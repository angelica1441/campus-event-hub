<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Event History';
$basePath = app_url();

$search = trim((string) ($_GET['search'] ?? ''));
$filter = strtolower(trim((string) ($_GET['status'] ?? 'all')));

$allowedFilters = ['all', 'completed', 'cancelled'];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$events = [];
$pageError = '';

try {
    /*
     * Event history includes:
     * 1. Cancelled or canceled events.
     * 2. Completed or ended events.
     * 3. Events whose date has already passed.
     *
     * Upcoming and ongoing future events are excluded.
     */
    $sql = "
        SELECT
            e.*,
            (
                SELECT COUNT(*)
                FROM registrations r
                WHERE r.event_id = e.id
                  AND LOWER(COALESCE(r.status, '')) = 'registered'
            ) AS registered_count
        FROM events e
        WHERE (
            LOWER(COALESCE(e.status, '')) IN (
                'cancelled',
                'canceled',
                'completed',
                'ended'
            )
            OR e.event_date < CURDATE()
        )
    ";

    $params = [];

    if ($filter === 'completed') {
        $sql .= "
            AND LOWER(COALESCE(e.status, '')) NOT IN (
                'cancelled',
                'canceled'
            )
            AND (
                LOWER(COALESCE(e.status, '')) IN ('completed', 'ended')
                OR e.event_date < CURDATE()
            )
        ";
    } elseif ($filter === 'cancelled') {
        $sql .= "
            AND LOWER(COALESCE(e.status, '')) IN (
                'cancelled',
                'canceled'
            )
        ";
    }

    if ($search !== '') {
        $sql .= "
            AND (
                e.title LIKE :search_title
                OR e.category LIKE :search_category
                OR e.venue LIKE :search_venue
                OR e.status LIKE :search_status
            )
        ";

        $keyword = '%' . $search . '%';

        $params['search_title'] = $keyword;
        $params['search_category'] = $keyword;
        $params['search_venue'] = $keyword;
        $params['search_status'] = $keyword;
    }

    $sql .= " ORDER BY e.event_date DESC, e.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {
    error_log('Event history database error: ' . $exception->getMessage());

    $pageError = 'Unable to load event history. Please check your database tables and columns.';
}

$totalHistory = count($events);
$completedCount = 0;
$cancelledCount = 0;

foreach ($events as $historyEvent) {
    $historyStatus = strtolower(
        trim((string) ($historyEvent['status'] ?? ''))
    );

    if (in_array($historyStatus, ['cancelled', 'canceled'], true)) {
        $cancelledCount++;
    } else {
        $completedCount++;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>

/* =====================================================
   PTC CAMPUS EVENT HUB
   EVENT HISTORY - DARK GLASSMORPHISM DESIGN
===================================================== */

.event-history-page {
    --history-green: #c7e78b;
    --history-mid: #8eaf75;
    --history-lime: #d9f2b2;
    --history-muted: #bdcbbf;
    --history-glass: rgba(255, 255, 255, 0.075);
    --history-border: rgba(255, 255, 255, 0.16);

    color: #f4f7ef !important;
    padding: 35px 0 55px;
    background:
        radial-gradient(ellipse at 5% 10%, rgba(100, 148, 103, 0.22), transparent 40%),
        radial-gradient(ellipse at 95% 85%, rgba(199, 231, 139, 0.10), transparent 40%),
        linear-gradient(135deg, #102e23, #183f30 55%, #102b21) !important;
}

.event-history-page *,
.event-history-page *::before,
.event-history-page *::after {
    box-sizing: border-box;
}

/* HERO */
.event-history-page .history-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    margin-bottom: 25px;
    padding: 38px 32px;
    border: 1px solid rgba(255,255,255,.17);
    border-radius: 22px;
    color: #fff;
    background:
        radial-gradient(circle at 90% 10%, rgba(199,231,139,.18), transparent 35%),
        linear-gradient(125deg, rgba(255,255,255,.10), rgba(255,255,255,.035)) !important;
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    box-shadow: 0 15px 35px rgba(0,0,0,.16);
}

.event-history-page .history-hero::after {
    content: "";
    position: absolute;
    z-index: -1;
    width: 220px;
    height: 220px;
    right: -65px;
    bottom: -140px;
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 50%;
    box-shadow:
        0 0 0 25px rgba(255,255,255,.035),
        0 0 0 50px rgba(255,255,255,.025);
}

.event-history-page .history-kicker {
    display: inline-block;
    margin-bottom: 14px;
    padding: 7px 12px;
    border: 1px solid rgba(199,231,139,.3);
    border-radius: 30px;
    background: rgba(199,231,139,.12);
    color: #d5f3a7;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: 1.4px;
}

.event-history-page .history-hero h1 {
    margin: 0 0 10px;
    color: #fff !important;
    font-size: clamp(1.8rem, 4vw, 2.5rem);
    font-weight: 850;
    letter-spacing: -.8px;
}

.event-history-page .history-hero p {
    max-width: 600px;
    margin: 0;
    color: rgba(255,255,255,.78) !important;
    font-size: .92rem;
    line-height: 1.8;
}

/* SUMMARY CARDS */
.event-history-page .history-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}

.event-history-page .history-stat {
    display: flex;
    align-items: center;
    gap: 15px;
    min-width: 0;
    padding: 22px;
    border: 1px solid var(--history-border) !important;
    border-radius: 18px;
    background: var(--history-glass) !important;
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    box-shadow: 0 10px 30px rgba(0,0,0,.15);
    transition: .3s ease;
}

.event-history-page .history-stat:hover {
    transform: translateY(-4px);
    background: rgba(255,255,255,.12) !important;
    border-color: rgba(199,231,139,.4) !important;
}

.event-history-page .history-stat-icon {
    display: grid;
    width: 49px;
    height: 49px;
    flex: 0 0 49px;
    place-items: center;
    border-radius: 15px;
    background: rgba(199,231,139,.14) !important;
    border: 1px solid rgba(199,231,139,.22);
    color: #d9f2b2 !important;
    font-size: 1.35rem;
    font-weight: 800;
}

.event-history-page .history-stat:nth-child(2) .history-stat-icon {
    background: rgba(104,190,126,.15) !important;
    color: #a9edc2 !important;
}

.event-history-page .history-stat:nth-child(3) .history-stat-icon {
    background: rgba(232,113,100,.14) !important;
    color: #ffaaa0 !important;
}

.event-history-page .history-stat-label {
    display: block;
    color: #c2d0c3 !important;
    font-size: .76rem;
    font-weight: 700;
}

.event-history-page .history-stat-number {
    display: block;
    margin-top: 4px;
    color: #ffffff !important;
    font-size: 1.8rem;
    font-weight: 850;
    line-height: 1.2;
}

/* HISTORY MAIN PANEL */
.event-history-page .history-panel {
    overflow: hidden;
    border: 1px solid var(--history-border) !important;
    border-radius: 21px;
    background: rgba(255,255,255,.065) !important;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-shadow: 0 15px 40px rgba(0,0,0,.18);
}

/* PANEL HEADER */
.event-history-page .history-panel-header {
    padding: 25px;
    border-bottom: 1px solid rgba(255,255,255,.12) !important;
    background: rgba(255,255,255,.035) !important;
}

.event-history-page .history-panel-header h2 {
    margin: 0;
    color: #ffffff !important;
    font-size: 1.35rem;
    font-weight: 850;
}

.event-history-page .history-panel-header p {
    margin: 7px 0 0;
    color: #bdcbbf !important;
    font-size: .84rem;
    line-height: 1.7;
}

/* TOOLBAR */
.event-history-page .history-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    padding: 18px 25px;
    border-bottom: 1px solid rgba(255,255,255,.12) !important;
    background: rgba(255,255,255,.035) !important;
}

/* FILTER BUTTONS */
.event-history-page .history-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.event-history-page .history-filter {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 37px;
    padding: 8px 14px;
    border: 1px solid rgba(255,255,255,.17) !important;
    border-radius: 10px;
    background: rgba(255,255,255,.07) !important;
    color: #d0ddcf !important;
    font-size: .77rem;
    font-weight: 750;
    text-decoration: none !important;
    transition: .2s ease;
}

.event-history-page .history-filter:hover {
    border-color: rgba(199,231,139,.45) !important;
    background: rgba(199,231,139,.12) !important;
    color: #e3f5ce !important;
}

.event-history-page .history-filter.active {
    border-color: #c7e78b !important;
    background: rgba(199,231,139,.20) !important;
    color: #e5f5cc !important;
    box-shadow: 0 3px 12px rgba(0,0,0,.12);
}

/* SEARCH */
.event-history-page .history-search {
    display: flex;
    align-items: center;
    gap: 7px;
    width: min(100%, 340px);
}

.event-history-page .history-search input {
    width: 100%;
    min-width: 0;
    min-height: 40px;
    padding: 9px 13px;
    border: 1px solid rgba(255,255,255,.2) !important;
    border-radius: 10px;
    outline: none;
    background: rgba(255,255,255,.08) !important;
    color: #ffffff !important;
    font-size: .82rem;
}

.event-history-page .history-search input::placeholder {
    color: #bdcbbf !important;
}

.event-history-page .history-search input:focus {
    border-color: #c7e78b !important;
    box-shadow: 0 0 0 3px rgba(199,231,139,.13);
}

.event-history-page .history-search button {
    min-height: 40px;
    padding: 8px 15px;
    border: 1px solid rgba(199,231,139,.3) !important;
    border-radius: 10px;
    background: #c7e78b !important;
    color: #173b29 !important;
    font-size: .8rem;
    font-weight: 800;
    transition: .2s ease;
}

.event-history-page .history-search button:hover {
    background: #ddf3b4 !important;
}

/* TABLE RESPONSIVE WRAPPER */
.event-history-page .table-responsive {
    background: transparent !important;
}

/* TABLE */
.event-history-page .history-table {
    width: 100%;
    min-width: 760px;
    margin: 0;
    color: #eaf1e8 !important;
    background: transparent !important;
    --bs-table-bg: transparent;
    --bs-table-color: #eaf1e8;
    --bs-table-hover-bg: rgba(255,255,255,.045);
    --bs-table-hover-color: #ffffff;
}

/* TABLE HEADER */
.event-history-page .history-table thead th {
    padding: 16px 18px;
    border-bottom: 1px solid rgba(255,255,255,.13) !important;
    background: rgba(199,231,139,.09) !important;
    color: #c9dbb9 !important;
    font-size: .69rem;
    font-weight: 850;
    letter-spacing: .65px;
    text-transform: uppercase;
    white-space: nowrap;
}

/* TABLE BODY */
.event-history-page .history-table tbody td {
    padding: 19px 18px;
    border-bottom: 1px solid rgba(255,255,255,.09) !important;
    background: transparent !important;
    color: #cbd8cd !important;
    font-size: .81rem;
    vertical-align: middle;
}

.event-history-page .history-table tbody tr:last-child td {
    border-bottom: 0 !important;
}

.event-history-page .history-table tbody tr {
    background: transparent !important;
    transition: .2s ease;
}

.event-history-page .history-table tbody tr:hover {
    background: rgba(255,255,255,.055) !important;
}

/* EVENT DETAILS */
.event-history-page .history-event-name {
    display: block;
    max-width: 260px;
    color: #ffffff !important;
    font-size: .86rem;
    font-weight: 800;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.event-history-page .history-event-venue {
    display: block;
    margin-top: 4px;
    color: #b2c4b5 !important;
    font-size: .74rem;
    overflow-wrap: anywhere;
}

.event-history-page .history-date {
    color: #e1edda !important;
    font-weight: 750;
    white-space: nowrap;
}

/* CATEGORY */
.event-history-page .history-category {
    display: inline-block;
    padding: 6px 10px;
    border: 1px solid rgba(199,231,139,.18);
    border-radius: 8px;
    background: rgba(199,231,139,.11) !important;
    color: #d9edbd !important;
    font-size: .73rem;
    font-weight: 700;
}

/* STATUS BADGES */
.event-history-page .history-status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 11px;
    border-radius: 30px;
    font-size: .7rem;
    font-weight: 800;
    white-space: nowrap;
}

.event-history-page .history-status::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.event-history-page .history-status.completed {
    border: 1px solid rgba(113,208,133,.25);
    background: rgba(113,208,133,.13) !important;
    color: #a9edb7 !important;
}

.event-history-page .history-status.cancelled {
    border: 1px solid rgba(255,130,119,.25);
    background: rgba(255,130,119,.13) !important;
    color: #ffaaa0 !important;
}

.event-history-page .history-status.ended,
.event-history-page .history-status.other {
    border: 1px solid rgba(255,255,255,.15);
    background: rgba(255,255,255,.08) !important;
    color: #c8d5ca !important;
}

/* ATTENDEES */
.event-history-page .history-attendees {
    color: #d9edbd !important;
    font-weight: 800;
    white-space: nowrap;
}

/* EMPTY STATE */
.event-history-page .history-empty {
    padding: 55px 20px !important;
    text-align: center;
    background: transparent !important;
}

.event-history-page .history-empty-icon {
    display: grid;
    width: 62px;
    height: 62px;
    margin: 0 auto 15px;
    place-items: center;
    border: 1px solid rgba(199,231,139,.2);
    border-radius: 19px;
    background: rgba(199,231,139,.12) !important;
    color: #d9f2b2 !important;
    font-size: 1.65rem;
}

.event-history-page .history-empty h3 {
    margin: 0 0 8px;
    color: #ffffff !important;
    font-size: 1.08rem;
    font-weight: 850;
}

.event-history-page .history-empty p {
    margin: 0;
    color: #bdcbbf !important;
    font-size: .83rem;
}

/* FOOTER */
.event-history-page .history-footer {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 18px 25px;
    border-top: 1px solid rgba(255,255,255,.12) !important;
    background: rgba(255,255,255,.04) !important;
    color: #bdcbbf !important;
    font-size: .77rem;
}

.event-history-page .history-footer a {
    color: #d9edbd !important;
    font-weight: 750;
    text-decoration: none;
}

.event-history-page .history-footer a:hover {
    color: #ffffff !important;
    text-decoration: underline;
}

/* ALERT */
.event-history-page .history-alert {
    border-radius: 12px;
}

/* =========================================
   PTC SAGE GREEN BORDER ENHANCEMENT
========================================= */

/* SUMMARY BOXES */
.event-history-page .history-stat {
    border: 1px solid rgba(174, 204, 151, 0.42) !important;
    box-shadow:
        0 8px 25px rgba(0, 0, 0, 0.15),
        inset 0 1px 0 rgba(225, 244, 207, 0.10) !important;
}

/* MAIN HISTORY PANEL */
.event-history-page .history-panel {
    border: 1px solid rgba(174, 204, 151, 0.48) !important;
    box-shadow:
        0 15px 40px rgba(0, 0, 0, 0.18),
        0 0 18px rgba(199, 231, 139, 0.045),
        inset 0 1px 0 rgba(225, 244, 207, 0.08) !important;
}

/* HERO BORDER */
.event-history-page .history-hero {
    border: 1px solid rgba(199, 231, 139, 0.42) !important;
    box-shadow:
        0 12px 30px rgba(0, 0, 0, 0.16),
        inset 0 1px 0 rgba(255, 255, 255, 0.12) !important;
}

/* TABLE ROW DIVIDERS */
.event-history-page .history-table tbody td {
    border-bottom: 1px solid rgba(174, 204, 151, 0.18) !important;
}

/* TABLE HEADER BORDER */
.event-history-page .history-table thead th {
    border-bottom: 1px solid rgba(199, 231, 139, 0.35) !important;
}

/* FILTER BUTTON BORDERS */
.event-history-page .history-filter {
    border: 1px solid rgba(174, 204, 151, 0.35) !important;
}

.event-history-page .history-filter.active {
    border: 1px solid #c7e78b !important;
    box-shadow: 0 0 12px rgba(199, 231, 139, 0.12);
}

/* SEARCH BORDER */
.event-history-page .history-search input {
    border: 1px solid rgba(174, 204, 151, 0.42) !important;
}

/* CATEGORY BORDER */
.event-history-page .history-category {
    border: 1px solid rgba(199, 231, 139, 0.30) !important;
}

/* HOVER EFFECT */
.event-history-page .history-stat:hover {
    border-color: #c7e78b !important;
    box-shadow:
        0 12px 28px rgba(0, 0, 0, 0.18),
        0 0 15px rgba(199, 231, 139, 0.10) !important;
}

.event-history-page .history-panel:hover {
    border-color: rgba(199, 231, 139, 0.60) !important;
}

/* FOOTER BORDER */
.event-history-page .history-footer {
    border-top: 1px solid rgba(174, 204, 151, 0.25) !important;
}

/* RESPONSIVE */
@media (max-width: 760px) {
    .event-history-page .history-summary {
        grid-template-columns: 1fr;
        gap: 11px;
    }

    .event-history-page .history-stat {
        padding: 17px;
    }

    .event-history-page .history-stat-number {
        font-size: 1.55rem;
    }

    .event-history-page .history-hero {
        padding: 27px 21px;
        border-radius: 18px;
    }

    .event-history-page .history-panel-header {
        padding: 21px 17px;
    }

    .event-history-page .history-toolbar {
        align-items: stretch;
        padding: 15px 17px;
    }

    .event-history-page .history-filters {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .event-history-page .history-filter {
        padding: 8px 5px;
        font-size: .72rem;
    }

    .event-history-page .history-search {
        width: 100%;
    }

    .event-history-page .history-table thead th,
    .event-history-page .history-table tbody td {
        padding: 14px 12px;
    }

    .event-history-page .history-footer {
        padding: 15px 17px;
    }
}


@media (max-width: 760px) {
    .event-history-page .history-summary {
        grid-template-columns: 1fr;
        gap: 11px;
    }

    .event-history-page .history-stat {
        padding: 16px;
    }

    .event-history-page .history-stat-number {
        font-size: 1.55rem;
    }

    .event-history-page .history-hero {
        padding: 27px 21px;
        border-radius: 18px;
    }

    .event-history-page .history-panel-header {
        padding: 21px 17px;
    }

    .event-history-page .history-toolbar {
        align-items: stretch;
        padding: 15px 17px;
    }

    .event-history-page .history-filters {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .event-history-page .history-filter {
        padding: 8px 5px;
        font-size: .72rem;
    }

    .event-history-page .history-search {
        width: 100%;
    }

    .event-history-page .history-table thead th,
    .event-history-page .history-table tbody td {
        padding: 14px 12px;
    }

    .event-history-page .history-footer {
        padding: 15px 17px;
    }
}
</style>

<main class="event-history-page">
    <div class="container">

        <section class="history-hero">
            <span class="history-kicker">ADMINISTRATION / ARCHIVE</span>

            <h1>Event History</h1>

            <p>
                Review completed events, cancelled activities, and past campus
                events. This page keeps a record of activities that are no
                longer active.
            </p>
        </section>

        <?php if ($pageError !== ''): ?>
            <div class="alert alert-danger history-alert" role="alert">
                <?= e($pageError) ?>
            </div>
        <?php endif; ?>

        <section class="history-summary" aria-label="Event history summary">

            <article class="history-stat">
                <div class="history-stat-icon" aria-hidden="true">▤</div>
                <div>
                    <span class="history-stat-label">Total History Records</span>
                    <strong class="history-stat-number">
                        <?= number_format($totalHistory) ?>
                    </strong>
                </div>
            </article>

            <article class="history-stat">
                <div class="history-stat-icon" aria-hidden="true">✓</div>
                <div>
                    <span class="history-stat-label">Completed / Past Events</span>
                    <strong class="history-stat-number">
                        <?= number_format($completedCount) ?>
                    </strong>
                </div>
            </article>

            <article class="history-stat">
                <div class="history-stat-icon" aria-hidden="true">×</div>
                <div>
                    <span class="history-stat-label">Cancelled Events</span>
                    <strong class="history-stat-number">
                        <?= number_format($cancelledCount) ?>
                    </strong>
                </div>
            </article>

        </section>

        <section class="history-panel">

            <header class="history-panel-header">
                <h2>Past Event Records</h2>
                <p>
                    Event details, schedule, category, attendance count, and final status.
                </p>
            </header>

            <div class="history-toolbar">

                <nav class="history-filters" aria-label="Filter event history">
                    <a
                        class="history-filter <?= $filter === 'all' ? 'active' : '' ?>"
                        href="event-history.php?status=all"
                    >
                        All Records
                    </a>

                    <a
                        class="history-filter <?= $filter === 'completed' ? 'active' : '' ?>"
                        href="event-history.php?status=completed"
                    >
                        Completed
                    </a>

                    <a
                        class="history-filter <?= $filter === 'cancelled' ? 'active' : '' ?>"
                        href="event-history.php?status=cancelled"
                    >
                        Cancelled
                    </a>
                </nav>

                <form class="history-search" method="get" action="event-history.php">
                    <input
                        type="hidden"
                        name="status"
                        value="<?= e($filter) ?>"
                    >

                    <input
                        type="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Search past events..."
                        aria-label="Search event history"
                    >

                    <button type="submit">Search</button>
                </form>

            </div>

            <div class="table-responsive">
                <table class="table history-table align-middle">

                    <thead>
                        <tr>
                            <th>Event Details</th>
                            <th>Event Date</th>
                            <th>Category</th>
                            <th>Registrations</th>
                            <th>Final Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!empty($events)): ?>

                        <?php foreach ($events as $event): ?>

                            <?php
                            $rawStatus = strtolower(
                                trim((string) ($event['status'] ?? ''))
                            );

                            $isCancelled = in_array(
                                $rawStatus,
                                ['cancelled', 'canceled'],
                                true
                            );

                            $eventDate = (string) ($event['event_date'] ?? '');
                            $dateTimestamp = $eventDate !== ''
                                ? strtotime($eventDate)
                                : false;

                            if ($isCancelled) {
                                $displayStatus = 'Cancelled';
                                $displayClass = 'cancelled';
                            } elseif (in_array($rawStatus, ['completed', 'ended'], true)) {
                                $displayStatus = 'Completed';
                                $displayClass = 'completed';
                            } elseif (
                                $dateTimestamp !== false &&
                                $dateTimestamp < strtotime(date('Y-m-d'))
                            ) {
                                $displayStatus = 'Past Event';
                                $displayClass = 'ended';
                            } else {
                                $displayStatus = ucfirst($rawStatus ?: 'Inactive');
                                $displayClass = 'other';
                            }

                            $eventTitle = (string) ($event['title'] ?? 'Untitled Event');
                            $venue = (string) ($event['venue'] ?? 'Venue not specified');
                            $category = (string) ($event['category'] ?? 'Uncategorized');
                            $attendees = (int) ($event['registered_count'] ?? 0);
                            ?>

                            <tr>
                                <td>
                                    <span class="history-event-name">
                                        <?= e($eventTitle) ?>
                                    </span>

                                    <span class="history-event-venue">
                                        <?= e($venue) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="history-date">
                                        <?php if ($dateTimestamp !== false): ?>
                                            <?= e(date('M d, Y', $dateTimestamp)) ?>
                                        <?php else: ?>
                                            Not specified
                                        <?php endif; ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="history-category">
                                        <?= e($category) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="history-attendees">
                                        <?= number_format($attendees) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="history-status <?= e($displayClass) ?>">
                                        <?= e($displayStatus) ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="history-empty">
                                <div class="history-empty-icon" aria-hidden="true">▤</div>

                                <h3>No History Records Found</h3>

                                <p>
                                    <?php if ($search !== ''): ?>
                                        No past events match your search. Try another keyword.
                                    <?php else: ?>
                                        Completed, cancelled, and past events will appear here.
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>
            </div>

            <footer class="history-footer">
                <span>
                    Displaying <?= number_format($totalHistory) ?> history record(s)
                </span>

                <a href="dashboard.php">← Back to Dashboard</a>
            </footer>

        </section>

    </div>
</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>