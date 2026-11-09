<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

$user = current_user();

if (!$user || strtolower((string) ($user['role'] ?? '')) !== 'student') {
    redirect('admin/dashboard.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

// CSRF validation
if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        (string) $_SESSION['csrf_token'],
        (string) ($_POST['csrf_token'] ?? '')
    )
) {
    $_SESSION['flash_error'] =
        'Security token expired. Refresh and try again.';

    redirect('my-registrations.php');
}

// Validate registration ID
$id = filter_input(
    INPUT_POST,
    'registration_id',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    $_SESSION['flash_error'] = 'Invalid registration.';
    redirect('my-registrations.php');
}

$userId = (int) ($user['id'] ?? 0);

if ($userId < 1) {
    $_SESSION['flash_error'] = 'Invalid student account.';
    redirect('my-registrations.php');
}

try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | STEP 1: FIND THE REGISTRATION
    |--------------------------------------------------------------------------
    */

    $findStmt = $pdo->prepare("
        SELECT event_id
        FROM registrations
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
    ");

    $findStmt->execute([$id, $userId]);

    $registrationInfo = $findStmt->fetch(PDO::FETCH_ASSOC);

    if (!$registrationInfo) {
        $pdo->rollBack();

        $_SESSION['flash_error'] =
            'Registration not found.';

        redirect('my-registrations.php');
    }

    $eventId = (int) $registrationInfo['event_id'];

    /*
    |--------------------------------------------------------------------------
    | STEP 2: LOCK THE EVENT
    |--------------------------------------------------------------------------
    | This prevents simultaneous registration and promotion
    | from taking the same available slot.
    */

    $eventStmt = $pdo->prepare("
        SELECT id, capacity, status, event_date
        FROM events
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $eventStmt->execute([$eventId]);

    $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $pdo->rollBack();

        $_SESSION['flash_error'] =
            'The event could not be found.';

        redirect('my-registrations.php');
    }

    /*
    |--------------------------------------------------------------------------
    | STEP 3: LOCK AND VALIDATE THE STUDENT REGISTRATION
    |--------------------------------------------------------------------------
    */

    $lockRegistrationStmt = $pdo->prepare("
        SELECT id, status
        FROM registrations
        WHERE id = ?
          AND event_id = ?
          AND user_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $lockRegistrationStmt->execute([
        $id,
        $eventId,
        $userId
    ]);

    $registration = $lockRegistrationStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$registration ||
        strtolower(trim((string) $registration['status'])) !== 'registered'
    ) {
        $pdo->rollBack();

        $_SESSION['flash_error'] =
            'Registration not found or already cancelled.';

        redirect('my-registrations.php');
    }

    /*
    |--------------------------------------------------------------------------
    | STEP 4: CANCEL THE REGISTRATION
    |--------------------------------------------------------------------------
    */

    $cancelStmt = $pdo->prepare("
        UPDATE registrations
        SET status = 'cancelled',
            qr_token = NULL
        WHERE id = ?
          AND user_id = ?
          AND status = 'registered'
    ");

    $cancelStmt->execute([
        $id,
        $userId
    ]);

    if ($cancelStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Unable to cancel the registration.'
        );
    }

    $promoted = false;

    /*
    |--------------------------------------------------------------------------
    | STEP 5: CHECK EVENT CAPACITY
    |--------------------------------------------------------------------------
    */

    $capacity = (int) ($event['capacity'] ?? 0);

    $eventStatus = strtolower(
        trim((string) ($event['status'] ?? ''))
    );

    $eventDate = strtotime(
        (string) ($event['event_date'] ?? '')
    );

    $eventIsUpcoming =
        $eventStatus === 'upcoming' &&
        $eventDate !== false &&
        $eventDate >= time();

    // Capacity of zero means unlimited.
    if ($capacity > 0 && $eventIsUpcoming) {

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM registrations
            WHERE event_id = ?
              AND status = 'registered'
        ");

        $countStmt->execute([$eventId]);

        $registeredCount = (int) $countStmt->fetchColumn();

        /*
        |--------------------------------------------------------------------------
        | STEP 6: PROMOTE THE FIRST WAITING STUDENT
        |--------------------------------------------------------------------------
        | Oldest waiting-list entry gets the available slot first.
        */

        if ($registeredCount < $capacity) {

            while (true) {

                $waitingStmt = $pdo->prepare("
                    SELECT id, user_id
                    FROM event_waiting_list
                    WHERE event_id = ?
                      AND status = 'waiting'
                    ORDER BY created_at ASC, id ASC
                    LIMIT 1
                    FOR UPDATE
                ");

                $waitingStmt->execute([$eventId]);

                $waiting = $waitingStmt->fetch(PDO::FETCH_ASSOC);

                if (!$waiting) {
                    break;
                }

                $waitingId = (int) $waiting['id'];
                $waitingUserId = (int) $waiting['user_id'];

                /*
                 * Check whether this student already has
                 * a registration record for the event.
                 */

                $existingStmt = $pdo->prepare("
                    SELECT id, status
                    FROM registrations
                    WHERE event_id = ?
                      AND user_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                    FOR UPDATE
                ");

                $existingStmt->execute([
                    $eventId,
                    $waitingUserId
                ]);

                $existingRegistration = $existingStmt->fetch(
                    PDO::FETCH_ASSOC
                );

                /*
                 * Do not create a duplicate registration.
                 * If already registered, synchronize the
                 * waiting-list status and check the next student.
                 */

                if (
                    $existingRegistration &&
                    strtolower(trim((string) $existingRegistration['status'])) === 'registered'
                ) {
                    $updateWaitingStmt = $pdo->prepare("
                        UPDATE event_waiting_list
                        SET status = 'registered'
                        WHERE id = ?
                    ");

                    $updateWaitingStmt->execute([$waitingId]);

                    continue;
                }

                /*
                 * Do not reactivate an attended registration.
                 */

                if (
                    $existingRegistration &&
                    strtolower(trim((string) $existingRegistration['status'])) === 'attended'
                ) {
                    $skipWaitingStmt = $pdo->prepare("
                        UPDATE event_waiting_list
                        SET status = 'cancelled'
                        WHERE id = ?
                    ");

                    $skipWaitingStmt->execute([$waitingId]);

                    continue;
                }

                /*
                 * Reactivate a previous cancelled registration
                 * or create a new registration.
                 */

                if ($existingRegistration) {

                    $promoteRegistrationStmt = $pdo->prepare("
                        UPDATE registrations
                        SET status = 'registered',
                            registered_at = CURRENT_TIMESTAMP,
                            qr_token = NULL
                        WHERE id = ?
                          AND status = 'cancelled'
                    ");

                    $promoteRegistrationStmt->execute([
                        (int) $existingRegistration['id']
                    ]);

                    if ($promoteRegistrationStmt->rowCount() !== 1) {
                        throw new RuntimeException(
                            'Unable to reactivate the waiting student registration.'
                        );
                    }

                } else {

                    $insertRegistrationStmt = $pdo->prepare("
                        INSERT INTO registrations
                            (event_id, user_id, status, registered_at, qr_token)
                        VALUES
                            (?, ?, 'registered', CURRENT_TIMESTAMP, NULL)
                    ");

                    $insertRegistrationStmt->execute([
                        $eventId,
                        $waitingUserId
                    ]);
                }

                /*
                 * Mark the waiting-list entry as promoted.
                 */

                $promoteWaitingStmt = $pdo->prepare("
                    UPDATE event_waiting_list
                    SET status = 'promoted'
                    WHERE id = ?
                      AND status = 'waiting'
                ");

                $promoteWaitingStmt->execute([$waitingId]);

                if ($promoteWaitingStmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Unable to update the waiting-list status.'
                    );
                }

                $promoted = true;

                // One cancellation creates one available slot.
                break;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STEP 7: COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    if ($promoted) {
        $_SESSION['flash_success'] =
            'Registration cancelled successfully. The next student on the waiting list has been promoted.';
    } else {
        $_SESSION['flash_success'] =
            'Registration cancelled successfully.';
    }

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Cancel Registration / Waiting List Promotion: ' .
        $exception->getMessage()
    );

    $_SESSION['flash_error'] =
        'Could not complete the cancellation. Please try again or contact the administrator.';
}

redirect('my-registrations.php');