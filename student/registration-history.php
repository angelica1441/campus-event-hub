<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_student();

$userId = (int)current_user()['id'];

$stmt = $pdo->prepare(
    "SELECT
        r.id AS registration_id,
        r.status,
        r.registered_at,
        e.id AS event_id,
        e.title,
        e.category,
        e.event_date,
        e.start_time,
        e.venue
     FROM registrations r
     INNER JOIN events e ON e.id = r.event_id
     WHERE r.user_id = ?
     ORDER BY r.registered_at DESC"
);

$stmt->execute([$userId]);
$registrations = $stmt->fetchAll();

$pageTitle = 'Registration History';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.history-page {
    max-width:1100px;
    margin:auto;
    padding:32px 18px 60px;
    color:#effff4;
}
.history-hero {
    padding:28px;
    border:1px solid #285b43;
    border-radius:20px;
    background:linear-gradient(120deg,#073b27,#116744);
}
.history-hero h1 { margin:8px 0; }
.history-hero p { color:#c3e8d1; }
.history-table-wrap {
    margin-top:24px;
    overflow-x:auto;
    border:1px solid #315d44;
    border-radius:15px;
    background:#0b2b1d;
}
.history-table {
    width:100%;
    min-width:700px;
    border-collapse:collapse;
}
.history-table th,
.history-table td {
    padding:15px;
    border-bottom:1px solid #31513f;
    text-align:left;
}
.history-table th {
    color:#aaffc6;
    background:#123e2b;
    font-size:12px;
    text-transform:uppercase;
}
.history-table td { color:#e5ffed; font-size:13px; }
.history-status {
    display:inline-block;
    padding:6px 10px;
    border-radius:30px;
    background:#164d32;
    color:#b9ffd0;
    font-weight:800;
}
.history-status.cancelled {
    background:#51262b;
    color:#ffd7dc;
}
.history-ticket {
    color:#aaffc6 !important;
    font-weight:800;
    text-decoration:none;
}
.history-empty {
    margin-top:22px;
    padding:35px;
    text-align:center;
    background:#0b2b1d;
    border:1px dashed #477456;
    border-radius:16px;
}
</style>

<main class="history-page">

    <section class="history-hero">
        <div style="color:#aaffc6;font-weight:900;letter-spacing:2px">
            STUDENT RECORDS
        </div>
        <h1>Registration History</h1>
        <p>Review all your previous and current campus event registrations.</p>
    </section>

    <?php if (!$registrations): ?>

        <section class="history-empty">
            <div style="font-size:42px">🎟️</div>
            <h2>No Registration History</h2>
            <p>You haven't registered for any campus events yet.</p>
            <a href="<?= e(app_url('events.php')) ?>"
               class="history-ticket">Browse Events →</a>
        </section>

    <?php else: ?>

        <div style="margin-top:25px">
            <strong>Total Records: <?= count($registrations) ?></strong>
        </div>

        <div class="history-table-wrap">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Category</th>
                        <th>Event Date</th>
                        <th>Registration Date</th>
                        <th>Status</th>
                        <th>Ticket</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($registrations as $r): ?>
                    <?php
                    $status = strtolower((string)$r['status']);
                    $cancelled = in_array(
                        $status,
                        ['cancelled', 'canceled'],
                        true
                    );
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($r['title']) ?></strong>
                            <br>
                            <small><?= e($r['venue']) ?></small>
                        </td>

                        <td><?= e($r['category']) ?></td>

                        <td>
                            <?= e(date('M d, Y', strtotime($r['event_date']))) ?>
                            <br>
                            <small><?= e(date('g:i A', strtotime($r['start_time']))) ?></small>
                        </td>

                        <td>
                            <?= e(date('M d, Y', strtotime($r['registered_at']))) ?>
                        </td>

                        <td>
                            <span class="history-status <?= $cancelled ? 'cancelled' : '' ?>">
                                <?= e(ucfirst($status)) ?>
                            </span>
                        </td>

                        <td>
                            <?php if (!$cancelled): ?>
                                <a class="history-ticket"
                                   href="<?= e(app_url('ticket.php?registration_id=' . (int)$r['registration_id'])) ?>">
                                    View Ticket
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>