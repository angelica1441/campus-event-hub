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

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/*
|--------------------------------------------------------------------------
| Validate registration ID
|--------------------------------------------------------------------------
*/

$id = filter_input(INPUT_GET, 'registration_id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid ticket reference.');
}

$userId = (int)($user['id'] ?? 0);

if ($userId < 1) {
    http_response_code(403);
    exit('Invalid student account.');
}

/*
|--------------------------------------------------------------------------
| Load registration and generate secure QR token
|--------------------------------------------------------------------------
*/

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT
            r.id AS registration_id,
            r.status AS registration_status,
            r.registered_at,
            r.qr_token,
            e.id AS event_id,
            e.title,
            e.category,
            e.event_date,
            e.start_time,
            e.end_time,
            e.venue,
            e.organizer
         FROM registrations r
         INNER JOIN events e ON e.id = r.event_id
         WHERE r.id = ?
           AND r.user_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([$id, $userId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$ticket ||
        strtolower((string)$ticket['registration_status']) !== 'registered'
    ) {
        $pdo->rollBack();

        http_response_code(404);
        exit('Ticket not found or registration cancelled.');
    }

    /*
     * Generate a secure token if the existing token is invalid.
     * 32 random bytes = 64 hexadecimal characters.
     */

    $qrToken = (string)($ticket['qr_token'] ?? '');

    if (strlen($qrToken) !== 64 || !ctype_xdigit($qrToken)) {
        $qrToken = bin2hex(random_bytes(32));

        $update = $pdo->prepare(
            "UPDATE registrations
             SET qr_token = ?
             WHERE id = ?
               AND user_id = ?
               AND status = 'registered'"
        );

        $update->execute([
            $qrToken,
            $id,
            $userId
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException(
                'Unable to save the QR ticket token.'
            );
        }
    }

    $pdo->commit();

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Ticket QR generation: ' . $exception->getMessage());

    http_response_code(500);
    exit('Unable to generate your ticket. Please try again.');
}

/*
|--------------------------------------------------------------------------
| Ticket information
|--------------------------------------------------------------------------
*/

$ticketNumber = 'CEH-' . date('Y') . '-' .
    str_pad(
        (string)$ticket['registration_id'],
        6,
        '0',
        STR_PAD_LEFT
    );

$date = !empty($ticket['event_date'])
    ? date('F d, Y', strtotime((string)$ticket['event_date']))
    : 'To be announced';

$time = !empty($ticket['start_time'])
    ? date('g:i A', strtotime((string)$ticket['start_time']))
    : '';

if ($time && !empty($ticket['end_time'])) {
    $time .= ' – ' . date(
        'g:i A',
        strtotime((string)$ticket['end_time'])
    );
}

if (!$time) {
    $time = 'To be announced';
}

/*
|--------------------------------------------------------------------------
| QR image - Opens Attendance Page
|--------------------------------------------------------------------------
|
| The QR now contains the full Attendance URL with the secure token.
| Update this base URL if your local IP address changes.
|
*/

$appBaseUrl = 'http://192.168.100.16/campus-event-hub';

$attendanceUrl = $appBaseUrl
    . '/attendance-check.php?token='
    . rawurlencode($qrToken);

$qrCodeUrl =
    'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data='
    . rawurlencode($attendanceUrl);

$pageTitle = 'Event Ticket';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.ticket-page {
    max-width: 960px;
    margin: 32px auto;
    padding: 18px;
    color: #eafff2;
}

.ticket-shell {
    overflow: hidden;
    border: 1px solid #285b43;
    border-radius: 24px;
    background: linear-gradient(145deg, #103a29, #061d15);
    box-shadow: 0 22px 55px #00150e66;
}

.ticket-head {
    padding: 30px;
    background: linear-gradient(120deg, #baffd0, #5aefa0);
    color: #0a2b1a;
}

.ticket-head small {
    font-weight: 900;
    letter-spacing: 2px;
}

.ticket-head h1 {
    font-size: clamp(27px, 4vw, 40px);
    overflow-wrap: anywhere;
    margin: 12px 0;
}

.ticket-head p {
    color: #204b34;
}

.ticket-body {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 250px;
    gap: 22px;
    padding: 26px;
}

.ticket-reference {
    text-align: center;
    padding: 15px;
    border: 1px dashed #78efa1;
    border-radius: 13px;
    margin-bottom: 16px;
}

.ticket-reference small {
    display: block;
    color: #afd3bd;
    margin-bottom: 7px;
}

.ticket-reference strong {
    font-size: 20px;
    color: #a9ffc2;
    overflow-wrap: anywhere;
}

.ticket-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.ticket-detail {
    padding: 14px;
    border: 1px solid #ffffff16;
    border-radius: 12px;
    overflow-wrap: anywhere;
}

.ticket-detail small {
    display: block;
    color: #a9cbb7;
    margin-bottom: 7px;
}

.ticket-detail strong {
    overflow-wrap: anywhere;
}

.ticket-qr {
    align-self: start;
    padding: 16px;
    border-radius: 16px;
    background: #fff;
    color: #163324;
    text-align: center;
}

.ticket-qr img {
    display: block;
    width: 100%;
    max-width: 220px;
    height: auto;
    margin: 0 auto 12px;
}

.ticket-qr p {
    font-size: 12px;
    color: #52675a;
    margin-top: 8px;
}

.ticket-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.ticket-btn {
    padding: 12px 16px;
    border: 1px solid #38654d;
    border-radius: 11px;
    background: #123b2a;
    color: #e5ffed !important;
    text-decoration: none;
    font-weight: 800;
    cursor: pointer;
    font-family: inherit;
}

.ticket-btn.primary {
    background: linear-gradient(135deg, #bdffd5, #61efa0);
    color: #082619 !important;
}

.ticket-security-note {
    font-size: 12px;
    color: #52675a;
    margin-top: 8px;
}

@media (max-width: 700px) {
    .ticket-page {
        padding: 10px;
    }

    .ticket-body {
        grid-template-columns: 1fr;
        padding: 18px;
    }

    .ticket-grid {
        grid-template-columns: 1fr;
    }

    .ticket-actions {
        flex-direction: column;
    }

    .ticket-btn {
        text-align: center;
    }
}

@media print {
    header,
    footer,
    .ticket-actions {
        display: none !important;
    }

    body {
        background: #fff !important;
    }

    .ticket-page {
        margin: 0;
        padding: 0;
        color: #123;
    }

    .ticket-shell {
        background: #fff;
        color: #123;
        box-shadow: none;
        border: 1px solid #aaa;
    }

    .ticket-body {
        grid-template-columns: minmax(0, 1fr) 210px;
    }

    .ticket-detail {
        border-color: #ccc;
    }

    .ticket-detail small,
    .ticket-reference small {
        color: #444;
    }

    .ticket-reference strong {
        color: #155c32;
    }

    .ticket-head {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

<main class="ticket-page">

    <div class="ticket-shell">

        <div class="ticket-head">
            <small>CAMPUS EVENT HUB · ADMISSION TICKET</small>

            <h1><?= e($ticket['title']) ?></h1>

            <p>
                Your registration is confirmed.
                Keep this ticket for event check-in.
            </p>
        </div>

        <div class="ticket-body">

            <div>

                <div class="ticket-reference">
                    <small>YOUR TICKET REFERENCE</small>
                    <strong><?= e($ticketNumber) ?></strong>
                </div>

                <div class="ticket-grid">

                    <div class="ticket-detail">
                        <small>Participant</small>
                        <strong>
                            <?= e(
                                $user['full_name']
                                ?? $user['username']
                                ?? 'Student'
                            ) ?>
                        </strong>
                    </div>

                    <div class="ticket-detail">
                        <small>Category</small>
                        <strong>
                            <?= e($ticket['category'] ?: 'Campus Event') ?>
                        </strong>
                    </div>

                    <div class="ticket-detail">
                        <small>Event date</small>
                        <strong><?= e($date) ?></strong>
                    </div>

                    <div class="ticket-detail">
                        <small>Schedule</small>
                        <strong><?= e($time) ?></strong>
                    </div>

                    <div class="ticket-detail">
                        <small>Venue</small>
                        <strong>
                            <?= e($ticket['venue'] ?: 'To be announced') ?>
                        </strong>
                    </div>

                    <div class="ticket-detail">
                        <small>Organizer</small>
                        <strong>
                            <?= e($ticket['organizer'] ?: 'Campus Event Team') ?>
                        </strong>
                    </div>

                    <div class="ticket-detail">
                        <small>Registered on</small>
                        <strong>
                            <?= !empty($ticket['registered_at'])
                                ? e(date(
                                    'F d, Y',
                                    strtotime((string)$ticket['registered_at'])
                                ))
                                : 'Confirmed'
                            ?>
                        </strong>
                    </div>

                    <div class="ticket-detail">
                        <small>Status</small>
                        <strong>Registered</strong>
                    </div>

                </div>

                <p style="margin-top:16px;color:#aaffc8">
                    ✓ Registration found in Campus Event Hub.
                </p>

                <div class="ticket-actions">

                    <button
                        class="ticket-btn primary"
                        type="button"
                        onclick="window.print()"
                    >
                        Print / Save as PDF
                    </button>

                    <a
                        class="ticket-btn"
                        href="my-registrations.php"
                    >
                        ← My Registrations
                    </a>

                </div>

            </div>

            <aside class="ticket-qr">

                <img
                    src="<?= e($qrCodeUrl) ?>"
                    alt="Attendance QR Code"
                >

                <strong>Attendance QR Code</strong>

                <p>
                    Scan this QR code using your phone camera
                    to open the Attendance page.
                </p>

                <p class="ticket-security-note">
                    Each ticket has a unique secure QR token.
                </p>

            </aside>

        </div>

    </div>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
SET qr_token = ?
 WHERE id = ?
   AND user_id = ?
   AND status = 'registered'
   AND qr_token IS NULL"