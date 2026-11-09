<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_student();

$userId = (int)current_user()['id'];

$stmt = $pdo->prepare(
    "SELECT
        a.id,
        a.attended_at,
        e.title,
        e.category,
        e.event_date,
        e.venue
     FROM attendance a
     INNER JOIN events e ON e.id = a.event_id
     WHERE a.user_id = ?
     ORDER BY a.attended_at DESC"
);

$stmt->execute([$userId]);
$records = $stmt->fetchAll();

$pageTitle = 'My Attendance Records';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.attendance-page {
    max-width:1050px;
    margin:auto;
    padding:32px 18px 60px;
    color:#effff4;
}
.attendance-hero {
    padding:30px;
    border:1px solid #285b43;
    border-radius:20px;
    background:linear-gradient(120deg,#073b27,#116744);
}
.attendance-hero h1 { margin:8px 0; }
.attendance-hero p { color:#c3e8d1; }
.attendance-summary {
    display:flex;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:15px;
    margin:24px 0;
    padding:20px;
    border:1px solid #315d44;
    border-radius:15px;
    background:#0b2b1d;
}
.attendance-count {
    color:#aaffc6;
    font-size:32px;
    font-weight:900;
}
.attendance-list {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}
.attendance-card {
    padding:22px;
    border:1px solid #315d44;
    border-radius:16px;
    background:linear-gradient(145deg,#103b2a,#071f17);
}
.attendance-card h2 {
    margin:12px 0;
    font-size:19px;
}
.attendance-card p {
    margin:8px 0;
    color:#c4e4d0;
    font-size:13px;
}
.attendance-badge {
    display:inline-block;
    padding:7px 12px;
    border-radius:30px;
    background:#164d32;
    color:#b9ffd0;
    font-size:12px;
    font-weight:900;
}
.attendance-empty {
    padding:40px 20px;
    text-align:center;
    border:1px dashed #477456;
    border-radius:17px;
    background:#0b2b1d;
}
@media(max-width:700px) {
    .attendance-list { grid-template-columns:1fr; }
}
</style>

<main class="attendance-page">

    <section class="attendance-hero">
        <div style="color:#aaffc6;font-weight:900;letter-spacing:2px">
            STUDENT PARTICIPATION
        </div>
        <h1>My Attendance Records</h1>
        <p>Track the campus events you have successfully attended.</p>
    </section>

    <section class="attendance-summary">
        <div>
            <strong>Total Attended Events</strong>
            <p style="color:#b9d8c5;margin:5px 0 0">
                Your verified event participation
            </p>
        </div>

        <div class="attendance-count">
            <?= count($records) ?>
        </div>
    </section>

    <?php if (!$records): ?>

        <section class="attendance-empty">
            <div style="font-size:45px">📋</div>
            <h2>No Attendance Records Yet</h2>
            <p style="color:#b9d8c5">
                Your attendance records will appear here after
                an administrator verifies your event ticket.
            </p>
        </section>

    <?php else: ?>

        <section class="attendance-list">

            <?php foreach ($records as $record): ?>

                <article class="attendance-card">

                    <span class="attendance-badge">
                        ✓ ATTENDED
                    </span>

                    <h2><?= e($record['title']) ?></h2>

                    <p>
                        🏷️ <?= e($record['category']) ?>
                    </p>

                    <p>
                        📅 Event Date:
                        <?= e(date('F j, Y', strtotime($record['event_date']))) ?>
                    </p>

                    <p>
                        📍 Venue:
                        <?= e($record['venue']) ?>
                    </p>

                    <p>
                        ✓ Checked In:
                        <?= e(date('F j, Y - g:i A', strtotime($record['attended_at']))) ?>
                    </p>

                    <p>
                        Attendance ID: #<?= (int)$record['id'] ?>
                    </p>

                </article>

            <?php endforeach; ?>

        </section>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>