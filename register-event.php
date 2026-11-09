<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

$user = current_user();

if (!$user || strtolower((string)($user['role'] ?? '')) !== 'student') {
    redirect('admin/dashboard.php');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
}

if (!$id || $id < 1) {
    $_SESSION['flash_error'] = 'Invalid event.';
    redirect('events.php');
}

$userId = (int)($user['id'] ?? $_SESSION['user_id'] ?? 0);

if ($userId < 1) {
    $_SESSION['flash_error'] = 'Invalid student account.';
    redirect('events.php');
}

try {
    $pdo->beginTransaction();

    // Lock the event to prevent simultaneous registrations
    // from exceeding the event capacity.
    $stmt = $pdo->prepare(
        "SELECT id, title, event_date, status, capacity
         FROM events
         WHERE id = ?
         FOR UPDATE"
    );

    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $pdo->rollBack();

        $_SESSION['flash_error'] = 'Event not found.';
        redirect('events.php');
    }

    // Check whether registration is still open.
    $eventStatus = strtolower((string)$event['status']);

    if (
        $eventStatus !== 'upcoming' ||
        (
            !empty($event['event_date']) &&
            date('Y-m-d', strtotime((string)$event['event_date'])) < date('Y-m-d')
        )
    ) {
        $pdo->rollBack();

        $_SESSION['flash_error'] =
            'Registration is closed for this event.';

        redirect('event-details.php?id=' . $id);
    }

    // Check the student's existing registration.
    $stmt = $pdo->prepare(
        "SELECT id, status
         FROM registrations
         WHERE user_id = ? AND event_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([$userId, $id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        $existing &&
        strtolower((string)$existing['status']) === 'registered'
    ) {
        $pdo->commit();

        $_SESSION['flash_success'] =
            'You are already registered for this event.';

        redirect('my-registrations.php');
    }

    // Check whether the student is already in the waiting list.
    $stmt = $pdo->prepare(
        "SELECT id, status
         FROM event_waiting_list
         WHERE event_id = ? AND user_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([$id, $userId]);
    $waitingEntry = $stmt->fetch(PDO::FETCH_ASSOC);

    // Do not allow a student to bypass an existing waiting list.
    if (
        $waitingEntry &&
        strtolower((string)$waitingEntry['status']) === 'waiting'
    ) {
        $pdo->commit();

        $_SESSION['flash_success'] =
            'You are already on the waiting list for this event.';

        redirect('event-details.php?id=' . $id);
    }

    // Check event capacity.
    // Capacity 0 means unlimited, following the existing project logic.
    $capacity = (int)($event['capacity'] ?? 0);

    $registeredCount = 0;

    if ($capacity > 0) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM registrations
             WHERE event_id = ?
             AND LOWER(TRIM(status)) = 'registered'"
        );

        $stmt->execute([$id]);
        $registeredCount = (int)$stmt->fetchColumn();
    }

    // If the event is full, add the student to the waiting list.
    if ($capacity > 0 && $registeredCount >= $capacity) {

        if ($waitingEntry) {
            // Reuse the student's previous waiting-list record.
            $stmt = $pdo->prepare(
                "UPDATE event_waiting_list
                 SET status = 'waiting',
                     created_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );

            $stmt->execute([(int)$waitingEntry['id']]);

        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO event_waiting_list
                    (event_id, user_id, status)
                 VALUES
                    (?, ?, 'waiting')"
            );

            $stmt->execute([$id, $userId]);
        }

        $pdo->commit();

        $_SESSION['flash_success'] =
            'This event is full. You have successfully joined the waiting list.';

        redirect('event-details.php?id=' . $id);
    }

    // There is an available slot. Register the student.
    if ($existing) {
        $stmt = $pdo->prepare(
            "UPDATE registrations
             SET status = 'registered',
                 registered_at = CURRENT_TIMESTAMP
             WHERE id = ? AND user_id = ?"
        );

        $stmt->execute([
            (int)$existing['id'],
            $userId
        ]);

    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO registrations
                (user_id, event_id, status, registered_at)
             VALUES
                (?, ?, 'registered', CURRENT_TIMESTAMP)"
        );

        $stmt->execute([$userId, $id]);
    }

    // Update an old waiting-list record if one exists.
    if ($waitingEntry) {
        $stmt = $pdo->prepare(
            "UPDATE event_waiting_list
             SET status = 'registered',
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );

        $stmt->execute([(int)$waitingEntry['id']]);
    }

    $pdo->commit();

    $_SESSION['flash_success'] =
        'Registration successful! Your ticket is ready.';

    redirect('my-registrations.php');

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Register event: ' . $e->getMessage());

    $_SESSION['flash_error'] =
        'Registration failed. Please try again.';

    redirect('event-details.php?id=' . $id);
}