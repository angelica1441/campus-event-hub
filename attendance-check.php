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
    http_response_code(403);
    exit('Only student accounts can check in.');
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/*
|--------------------------------------------------------------------------
| Request method
|--------------------------------------------------------------------------
| Uses filter_input instead of $_SERVER['REQUEST_METHOD'].
*/
$requestMethod = strtoupper(
    (string)(filter_input(INPUT_SERVER, 'REQUEST_METHOD') ?? 'GET')
);

$userId = (int)($user['id'] ?? 0);

$token = trim(
    (string)(filter_input(INPUT_GET, 'token', FILTER_UNSAFE_RAW) ?? '')
);

if ($userId < 1 || !preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
    http_response_code(400);
    exit('Invalid attendance QR code.');
}

try {
    $stmt = $pdo->prepare(
        "SELECT
            r.id AS registration_id,
            r.user_id,
            r.status AS registration_status,
            e.id AS event_id,
            e.title,
            e.event_date,
            e.start_time,
            e.end_time,
            e.venue
         FROM registrations r
         INNER JOIN events e ON e.id = r.event_id
         WHERE r.qr_token = ?
         LIMIT 1"
    );

    $stmt->execute([$token]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(404);
        exit('Attendance ticket not found.');
    }

    if ((int)$ticket['user_id'] !== $userId) {
        http_response_code(403);
        exit('This ticket does not belong to your student account.');
    }

    if (strtolower((string)$ticket['registration_status']) !== 'registered') {
        exit('This registration is no longer active.');
    }

    $attendanceStmt = $pdo->prepare(
        "SELECT id, attended_at
         FROM attendance
         WHERE registration_id = ?
         LIMIT 1"
    );

    $attendanceStmt->execute([
        (int)$ticket['registration_id']
    ]);

    $existingAttendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

    $alreadyAttended = (bool)$existingAttendance;
    $errorMessage = '';

    /*
    |--------------------------------------------------------------------------
    | Confirm attendance through POST only
    |--------------------------------------------------------------------------
    */
    if ($requestMethod === 'POST' && !$alreadyAttended) {

        require_valid_csrf();

        try {
            $pdo->beginTransaction();

            // Lock registration to prevent duplicate simultaneous check-ins.
            $lockStmt = $pdo->prepare(
                "SELECT id, user_id, status
                 FROM registrations
                 WHERE id = ? AND qr_token = ?
                 LIMIT 1
                 FOR UPDATE"
            );

            $lockStmt->execute([
                (int)$ticket['registration_id'],
                $token
            ]);

            $lockedRegistration = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$lockedRegistration ||
                (int)$lockedRegistration['user_id'] !== $userId ||
                strtolower((string)$lockedRegistration['status']) !== 'registered'
            ) {
                throw new RuntimeException('Registration is no longer valid.');
            }

            // Check again inside the transaction.
            $checkStmt = $pdo->prepare(
                "SELECT id
                 FROM attendance
                 WHERE registration_id = ?
                 LIMIT 1"
            );

            $checkStmt->execute([
                (int)$ticket['registration_id']
            ]);

            if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->commit();
                $alreadyAttended = true;
            } else {
                $insertStmt = $pdo->prepare(
                    "INSERT INTO attendance
                        (
                            registration_id,
                            user_id,
                            event_id,
                            attendance_mode,
                            verification_method,
                            marked_by
                        )
                     VALUES (?, ?, ?, 'face_to_face', 'qr', NULL)"
                );

                $insertStmt->execute([
                    (int)$ticket['registration_id'],
                    $userId,
                    (int)$ticket['event_id']
                ]);

                $pdo->commit();
                $alreadyAttended = true;
            }

        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'QR attendance check-in: ' . $exception->getMessage()
            );

            $errorMessage = 'Unable to record attendance. Please try again.';
        }
    }

} catch (Throwable $exception) {
    error_log('Attendance page: ' . $exception->getMessage());

    http_response_code(500);
    exit('Unable to load attendance information.');
}

$pageTitle = 'Event Attendance';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.attendance-page {
    max-width: 650px;
    margin: 45px auto;
    padding: 18px;
    color: #eafff2;
}

.attendance-card {
    background: linear-gradient(145deg, #103a29, #061d15);
    border: 1px solid #285b43;
    border-radius: 22px;
    padding: 30px;
    box-shadow: 0 20px 50px #00150e66;
}

.attendance-card h1 {
    color: #aaffc8;
    margin-bottom: 10px;
}

.attendance-detail {
    background: #ffffff0d;
    border: 1px solid #ffffff18;
    padding: 15px;
    border-radius: 12px;
    margin-top: 12px;
}

.attendance-detail small {
    display: block;
    color: #a9cbb7;
    margin-bottom: 5px;
}

.attendance-button {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 12px;
    margin-top: 22px;
    background: linear-gradient(135deg, #bdffd5, #61efa0);
    color: #082619;
    font-weight: 900;
    cursor: pointer;
}

.attendance-success {
    background: #164b31;
    border: 1px solid #61efa0;
    color: #caffd9;
    padding: 16px;
    border-radius: 12px;
    margin-top: 20px;
}

.attendance-error {
    background: #642c2c;
    color: #ffe0e0;
    padding: 14px;
    border-radius: 12px;
    margin-top: 15px;
}
</style>

<main class="attendance-page">
    <section class="attendance-card">

        <p style="color:#aaffc8;font-weight:800;letter-spacing:2px;">
            CAMPUS EVENT HUB
        </p>

        <h1>Event Attendance</h1>

        <p>Welcome, <?= e($user['full_name'] ?? 'Student') ?>.</p>

        <div class="attendance-detail">
            <small>Event</small>
            <strong><?= e($ticket['title']) ?></strong>
        </div>

        <div class="attendance-detail">
            <small>Event Date</small>
            <strong>
                <?= e(
                    !empty($ticket['event_date'])
                        ? date('F d, Y', strtotime((string)$ticket['event_date']))
                        : 'To be announced'
                ) ?>
            </strong>
        </div>

        <div class="attendance-detail">
            <small>Venue</small>
            <strong><?= e($ticket['venue'] ?: 'To be announced') ?></strong>
        </div>

        <?php if ($alreadyAttended): ?>

            <div class="attendance-success">
                <strong>✓ Attendance already recorded!</strong>
                <p style="margin:8px 0 0;">
                    Your attendance has already been submitted.
                </p>
            </div>

        <?php else: ?>

            <?php if ($errorMessage !== ''): ?>
                <div class="attendance-error">
                    <?= e($errorMessage) ?>
                </div>
            <?php endif; ?>

            <p style="margin-top:20px;">
                Confirm your attendance for this event.
            </p>

            <form method="POST">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <button class="attendance-button" type="submit">
                    Confirm My Attendance
                </button>
            </form>

        <?php endif; ?>

    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>