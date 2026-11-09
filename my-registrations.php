<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

$user = current_user();

$userId = (int) (
    $user['id'] ?? $_SESSION['user_id'] ?? 0
);

if ($userId < 1) {
    exit('User session not found. Please log in again.');
}

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = (string) ($_SESSION['flash_success'] ?? '');
$error = (string) ($_SESSION['flash_error'] ?? '');

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$registrations = [];
$loadError = '';

try {
    /*
    |--------------------------------------------------------------------------
    | 1. Load regular registrations
    |--------------------------------------------------------------------------
    | Cancelled registrations are hidden only when the student
    | currently has a waiting-list entry for the same event.
    | This prevents duplicate event cards.
    */

    $stmt = $pdo->prepare(
        "SELECT
            r.id AS registration_id,
            r.user_id,
            r.event_id,
            r.status AS registration_status,
            r.registered_at AS activity_date,
            e.title AS event_title,
            e.category,
            e.event_date,
            e.start_time,
            e.end_time,
            e.venue,
            'registration' AS record_type
         FROM registrations r
         INNER JOIN events e
            ON e.id = r.event_id
         WHERE r.user_id = ?
           AND NOT (
                LOWER(TRIM(r.status)) = 'cancelled'
                AND EXISTS (
                    SELECT 1
                    FROM event_waiting_list wl
                    WHERE wl.event_id = r.event_id
                      AND wl.user_id = r.user_id
                      AND wl.status = 'waiting'
                )
           )
         ORDER BY r.registered_at DESC"
    );

    $stmt->execute([$userId]);

    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | 2. Load students currently in the waiting list
    |--------------------------------------------------------------------------
    */

    $waitingStmt = $pdo->prepare(
        "SELECT
            NULL AS registration_id,
            wl.user_id,
            wl.event_id,
            wl.status AS registration_status,
            wl.created_at AS activity_date,
            e.title AS event_title,
            e.category,
            e.event_date,
            e.start_time,
            e.end_time,
            e.venue,
            'waiting_list' AS record_type
         FROM event_waiting_list wl
         INNER JOIN events e
            ON e.id = wl.event_id
         WHERE wl.user_id = ?
           AND wl.status = 'waiting'
         ORDER BY wl.created_at DESC"
    );

    $waitingStmt->execute([$userId]);

    $waitingRegistrations = $waitingStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | 3. Combine registrations and waiting-list entries
    |--------------------------------------------------------------------------
    */

    $registrations = array_merge(
        $registrations,
        $waitingRegistrations
    );

    /*
    | Sort by most recent registration or waiting-list entry.
    */

    usort($registrations, static function ($a, $b) {
        $dateA = strtotime((string) ($a['activity_date'] ?? '')) ?: 0;
        $dateB = strtotime((string) ($b['activity_date'] ?? '')) ?: 0;

        return $dateB <=> $dateA;
    });

} catch (PDOException $exception) {
    error_log('My registrations: ' . $exception->getMessage());

    $loadError = 'Hindi ma-load ang registrations at waiting list. I-check ang database tables at columns.';
}

$pageTitle = 'My Registrations';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.myreg {
    max-width: 1150px;
    margin: auto;
    padding: 30px 20px 60px;
    color: #eafff1;
}

.myreg-hero {
    padding: 30px;
    border: 1px solid #285b43;
    border-radius: 22px;
    background: linear-gradient(120deg, #073322, #126541);
}

.myreg-hero h1 {
    margin: 8px 0;
    font-size: clamp(28px, 4vw, 42px);
}

.myreg-hero p,
.myreg p {
    color: #bfdfcc;
}

.myreg-alert {
    margin-top: 16px;
    padding: 14px 16px;
    border-radius: 12px;
    background: #164b31;
    color: #c8ffda;
}

.myreg-alert.error {
    background: #51262b;
    color: #ffd7dc;
}

.myreg-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    margin: 28px 0 18px;
}

.myreg-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.myreg-card {
    padding: 22px;
    border: 1px solid #285641;
    border-radius: 18px;
    background: linear-gradient(145deg, #103a29, #071f17);
    box-shadow: 0 12px 28px #00150d44;
}

.myreg-card h2 {
    margin: 14px 0;
    font-size: 21px;
    overflow-wrap: anywhere;
}

.myreg-meta {
    display: grid;
    gap: 11px;
    color: #c9e3d2;
    font-size: 14px;
}

.myreg-status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 999px;
    background: #164d32;
    color: #b9ffd0;
    font-size: 12px;
    font-weight: 800;
}

.myreg-status.cancelled {
    background: #51262b;
    color: #ffd7dc;
}

.myreg-status.waiting {
    background: #594414;
    color: #ffe69a;
}

.myreg-status.attended {
    background: #173e60;
    color: #b9ddff;
}

.myreg-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #31513f;
}

.myreg-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    border: 1px solid #426c53;
    border-radius: 10px;
    padding: 11px 14px;
    background: #153e2b;
    color: #eafff1 !important;
    font-family: inherit;
    font-weight: 800;
    font-size: 13px;
    text-decoration: none;
    cursor: pointer;
}

.myreg-btn.primary {
    background: linear-gradient(135deg, #b9ffd1, #5ce99c);
    border-color: #9cffbd;
    color: #082a19 !important;
}

.myreg-actions form {
    margin: 0;
}

.myreg-info {
    margin-top: 15px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #493b16;
    color: #ffe8a6;
    font-size: 13px;
    line-height: 1.6;
}

.myreg-info.success {
    background: #164b31;
    color: #c8ffda;
}

.myreg-empty {
    text-align: center;
    padding: 45px 20px;
    margin-top: 20px;
    border: 1px dashed #396b50;
    border-radius: 18px;
    background: #0a2a1e;
}

@media (max-width: 720px) {
    .myreg-grid {
        grid-template-columns: 1fr;
    }

    .myreg {
        padding: 20px 13px 40px;
    }
}
</style>

<main class="myreg">

    <section class="myreg-hero">
        <div style="font-weight:900;letter-spacing:2px;color:#9bffc0">
            CAMPUS EVENT HUB
        </div>

        <h1>My Registrations</h1>

        <p>
            View your event registrations, check your waiting-list
            status, access your ticket, and manage your bookings here.
        </p>
    </section>

    <?php if ($success !== ''): ?>
        <div class="myreg-alert">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="myreg-alert error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($loadError !== ''): ?>
        <div class="myreg-alert error">
            <?= e($loadError) ?>
        </div>
    <?php endif; ?>

    <div class="myreg-toolbar">
        <div>
            <h2>Your Events</h2>

            <p>
                Total records found: <?= count($registrations) ?>
            </p>
        </div>

        <a class="myreg-btn primary" href="events.php">
            + Browse Events
        </a>
    </div>

    <?php if (!$registrations && !$loadError): ?>

        <section class="myreg-empty">
            <div style="font-size:44px">🎟️</div>

            <h2>No registrations found</h2>

            <p>
                You do not have any event registrations or
                waiting-list entries yet.
            </p>

            <a class="myreg-btn primary" href="events.php">
                Browse Events
            </a>
        </section>

    <?php elseif ($registrations): ?>

        <section class="myreg-grid">

            <?php foreach ($registrations as $r): ?>

                <?php
                $status = strtolower(trim(
                    (string) ($r['registration_status'] ?? '')
                ));

                $isWaiting = (
                    ($r['record_type'] ?? '') === 'waiting_list'
                    && $status === 'waiting'
                );

                $isRegistered = $status === 'registered';

                $isAttended = $status === 'attended';

                $isCancelled = in_array(
                    $status,
                    ['cancelled', 'canceled'],
                    true
                );

                $statusClass = '';

                if ($isWaiting) {
                    $statusClass = 'waiting';
                } elseif ($isCancelled) {
                    $statusClass = 'cancelled';
                } elseif ($isAttended) {
                    $statusClass = 'attended';
                }

                $date = !empty($r['event_date'])
                    ? date(
                        'F j, Y',
                        strtotime((string) $r['event_date'])
                    )
                    : 'To be announced';

                $time = !empty($r['start_time'])
                    ? date(
                        'g:i A',
                        strtotime((string) $r['start_time'])
                    )
                    : 'To be announced';

                if (
                    $time !== 'To be announced'
                    && !empty($r['end_time'])
                ) {
                    $time .= ' – ' . date(
                        'g:i A',
                        strtotime((string) $r['end_time'])
                    );
                }

                $displayStatus = $status !== ''
                    ? ucwords(str_replace('_', ' ', $status))
                    : 'Unknown';
                ?>

                <article class="myreg-card">

                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:center">

                        <span style="font-size:25px">
                            <?= $isWaiting ? '⏳' : '🎓' ?>
                        </span>

                        <span class="myreg-status <?= e($statusClass) ?>">
                            <?= e($displayStatus) ?>
                        </span>

                    </div>

                    <h2><?= e($r['event_title']) ?></h2>

                    <div class="myreg-meta">

                        <div>
                            📅 <?= e($date) ?>
                        </div>

                        <div>
                            🕒 <?= e($time) ?>
                        </div>

                        <div>
                            📍 <?= e($r['venue'] ?: 'Venue to be announced') ?>
                        </div>

                        <div>
                            🏷️ <?= e($r['category'] ?: 'Campus Event') ?>
                        </div>

                        <?php if (!$isWaiting && !empty($r['registration_id'])): ?>
                            <div>
                                Registration ID:
                                <?= (int) $r['registration_id'] ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($isWaiting): ?>
                            <div>
                                Waiting List Entry:
                                <?= !empty($r['activity_date'])
                                    ? e(date(
                                        'F j, Y g:i A',
                                        strtotime((string) $r['activity_date'])
                                    ))
                                    : 'Available' ?>
                            </div>
                        <?php endif; ?>

                    </div>

                    <?php if ($isWaiting): ?>

                        <div class="myreg-info">
                            ⏳ You are currently on the waiting list
                            for this event. Your registration will be
                            promoted when a slot becomes available.
                        </div>

                    <?php elseif ($isRegistered): ?>

                        <div class="myreg-info success">
                            ✓ Your event registration is active.
                            You can access your QR ticket below.
                        </div>

                    <?php elseif ($isAttended): ?>

                        <div class="myreg-info success">
                            ✓ Your attendance has been recorded
                            for this event.
                        </div>

                    <?php elseif ($isCancelled): ?>

                        <div class="myreg-info">
                            This registration has been cancelled.
                        </div>

                    <?php endif; ?>

                    <div class="myreg-actions">

                        <?php if ($isRegistered && !empty($r['registration_id'])): ?>

                            <a
                                class="myreg-btn primary"
                                href="ticket.php?registration_id=<?= (int) $r['registration_id'] ?>"
                            >
                                ▦ View QR Ticket
                            </a>

                            <form
                                method="post"
                                action="cancel-registration.php"
                                onsubmit="return confirm('Are you sure you want to cancel this registration?');"
                            >

                                <input
                                    type="hidden"
                                    name="registration_id"
                                    value="<?= (int) $r['registration_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                >

                                <button class="myreg-btn" type="submit">
                                    Cancel Registration
                                </button>

                            </form>

                        <?php endif; ?>

                        <a
                            class="myreg-btn"
                            href="event-details.php?id=<?= (int) $r['event_id'] ?>"
                        >
                            Event Details
                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        </section>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>