<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Registrations';
$basePath = app_url();

// Event ID is optional
$id = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    $id = null;
}

$event = null;
$attendees = [];
$active = 0;
$cancelled = 0;
$total = 0;
$remaining = 0;
$capacity = 0;
$errors = [];

$search = trim($_GET['search'] ?? '');
$statusFilter = strtolower(trim($_GET['status'] ?? ''));

try {

    // Get selected event if event_id is provided
    if ($id !== null) {
        $eventStmt = $pdo->prepare(
            'SELECT * FROM events WHERE id = ?'
        );

        $eventStmt->execute([$id]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            set_flash('danger', 'Event not found.');
            redirect('admin/dashboard.php');
        }

        $capacity = max(0, (int) ($event['capacity'] ?? 0));
    }

    // Get registration summary
    $summarySql = "
        SELECT
            COUNT(*) AS total,
            SUM(
                CASE
                    WHEN LOWER(TRIM(COALESCE(status, ''))) = 'registered'
                    THEN 1 ELSE 0
                END
            ) AS active,
            SUM(
                CASE
                    WHEN LOWER(TRIM(COALESCE(status, ''))) = 'cancelled'
                    THEN 1 ELSE 0
                END
            ) AS cancelled
        FROM registrations
    ";

    $summaryParams = [];

    if ($id !== null) {
        $summarySql .= " WHERE event_id = ?";
        $summaryParams[] = $id;
    }

    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute($summaryParams);

    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    $total = (int) ($summary['total'] ?? 0);
    $active = (int) ($summary['active'] ?? 0);
    $cancelled = (int) ($summary['cancelled'] ?? 0);

    if ($id !== null) {
        $remaining = max(0, $capacity - $active);
    }

    // Get all registrations and student information
    $sql = "
        SELECT
            r.id,
            r.event_id,
            r.user_id,
            r.status,
            r.registered_at,
            u.full_name,
            u.username,
            u.email,
            e.title AS event_title,
            e.event_date
        FROM registrations AS r
        LEFT JOIN users AS u ON u.id = r.user_id
        LEFT JOIN events AS e ON e.id = r.event_id
        WHERE 1 = 1
    ";

    $params = [];

    // Filter by event when viewing individual attendees
    if ($id !== null) {
        $sql .= " AND r.event_id = ?";
        $params[] = $id;
    }

    // Search students and event titles
    if ($search !== '') {
        $sql .= "
            AND (
                u.full_name LIKE ?
                OR u.username LIKE ?
                OR u.email LIKE ?
                OR e.title LIKE ?
            )
        ";

        $keyword = '%' . $search . '%';

        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
    }

    // Filter by status
    if (in_array($statusFilter, ['registered', 'cancelled', 'pending'], true)) {
        $sql .= " AND LOWER(TRIM(COALESCE(r.status, ''))) = ?";
        $params[] = $statusFilter;
    }

    $sql .= " ORDER BY r.registered_at DESC, r.id DESC";

    $attendeeStmt = $pdo->prepare($sql);
    $attendeeStmt->execute($params);

    $attendees = $attendeeStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Registration page error: ' . $e->getMessage());

    $errors[] = 'Unable to load registrations. Please check your database tables and columns.';
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-hero registrations-hero">
    <div class="container">

        <a href="dashboard.php" class="back-link">
            &larr; Back to Dashboard
        </a>

        <h1 class="mt-3">
            <?= $id !== null ? 'Event Attendees' : 'All Registrations' ?>
        </h1>

        <?php if ($event): ?>
            <p>
                <?= e($event['title']) ?>
                &middot;
                <?= e(format_date($event['event_date'])) ?>
            </p>
        <?php else: ?>
            <p>
                View and manage all student registrations across campus events.
            </p>
        <?php endif; ?>

    </div>
</section>

<section class="section-space registrations-page">
    <div class="container">

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-0"><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- REGISTRATION SUMMARY -->
        <div class="admin-stat-grid">

            <div class="admin-stat">
                <span>Total Registrations</span>
                <strong><?= $total ?></strong>
                <small>All registration records</small>
            </div>

            <div class="admin-stat">
                <span>Active Registrations</span>
                <strong><?= $active ?></strong>
                <small>Currently registered</small>
            </div>

            <div class="admin-stat">
                <span>Cancelled Registrations</span>
                <strong><?= $cancelled ?></strong>
                <small>Cancelled records</small>
            </div>

        </div>

        <?php if ($id !== null): ?>
            <div class="admin-stat-grid capacity-grid">
                <div class="admin-stat">
                    <span>Total Capacity</span>
                    <strong><?= $capacity ?></strong>
                    <small>Maximum attendees</small>
                </div>

                <div class="admin-stat">
                    <span>Remaining Slots</span>
                    <strong><?= $remaining ?></strong>
                    <small>Available spaces</small>
                </div>
            </div>
        <?php endif; ?>

        <!-- HEADING -->
        <div class="section-heading admin-heading">
            <div>
                <span class="eyebrow eyebrow-dark">
                    REGISTRATION MANAGEMENT
                </span>

                <h2>
                    <?= $id !== null ? 'Registered Students' : 'All Student Registrations' ?>
                </h2>

                <p>
                    View student details, event information, and registration status.
                </p>
            </div>

            <button
                class="btn btn-outline-green no-print"
                type="button"
                onclick="window.print()"
            >
                Print Registration List
            </button>
        </div>

        <!-- SEARCH AND FILTER -->
        <form method="GET" class="registration-filter no-print">

            <?php if ($id !== null): ?>
                <input type="hidden" name="event_id" value="<?= (int) $id ?>">
            <?php endif; ?>

            <div class="registration-search">
                <input
                    type="text"
                    name="search"
                    placeholder="Search student or event..."
                    value="<?= e($search) ?>"
                >

                <button type="submit" class="btn-filter">
                    Search
                </button>
            </div>

            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>

                <option value="registered"
                    <?= $statusFilter === 'registered' ? 'selected' : '' ?>>
                    Registered
                </option>

                <option value="cancelled"
                    <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>
                    Cancelled
                </option>

                <option value="pending"
                    <?= $statusFilter === 'pending' ? 'selected' : '' ?>>
                    Pending
                </option>
            </select>

            <a
                href="<?= $id !== null
                    ? 'Registration.php?event_id=' . (int) $id
                    : 'Registration.php' ?>"
                class="btn-reset"
            >
                Reset
            </a>

        </form>

        <!-- REGISTRATION TABLE -->
        <div class="table-responsive registrations-table-wrap">

            <table class="table registrations-table align-middle">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Username</th>
                        <th>Email</th>

                        <?php if ($id === null): ?>
                            <th>Event</th>
                        <?php endif; ?>

                        <th>Registration Date</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!empty($attendees)): ?>

                        <?php foreach ($attendees as $index => $attendee): ?>

                            <?php
                            $status = strtolower(
                                trim($attendee['status'] ?? 'unknown')
                            );

                            $statusClass = preg_replace(
                                '/[^a-z0-9_-]/i',
                                '',
                                $status
                            );

                            $registeredAt = $attendee['registered_at'] ?? '';

                            $timestamp = $registeredAt !== ''
                                ? strtotime($registeredAt)
                                : false;
                            ?>

                            <tr>
                                <td><?= $index + 1 ?></td>

                                <td>
                                    <strong>
                                        <?= e($attendee['full_name'] ?? 'Unknown Student') ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e($attendee['username'] ?? '—') ?>
                                </td>

                                <td>
                                    <?= e($attendee['email'] ?? '—') ?>
                                </td>

                                <?php if ($id === null): ?>
                                    <td>
                                        <strong>
                                            <?= e($attendee['event_title'] ?? 'Unknown Event') ?>
                                        </strong>
                                    </td>
                                <?php endif; ?>

                                <td>
                                    <?= $timestamp
                                        ? e(date('M j, Y g:i A', $timestamp))
                                        : '—' ?>
                                </td>

                                <td>
                                    <span class="status-pill status-<?= e($statusClass) ?>">
                                        <?= e(ucfirst($status)) ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td
                                colspan="<?= $id !== null ? 6 : 7 ?>"
                                class="text-center py-5"
                            >
                                <strong>No registrations found.</strong>

                                <p class="text-muted mb-0 mt-2">
                                    Try changing your search or status filter.
                                </p>
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>
            </table>
        </div>

        <div class="mt-3 no-print">
            <a href="dashboard.php" class="btn btn-light">
                Back to Dashboard
            </a>
        </div>

    </div>
</section>
<style>
/* =========================================
   PTC CAMPUS EVENT HUB
   EVENT REGISTRATIONS - DARK GLASS THEME
========================================= */

/* REGISTRATION SEARCH AND FILTER */
.registration-filter {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
    padding: 16px;
    border: 1px solid rgba(174,204,151,.35);
    border-radius: 15px;
    background: rgba(15,53,36,.65);
}

.registration-search {
    display: flex;
    flex: 1;
    min-width: 230px;
    gap: 8px;
}

.registration-filter input,
.registration-filter select {
    min-height: 43px;
    padding: 9px 13px;
    border: 1px solid rgba(174,204,151,.45);
    border-radius: 9px;
    outline: none;
    background: #102f22;
    color: #e5f4dc;
}

.registration-search input {
    flex: 1;
    min-width: 0;
}

.registration-filter input::placeholder {
    color: #a9bfae;
}

.registration-filter select {
    min-width: 150px;
}

.btn-filter,
.btn-reset {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 43px;
    padding: 9px 16px;
    border: 1px solid rgba(199,231,139,.5);
    border-radius: 9px;
    background: rgba(199,231,139,.12);
    color: #d7edc3;
    font-weight: 750;
    text-decoration: none;
}

.btn-filter:hover,
.btn-reset:hover {
    background: rgba(199,231,139,.24);
    color: #fff;
}

@media (max-width: 700px) {
    .registration-filter {
        align-items: stretch;
        flex-direction: column;
    }

    .registration-search {
        min-width: 0;
    }

    .registration-filter select {
        width: 100%;
    }
}

.registrations-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    padding: 65px 0;
    border: 1px solid rgba(199,231,139,.48) !important;
    border-radius: 0 0 22px 22px;
    background:
        radial-gradient(circle at 90% 10%, rgba(199,231,139,.16), transparent 30%),
        linear-gradient(125deg, #091d16, #123b2b 65%, #1b5038) !important;
    color: #edf5e9 !important;
    box-shadow: 0 12px 30px rgba(0,0,0,.18);
}

.registrations-hero h1 {
    color: #fff !important;
    font-weight: 850;
}

.registrations-hero p {
    color: #c5d9c8 !important;
}

.registrations-hero .back-link {
    color: #c7e78b !important;
    font-weight: 700;
    text-decoration: none;
}

.registrations-hero .back-link:hover {
    color: #e2f5c9 !important;
}

.registrations-page {
    min-height: 60vh;
    padding-top: 35px;
    padding-bottom: 50px;
    color: #edf5e9 !important;
}

/* STATISTICS */
.registrations-page .admin-stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
    margin-bottom: 32px;
}

.registrations-page .admin-stat {
    position: relative;
    overflow: hidden;
    padding: 23px;
    border: 1px solid rgba(174,204,151,.55) !important;
    border-radius: 18px;
    background: linear-gradient(145deg, rgba(27,75,51,.90), rgba(12,42,30,.95)) !important;
    color: #edf5e9 !important;
    box-shadow: 0 10px 25px rgba(0,0,0,.16);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    transition: .3s ease;
}

.registrations-page .admin-stat:nth-child(2) {
    border-color: rgba(130,190,210,.55) !important;
}

.registrations-page .admin-stat:nth-child(3) {
    border-color: rgba(220,190,125,.55) !important;
}

.registrations-page .admin-stat:hover {
    transform: translateY(-4px);
    border-color: #c7e78b !important;
    box-shadow: 0 12px 28px rgba(0,0,0,.2);
}

.registrations-page .admin-stat span {
    display: block;
    color: #b9d2b7 !important;
    font-size: .83rem;
    font-weight: 750;
}

.registrations-page .admin-stat strong {
    display: block;
    margin: 9px 0 5px;
    color: #d2f0a9 !important;
    font-size: 2.2rem;
    font-weight: 850;
}

.registrations-page .admin-stat small {
    color: #9eb5a1 !important;
    font-size: .75rem;
}

/* SECTION HEADING */
.registrations-page .admin-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    margin: 25px 0 20px;
}

.registrations-page .eyebrow-dark {
    color: #b9d98f !important;
    font-size: .7rem;
    font-weight: 850;
    letter-spacing: 1.4px;
}

.registrations-page .admin-heading h2 {
    margin: 7px 0;
    color: #e5f4dc !important;
    font-size: 1.5rem;
    font-weight: 850;
}

.registrations-page .admin-heading p {
    margin: 0;
    color: #a9bfae !important;
    font-size: .85rem;
}

/* BUTTONS */
.registrations-page .btn-outline-green {
    border: 1px solid rgba(199,231,139,.5) !important;
    border-radius: 10px;
    background: rgba(199,231,139,.09) !important;
    color: #d7edc3 !important;
    font-weight: 750;
    padding: 10px 16px;
    transition: .2s ease;
}

.registrations-page .btn-outline-green:hover {
    border-color: #c7e78b !important;
    background: rgba(199,231,139,.2) !important;
    color: #fff !important;
}

.registrations-page .btn-light {
    border: 1px solid rgba(174,204,151,.4) !important;
    border-radius: 10px;
    background: rgba(255,255,255,.06) !important;
    color: #dce8d1 !important;
}

.registrations-page .btn-light:hover {
    border-color: #c7e78b !important;
    background: rgba(199,231,139,.12) !important;
    color: #fff !important;
}

/* TABLE PANEL */
.registrations-page .registrations-table-wrap {
    overflow-x: auto;
    border: 1px solid rgba(174,204,151,.55) !important;
    border-radius: 18px;
    background: linear-gradient(145deg, rgba(20,61,42,.95), rgba(10,39,28,.98)) !important;
    box-shadow: 0 12px 30px rgba(0,0,0,.17);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
}

/* TABLE */
.registrations-page .registrations-table {
    width: 100%;
    min-width: 850px;
    margin: 0 !important;
    border-collapse: separate;
    border-spacing: 0;
    background: transparent !important;
    --bs-table-bg: transparent;
    --bs-table-color: #dce8d1;
    --bs-table-hover-bg: rgba(199,231,139,.06);
    --bs-table-hover-color: #fff;
    --bs-table-striped-bg: transparent;
    --bs-table-border-color: rgba(174,204,151,.20);
}

.registrations-page .registrations-table thead th {
    padding: 17px 16px !important;
    border-bottom: 1px solid rgba(199,231,139,.42) !important;
    background: rgba(4,27,18,.65) !important;
    color: #c7e78b !important;
    font-size: .72rem;
    font-weight: 850;
    letter-spacing: .6px;
    text-transform: uppercase;
    white-space: nowrap;
}

.registrations-page .registrations-table tbody td {
    padding: 17px 16px !important;
    border-bottom: 1px solid rgba(174,204,151,.20) !important;
    background: transparent !important;
    color: #dce8d1 !important;
    font-size: .82rem;
    vertical-align: middle;
}

.registrations-page .registrations-table tbody tr {
    background: transparent !important;
    transition: background .2s ease;
}

.registrations-page .registrations-table tbody tr:hover {
    background: rgba(199,231,139,.07) !important;
}

.registrations-page .registrations-table tbody tr:last-child td {
    border-bottom: none !important;
}

.registrations-page .registrations-table td strong {
    color: #e5f4dc !important;
    font-weight: 800;
}

/* STATUS */
.registrations-page .status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 11px;
    border: 1px solid rgba(150,220,120,.30);
    border-radius: 30px;
    background: rgba(90,160,65,.16);
    color: #b8ed9d !important;
    font-size: .72rem;
    font-weight: 800;
    white-space: nowrap;
}

.registrations-page .status-pill::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.registrations-page .status-cancelled,
.registrations-page .status-canceled {
    border-color: rgba(240,130,110,.3);
    background: rgba(180,65,50,.16);
    color: #ffb6a9 !important;
}

.registrations-page .status-pending {
    border-color: rgba(220,190,125,.35);
    background: rgba(190,150,65,.15);
    color: #f0d28c !important;
}

/* EMPTY STATE AND ERROR */
.registrations-page .registrations-table .text-center {
    padding: 45px 20px !important;
    color: #e5f4dc !important;
}

.registrations-page .registrations-table .text-muted {
    color: #a9bfae !important;
}

.registrations-page .alert-danger {
    border: 1px solid rgba(240,130,110,.4);
    border-radius: 12px;
    background: rgba(120,35,30,.25);
    color: #ffd5ce;
}

/* MOBILE */
@media (max-width: 700px) {
    .registrations-page .admin-stat-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .registrations-page .admin-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .registrations-page .admin-stat {
        padding: 18px;
    }

    .registrations-hero {
        border-radius: 0 0 17px 17px;
    }
}

/* PRINT */
@media print {
    .navbar,
    footer,
    .page-hero .back-link,
    .no-print,
    .form-actions {
        display: none !important;
    }

    body {
        background: #fff !important;
        color: #000 !important;
    }

    .page-hero,
    .section-space {
        padding: 12px 0 !important;
        background: #fff !important;
        color: #000 !important;
    }

    .container {
        width: 100% !important;
        max-width: 100% !important;
    }

    .registrations-table-wrap {
        overflow: visible !important;
        border: none !important;
        background: #fff !important;
        box-shadow: none !important;
    }

    .registrations-table {
        width: 100% !important;
        min-width: 0 !important;
        font-size: 11px !important;
    }

    .registrations-table th,
    .registrations-table td {
        border: 1px solid #ccc !important;
        background: #fff !important;
        color: #000 !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>