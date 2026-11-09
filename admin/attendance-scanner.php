<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_admin();

$pageTitle = 'Attendance Monitoring';

$selectedEventId = filter_input(
    INPUT_GET,
    'event_id',
    FILTER_VALIDATE_INT
);

$selectedEventId = ($selectedEventId !== false && $selectedEventId !== null)
    ? max(0, (int) $selectedEventId)
    : 0;

$events = [];
$attendanceRecords = [];

$stats = [
    'total' => 0,
    'face_to_face' => 0,
    'online' => 0,
];

$pageError = '';

try {
    // GET EVENTS FOR THE FILTER DROPDOWN
    $eventsQuery = "
        SELECT id, title
        FROM events
        ORDER BY event_date DESC, id DESC
    ";

    $events = $pdo->query($eventsQuery)->fetchAll(PDO::FETCH_ASSOC);

    // BUILD EVENT FILTER
    $where = '';
    $params = [];

    if ($selectedEventId > 0) {
        $where = 'WHERE a.event_id = ?';
        $params[] = $selectedEventId;
    }

    // ATTENDANCE STATISTICS
    $statsQuery = "
        SELECT
            COUNT(*) AS total,
            SUM(
                CASE
                    WHEN LOWER(COALESCE(a.attendance_mode, '')) = 'face_to_face'
                    THEN 1 ELSE 0
                END
            ) AS face_to_face,
            SUM(
                CASE
                    WHEN LOWER(COALESCE(a.attendance_mode, '')) = 'online'
                    THEN 1 ELSE 0
                END
            ) AS online
        FROM attendance a
        $where
    ";

    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute($params);

    $statsResult = $statsStmt->fetch(PDO::FETCH_ASSOC);

    if ($statsResult) {
        $stats = [
            'total' => (int) ($statsResult['total'] ?? 0),
            'face_to_face' => (int) ($statsResult['face_to_face'] ?? 0),
            'online' => (int) ($statsResult['online'] ?? 0),
        ];
    }

    // ATTENDANCE RECORDS
    $attendanceQuery = "
        SELECT
            a.id AS attendance_id,
            a.attended_at,
            a.attendance_mode,
            a.verification_method,
            u.full_name AS student_name,
            u.email AS student_email,
            e.title AS event_title,
            e.event_date,
            e.venue
        FROM attendance a
        INNER JOIN users u
            ON u.id = a.user_id
        INNER JOIN events e
            ON e.id = a.event_id
        $where
        ORDER BY a.attended_at DESC, a.id DESC
    ";

    $attendanceStmt = $pdo->prepare($attendanceQuery);
    $attendanceStmt->execute($params);

    $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {
    error_log(
        'Attendance Monitoring Error: ' . $exception->getMessage()
    );

    $pageError = 'Attendance information could not be loaded. Please check your database structure.';
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.attendance-page {
    --att-green: #c7e78b;
    --att-text: #edf5e9;
    --att-muted: #a7b9a9;

    padding: 12px 0 45px;
    color: var(--att-text);
}

.attendance-page * {
    box-sizing: border-box;
}

.attendance-hero {
    position: relative;
    overflow: hidden;
    margin-bottom: 24px;
    padding: 30px;
    border: 1px solid rgba(199,231,139,.45);
    border-radius: 22px;
    background:
        radial-gradient(circle at 90% 10%, rgba(199,231,139,.16), transparent 30%),
        linear-gradient(125deg, #091d16, #123b2b 70%, #1b5038);
    box-shadow: 0 15px 35px rgba(0,0,0,.2);
}

.attendance-hero .eyebrow {
    color: #d2f0a9;
    font-size: .72rem;
    font-weight: 850;
    letter-spacing: 1.5px;
}

.attendance-hero h1 {
    margin: 14px 0 8px;
    color: #fff;
    font-size: clamp(1.8rem, 4vw, 2.5rem);
    font-weight: 850;
}

.attendance-hero p {
    max-width: 650px;
    margin: 0;
    color: #b6cdbb;
    font-size: .9rem;
    line-height: 1.8;
}

.attendance-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.attendance-stat {
    padding: 21px;
    border: 1px solid rgba(199,231,139,.35);
    border-radius: 17px;
    background: linear-gradient(145deg, #164630, #0b291d);
    box-shadow: 0 10px 25px rgba(0,0,0,.15);
}

.attendance-stat:nth-child(2) {
    border-color: rgba(130,190,210,.5);
}

.attendance-stat:nth-child(3) {
    border-color: rgba(220,190,125,.5);
}

.attendance-stat .stat-label {
    display: block;
    color: #b6c9b8;
    font-size: .78rem;
    font-weight: 700;
}

.attendance-stat .stat-value {
    display: block;
    margin-top: 8px;
    color: #d2f0a9;
    font-size: 2rem;
    font-weight: 850;
}

.attendance-panel {
    overflow: hidden;
    border: 1px solid rgba(199,231,139,.35);
    border-radius: 19px;
    background: linear-gradient(145deg, #143d2a, #0a271c);
    box-shadow: 0 12px 30px rgba(0,0,0,.17);
}

.attendance-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    padding: 22px;
    border-bottom: 1px solid rgba(199,231,139,.2);
}

.attendance-panel-header h2 {
    margin: 0;
    color: #e5f4dc;
    font-size: 1.2rem;
    font-weight: 850;
}

.attendance-panel-header p {
    margin: 6px 0 0;
    color: #a6bba9;
    font-size: .8rem;
}

.attendance-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 17px 22px;
    border-bottom: 1px solid rgba(199,231,139,.15);
    background: rgba(4,25,17,.2);
}

.attendance-filters select,
.attendance-filters input {
    min-height: 42px;
    padding: 9px 12px;
    border: 1px solid rgba(199,231,139,.3);
    border-radius: 10px;
    outline: none;
    background: #0b2a1d;
    color: #edf5e9;
    font-size: .82rem;
}

.attendance-filters select {
    min-width: 220px;
}

.attendance-filters input {
    flex: 1;
    min-width: 180px;
}

.attendance-filters input::placeholder {
    color: #8da492;
}

.attendance-filters select:focus,
.attendance-filters input:focus {
    border-color: #c7e78b;
    box-shadow: 0 0 0 3px rgba(199,231,139,.12);
}

.attendance-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.attendance-table {
    width: 100%;
    min-width: 900px;
    margin: 0;
    border-collapse: collapse;
}

.attendance-table th {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(199,231,139,.25);
    background: rgba(4,27,18,.45);
    color: #c5dfa8;
    font-size: .68rem;
    font-weight: 850;
    letter-spacing: .6px;
    text-align: left;
    text-transform: uppercase;
    white-space: nowrap;
}

.attendance-table td {
    padding: 16px;
    border-bottom: 1px solid rgba(199,231,139,.12);
    color: #c5d5c8;
    font-size: .79rem;
    vertical-align: middle;
}

.attendance-table tbody tr:hover {
    background: rgba(199,231,139,.05);
}

.attendance-student {
    display: block;
    color: #e0f0d7;
    font-weight: 800;
}

.attendance-email,
.attendance-venue {
    display: block;
    margin-top: 4px;
    color: #91a99a;
    font-size: .72rem;
    overflow-wrap: anywhere;
}

.attendance-event {
    color: #d2e9c4;
    font-weight: 750;
}

.attendance-date {
    color: #c5d5c8;
    white-space: nowrap;
}

.attendance-badge {
    display: inline-block;
    padding: 6px 9px;
    border: 1px solid rgba(199,231,139,.25);
    border-radius: 20px;
    background: rgba(199,231,139,.09);
    color: #d2f0a9;
    font-size: .68rem;
    font-weight: 800;
    white-space: nowrap;
}

.attendance-badge.online {
    border-color: rgba(130,190,210,.35);
    background: rgba(70,145,185,.13);
    color: #a8ddf5;
}

.attendance-badge.manual {
    border-color: rgba(220,190,125,.35);
    background: rgba(190,150,65,.13);
    color: #f0d28c;
}

.attendance-empty {
    padding: 45px 20px !important;
    color: #a6bba9 !important;
    text-align: center;
}

.attendance-empty strong {
    display: block;
    margin-bottom: 7px;
    color: #d2f0a9;
    font-size: 1rem;
}

.attendance-alert {
    margin-bottom: 20px;
    border-radius: 12px;
}

.attendance-footer {
    padding: 14px 22px;
    color: #91a99a;
    font-size: .76rem;
}

@media (max-width: 650px) {
    .attendance-hero {
        padding: 23px 19px;
    }

    .attendance-stats {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .attendance-stat {
        padding: 16px;
    }

    .attendance-stat .stat-value {
        font-size: 1.7rem;
    }

    .attendance-panel-header {
        align-items: flex-start;
        flex-direction: column;
        padding: 18px;
    }

    .attendance-filters {
        flex-direction: column;
        padding: 15px;
    }

    .attendance-filters select,
    .attendance-filters input {
        width: 100%;
        min-width: 0;
    }
}
</style>

<main class="attendance-page">
    <div class="container">

        <section class="attendance-hero">
            <span class="eyebrow">CAMPUS EVENT HUB · ADMIN</span>

            <h1>Attendance Monitoring</h1>

            <p>
                Monitor student attendance records, review event participation,
                and check when students confirmed their attendance.
            </p>
        </section>

        <?php if ($pageError !== ''): ?>
            <div class="alert alert-danger attendance-alert" role="alert">
                <?= e($pageError) ?>
            </div>
        <?php endif; ?>

        <!-- ATTENDANCE STATISTICS -->
        <section class="attendance-stats">

            <article class="attendance-stat">
                <span class="stat-label">Total Attendance Records</span>
                <strong class="stat-value">
                    <?= number_format($stats['total']) ?>
                </strong>
            </article>

            <article class="attendance-stat">
                <span class="stat-label">Face-to-Face Attendance</span>
                <strong class="stat-value">
                    <?= number_format($stats['face_to_face']) ?>
                </strong>
            </article>

            <article class="attendance-stat">
                <span class="stat-label">Online Attendance</span>
                <strong class="stat-value">
                    <?= number_format($stats['online']) ?>
                </strong>
            </article>

        </section>

        <!-- ATTENDANCE RECORDS -->
        <section class="attendance-panel">

            <div class="attendance-panel-header">
                <div>
                    <h2>Student Attendance Records</h2>
                    <p>Attendance entries recorded in the system.</p>
                </div>
            </div>

            <form method="get" class="attendance-filters">

                <select
                    name="event_id"
                    aria-label="Filter attendance by event"
                    onchange="this.form.submit()"
                >
                    <option value="0">All Events</option>

                    <?php foreach ($events as $event): ?>
                        <option
                            value="<?= (int) $event['id'] ?>"
                            <?= $selectedEventId === (int) $event['id'] ? 'selected' : '' ?>
                        >
                            <?= e($event['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input
                    type="search"
                    id="attendanceSearch"
                    placeholder="Search student, email, event, or method..."
                    aria-label="Search attendance records"
                >

            </form>

            <div class="attendance-table-wrap">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Event</th>
                            <th>Attendance Date</th>
                            <th>Mode</th>
                            <th>Verification</th>
                        </tr>
                    </thead>

                    <tbody id="attendanceTableBody">

                    <?php if (!empty($attendanceRecords)): ?>

                        <?php foreach ($attendanceRecords as $record): ?>
                            <?php
                            $attendedAt = (string) ($record['attended_at'] ?? '');
                            $attendedTimestamp = $attendedAt !== ''
                                ? strtotime($attendedAt)
                                : false;

                            $mode = strtolower(
                                trim((string) ($record['attendance_mode'] ?? ''))
                            );

                            $method = strtolower(
                                trim((string) ($record['verification_method'] ?? ''))
                            );

                            $modeLabel = match ($mode) {
                                'face_to_face' => 'Face-to-Face',
                                'online' => 'Online',
                                default => ucfirst(str_replace('_', ' ', $mode ?: 'Unknown')),
                            };

                            $methodLabel = match ($method) {
                                'qr' => 'QR Code',
                                'manual' => 'Manual',
                                'online_link' => 'Online Link',
                                default => ucfirst(str_replace('_', ' ', $method ?: 'Unknown')),
                            };

                            $modeClass = $mode === 'online' ? 'online' : '';
                            $methodClass = $method === 'manual' ? 'manual' : '';

                            $searchText = strtolower(
                                (string) ($record['student_name'] ?? '') . ' ' .
                                (string) ($record['student_email'] ?? '') . ' ' .
                                (string) ($record['event_title'] ?? '') . ' ' .
                                $modeLabel . ' ' . $methodLabel
                            );
                            ?>

                            <tr
                                class="attendance-row"
                                data-search="<?= e($searchText) ?>"
                            >
                                <td>
                                    <span class="attendance-student">
                                        <?= e($record['student_name'] ?? 'Unknown Student') ?>
                                    </span>

                                    <span class="attendance-email">
                                        <?= e($record['student_email'] ?? '') ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="attendance-event">
                                        <?= e($record['event_title'] ?? 'Unknown Event') ?>
                                    </span>

                                    <span class="attendance-venue">
                                        <?= e($record['venue'] ?? 'Venue not specified') ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="attendance-date">
                                        <?= $attendedTimestamp !== false
                                            ? e(date('M d, Y · h:i A', $attendedTimestamp))
                                            : 'Not available' ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="attendance-badge <?= e($modeClass) ?>">
                                        <?= e($modeLabel) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="attendance-badge <?= e($methodClass) ?>">
                                        <?= e($methodLabel) ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        <tr id="attendanceNoResults" hidden>
                            <td colspan="5" class="attendance-empty">
                                <strong>No matching records</strong>
                                Try another student name, email, event, or method.
                            </td>
                        </tr>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="attendance-empty">
                                <strong>No attendance records yet</strong>
                                Student attendance confirmations will appear here.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <div class="attendance-footer">
                Showing <strong id="attendanceVisibleCount"><?= count($attendanceRecords) ?></strong>
                of <?= count($attendanceRecords) ?> attendance records.
            </div>

        </section>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.getElementById('attendanceSearch');
    const rows = Array.from(
        document.querySelectorAll('.attendance-row')
    );

    const countLabel = document.getElementById('attendanceVisibleCount');
    const noResults = document.getElementById('attendanceNoResults');

    if (!searchInput) {
        return;
    }

    searchInput.addEventListener('input', function () {

        const keyword = this.value.trim().toLowerCase();
        let visible = 0;

        rows.forEach(function (row) {

            const searchText = row.dataset.search || '';
            const matches = searchText.includes(keyword);

            row.hidden = !matches;

            if (matches) {
                visible++;
            }
        });

        if (countLabel) {
            countLabel.textContent = visible;
        }

        if (noResults) {
            noResults.hidden = visible !== 0 || rows.length === 0;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>