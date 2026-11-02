<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Admin Dashboard';
$basePath = app_url();

$stats = [
    'active_events' => 0,
    'total_registrations' => 0,
    'total_students' => 0,
];

$events = [];
$recentRegistrations = [];
$pageError = '';

$adminName = $_SESSION['user']['full_name']
    ?? $_SESSION['user']['name']
    ?? 'Administrator';

try {
    // DASHBOARD STATISTICS
    $statsQuery = "
        SELECT
            (
                SELECT COUNT(*)
                FROM events
                WHERE LOWER(COALESCE(status, '')) IN ('upcoming', 'ongoing')
                  AND event_date >= CURDATE()
            ) AS active_events,
            (
                SELECT COUNT(*)
                FROM registrations
                WHERE LOWER(COALESCE(status, '')) = 'registered'
            ) AS total_registrations,
            (
                SELECT COUNT(*)
                FROM users
                WHERE LOWER(COALESCE(role, '')) = 'student'
            ) AS total_students
    ";

    $statsResult = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);

    if ($statsResult) {
        $stats = array_merge($stats, $statsResult);
    }

    // CAMPUS EVENTS AND ATTENDEE COUNTS
    $eventsQuery = "
        SELECT
            e.*,
            (
                SELECT COUNT(*)
                FROM registrations r
                WHERE r.event_id = e.id
                  AND LOWER(COALESCE(r.status, '')) = 'registered'
            ) AS registered_count
        FROM events e
        ORDER BY
            CASE
                WHEN LOWER(COALESCE(e.status, '')) IN ('upcoming', 'ongoing')
                     AND e.event_date >= CURDATE()
                THEN 0
                ELSE 1
            END ASC,
            e.event_date ASC,
            e.id DESC
    ";

    $events = $pdo->query($eventsQuery)->fetchAll(PDO::FETCH_ASSOC);

    // RECENT STUDENT REGISTRATIONS
    $recentRegistrationsQuery = "
        SELECT
            r.id AS registration_id,
            r.registered_at,
            r.status AS registration_status,
            u.full_name AS student_name,
            u.email AS student_email,
            e.title AS event_title
        FROM registrations r
        INNER JOIN users u ON u.id = r.user_id
        INNER JOIN events e ON e.id = r.event_id
        WHERE LOWER(COALESCE(r.status, '')) = 'registered'
          AND LOWER(COALESCE(u.role, '')) = 'student'
        ORDER BY r.registered_at DESC, r.id DESC
        LIMIT 10
    ";

    $recentRegistrations = $pdo
        ->query($recentRegistrationsQuery)
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {
    error_log('Admin dashboard database error: ' . $exception->getMessage());

    $pageError = 'Some dashboard information could not be loaded. Please check the database structure.';
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* =========================================
   PTC CAMPUS EVENT HUB
   ADMIN DASHBOARD - DARK GLASS THEME
========================================= */

.dashboard-page {
    --dash-green: #b9e58b;
    --dash-green-light: #d8f3b5;
    --dash-accent: #c7e78b;
    --dash-text: #edf5e9;
    --dash-muted: #a7b9a9;
    --dash-border: rgba(199, 231, 139, .17);

    color: var(--dash-text);
    padding: 12px 0 48px;
}

.dashboard-page *,
.dashboard-page *::before,
.dashboard-page *::after {
    box-sizing: border-box;
}

/* HERO */

.dashboard-page .dashboard-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    margin-bottom: 26px;
    padding: 36px 32px;
    border: 1px solid rgba(199,231,139,.2);
    border-radius: 24px;
    color: #fff;
    background:
        radial-gradient(circle at 90% 8%, rgba(199,231,139,.19), transparent 30%),
        linear-gradient(125deg, #091d16, #123b2b 65%, #1b5038);
    box-shadow: 0 18px 40px rgba(0,0,0,.22);
}

.dashboard-page .dashboard-hero::after {
    content: "";
    position: absolute;
    z-index: -1;
    width: 220px;
    height: 220px;
    right: -65px;
    bottom: -135px;
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 50%;
    box-shadow:
        0 0 0 25px rgba(255,255,255,.025),
        0 0 0 50px rgba(255,255,255,.018);
}

.dashboard-page .hero-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    border: 1px solid rgba(199,231,139,.3);
    border-radius: 30px;
    background: rgba(199,231,139,.1);
    color: #d5f3a7;
    font-size: .7rem;
    font-weight: 850;
    letter-spacing: 1.4px;
}

.dashboard-page .hero-label::before {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--dash-accent);
    box-shadow: 0 0 10px rgba(199,231,139,.7);
}

.dashboard-page .dashboard-hero h1 {
    margin: 19px 0 10px;
    color: #fff;
    font-size: clamp(1.9rem, 4vw, 2.8rem);
    font-weight: 850;
    letter-spacing: -1px;
    line-height: 1.15;
}

.dashboard-page .dashboard-hero p {
    max-width: 590px;
    margin: 0;
    color: rgba(255,255,255,.76);
    font-size: .92rem;
    line-height: 1.8;
}

.dashboard-page .hero-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1px solid rgba(255,255,255,.13);
}

.dashboard-page .hero-welcome {
    color: #d6e7d9;
    font-size: .86rem;
}

.dashboard-page .hero-welcome strong {
    color: #fff;
}

.dashboard-page .hero-date {
    padding: 9px 13px;
    border: 1px solid rgba(255,255,255,.13);
    border-radius: 10px;
    background: rgba(255,255,255,.07);
    color: #d5e7db;
    font-size: .8rem;
    font-weight: 650;
}

.dashboard-page .dashboard-alert {
    border: 1px solid rgba(255,120,100,.3);
    border-radius: 13px;
    background: rgba(120,35,30,.28);
    color: #ffd5ce;
}

/* STATISTICS */

.dashboard-page .dashboard-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
    margin-bottom: 30px;
}

.dashboard-page .stat-card {
    position: relative;
    overflow: hidden;
    min-width: 0;
    padding: 23px;
    border: 1px solid rgba(199,231,139,.18);
    border-radius: 19px;
    background: linear-gradient(145deg, rgba(27,75,51,.82), rgba(12,42,30,.88));
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    box-shadow: 0 10px 28px rgba(0,0,0,.15);
    transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
}

.dashboard-page .stat-card::after {
    content: "";
    position: absolute;
    width: 110px;
    height: 110px;
    right: -45px;
    bottom: -60px;
    border-radius: 50%;
    background: rgba(199,231,139,.08);
}

.dashboard-page .stat-card:hover {
    transform: translateY(-4px);
    border-color: rgba(199,231,139,.42);
    box-shadow: 0 15px 32px rgba(0,0,0,.22);
}

.dashboard-page .stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dashboard-page .stat-icon {
    display: grid;
    width: 47px;
    height: 47px;
    place-items: center;
    border: 1px solid rgba(199,231,139,.22);
    border-radius: 15px;
    background: rgba(199,231,139,.12);
    color: #d5f3a7;
    font-size: 1.35rem;
}

.dashboard-page .stat-card:nth-child(2) .stat-icon {
    border-color: rgba(120,190,220,.25);
    background: rgba(70,145,185,.15);
    color: #a8ddf5;
}

.dashboard-page .stat-card:nth-child(3) .stat-icon {
    border-color: rgba(230,195,115,.25);
    background: rgba(190,150,65,.14);
    color: #f0d28c;
}

.dashboard-page .stat-caption {
    display: block;
    margin-top: 19px;
    color: #b6c9b8;
    font-size: .8rem;
    font-weight: 700;
}

.dashboard-page .stat-number {
    display: block;
    margin-top: 4px;
    color: #d2f0a9;
    font-size: clamp(1.8rem, 3vw, 2.3rem);
    font-weight: 850;
    line-height: 1.2;
}

.dashboard-page .stat-footnote {
    display: block;
    margin-top: 7px;
    color: #92aa98;
    font-size: .74rem;
}

/* MAIN PANELS */

.dashboard-page .events-panel,
.dashboard-page .recent-panel {
    overflow: hidden;
    margin-bottom: 25px;
    border: 1px solid rgba(199,231,139,.17);
    border-radius: 21px;
    background: linear-gradient(145deg, rgba(20,61,42,.91), rgba(10,39,28,.95));
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    box-shadow: 0 12px 32px rgba(0,0,0,.17);
}

.dashboard-page .panel-header,
.dashboard-page .recent-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 17px;
    padding: 24px 25px;
    border-bottom: 1px solid rgba(199,231,139,.13);
    background: rgba(255,255,255,.025);
}

.dashboard-page .section-kicker {
    display: block;
    margin-bottom: 7px;
    color: #b5d58d;
    font-size: .69rem;
    font-weight: 850;
    letter-spacing: 1.4px;
}

.dashboard-page .panel-header h2,
.dashboard-page .recent-heading h2 {
    margin: 0;
    color: #e5f4dc;
    font-size: 1.35rem;
    font-weight: 850;
    letter-spacing: -.35px;
}

.dashboard-page .panel-header p,
.dashboard-page .recent-heading p {
    margin: 7px 0 0;
    color: #a6bba9;
    font-size: .82rem;
    line-height: 1.65;
}

/* BUTTONS */

.dashboard-page .panel-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 9px;
}

.dashboard-page .btn-dashboard {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 41px;
    padding: 10px 14px;
    border: 1px solid transparent;
    border-radius: 11px;
    font-size: .79rem;
    font-weight: 750;
    text-decoration: none;
    transition: all .18s ease;
}

.dashboard-page .btn-dashboard:hover {
    transform: translateY(-2px);
}

.dashboard-page .btn-dashboard-primary {
    border-color: rgba(199,231,139,.35);
    background: linear-gradient(135deg, #b9df83, #8ebd62);
    color: #153522;
    box-shadow: 0 5px 15px rgba(0,0,0,.14);
}

.dashboard-page .btn-dashboard-primary:hover {
    background: linear-gradient(135deg, #d0f0a5, #a9d77a);
    color: #102d1d;
}

.dashboard-page .btn-dashboard-secondary {
    border-color: rgba(199,231,139,.24);
    background: rgba(255,255,255,.055);
    color: #d8ebca;
}

.dashboard-page .btn-dashboard-secondary:hover {
    border-color: rgba(199,231,139,.5);
    background: rgba(199,231,139,.12);
    color: #fff;
}

/* SEARCH TOOLBAR */

.dashboard-page .event-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 13px;
    padding: 16px 25px;
    border-bottom: 1px solid rgba(199,231,139,.12);
    background: rgba(4,25,17,.24);
}

.dashboard-page .event-count {
    color: #a9bcae;
    font-size: .79rem;
}

.dashboard-page .event-count strong {
    color: #d0eda9;
}

.dashboard-page .event-search {
    position: relative;
    width: min(100%, 290px);
}

.dashboard-page .event-search span {
    position: absolute;
    top: 50%;
    left: 13px;
    transform: translateY(-50%);
    color: #a9c19f;
    pointer-events: none;
}

.dashboard-page .event-search input {
    width: 100%;
    min-height: 41px;
    padding: 9px 12px 9px 36px;
    border: 1px solid rgba(199,231,139,.2);
    border-radius: 11px;
    outline: none;
    background: rgba(5,30,20,.65);
    color: #edf5e9;
    font-size: .81rem;
}

.dashboard-page .event-search input::placeholder {
    color: #8da492;
}

.dashboard-page .event-search input:focus {
    border-color: #a8d17b;
    box-shadow: 0 0 0 3px rgba(168,209,123,.13);
}

/* TABLES */

.dashboard-page .table-responsive {
    width: 100%;
    overflow-x: auto;
}

.dashboard-page .event-table,
.dashboard-page .recent-table {
    width: 100%;
    min-width: 740px;
    margin: 0;
    border-collapse: separate;
    border-spacing: 0;
    background: transparent;
}

.dashboard-page .event-table thead th,
.dashboard-page .recent-table thead th {
    padding: 14px 17px;
    border-bottom: 1px solid rgba(199,231,139,.15);
    background: rgba(4,27,18,.5);
    color: #b8d19f;
    font-size: .68rem;
    font-weight: 850;
    letter-spacing: .6px;
    text-transform: uppercase;
    white-space: nowrap;
}

.dashboard-page .event-table tbody td,
.dashboard-page .recent-table tbody td {
    padding: 17px;
    border-bottom: 1px solid rgba(199,231,139,.10);
    background: transparent;
    color: #c5d5c8;
    font-size: .8rem;
    vertical-align: middle;
}

.dashboard-page .event-table tbody tr,
.dashboard-page .recent-table tbody tr {
    background: transparent;
    transition: background .18s ease;
}

.dashboard-page .event-table tbody tr:hover,
.dashboard-page .recent-table tbody tr:hover {
    background: rgba(199,231,139,.055);
}

.dashboard-page .event-table tbody tr:last-child td,
.dashboard-page .recent-table tbody tr:last-child td {
    border-bottom: 0;
}

.dashboard-page .event-title {
    display: block;
    max-width: 250px;
    color: #e0f0d7;
    font-size: .86rem;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.dashboard-page .event-venue {
    display: block;
    max-width: 240px;
    margin-top: 4px;
    color: #91a99a;
    font-size: .73rem;
    overflow-wrap: anywhere;
}

.dashboard-page .event-date {
    color: #d2e3d1;
    font-weight: 700;
    white-space: nowrap;
}

.dashboard-page .category-label {
    display: inline-block;
    padding: 6px 9px;
    border: 1px solid rgba(199,231,139,.19);
    border-radius: 8px;
    background: rgba(199,231,139,.09);
    color: #c9e8a8;
    font-size: .72rem;
    font-weight: 700;
}

.dashboard-page .registration-count {
    color: #d0eda9;
    font-weight: 800;
    white-space: nowrap;
}

.dashboard-page .registration-count small {
    color: #91a99a;
    font-size: .74rem;
    font-weight: 500;
}

/* STATUS */

.dashboard-page .status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    border-radius: 30px;
    font-size: .7rem;
    font-weight: 800;
    white-space: nowrap;
}

.dashboard-page .status-pill::before {
    content: "";
    width: 6px;
    height: 6px;
    flex: 0 0 6px;
    border-radius: 50%;
    background: currentColor;
}

.dashboard-page .status-upcoming {
    border: 1px solid rgba(150,220,120,.2);
    background: rgba(90,160,65,.16);
    color: #b8ed9d;
}

.dashboard-page .status-ongoing {
    border: 1px solid rgba(120,190,220,.2);
    background: rgba(55,130,175,.17);
    color: #a8ddf5;
}

.dashboard-page .status-completed,
.dashboard-page .status-ended {
    border: 1px solid rgba(180,195,180,.15);
    background: rgba(180,195,180,.09);
    color: #c0cec2;
}

.dashboard-page .status-cancelled,
.dashboard-page .status-canceled {
    border: 1px solid rgba(240,130,110,.2);
    background: rgba(180,65,50,.17);
    color: #ffb6a9;
}

.dashboard-page .status-unknown {
    background: rgba(180,190,180,.09);
    color: #c0cec2;
}

/* ACTION BUTTONS */

.dashboard-page .action-buttons {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.dashboard-page .action-buttons form {
    margin: 0;
}

.dashboard-page .btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 31px;
    padding: 6px 9px;
    border: 1px solid rgba(199,231,139,.2);
    border-radius: 8px;
    background: rgba(255,255,255,.055);
    color: #d1e8c2;
    font-size: .72rem;
    font-weight: 750;
    text-decoration: none;
    cursor: pointer;
    transition: all .18s ease;
}

.dashboard-page .btn-action:hover {
    border-color: rgba(199,231,139,.45);
    background: rgba(199,231,139,.13);
    color: #fff;
}

.dashboard-page .btn-action-danger {
    border-color: rgba(240,130,110,.25);
    background: rgba(180,65,50,.1);
    color: #ffb6a9;
}

.dashboard-page .btn-action-danger:hover {
    border-color: rgba(240,130,110,.5);
    background: rgba(180,65,50,.22);
    color: #ffd0c8;
}

/* EMPTY STATES */

.dashboard-page .empty-state {
    padding: 42px 20px !important;
    background: rgba(255,255,255,.015) !important;
    text-align: center;
}

.dashboard-page .empty-icon {
    display: grid;
    width: 56px;
    height: 56px;
    margin: 0 auto 13px;
    place-items: center;
    border: 1px solid rgba(199,231,139,.2);
    border-radius: 17px;
    background: rgba(199,231,139,.1);
    color: #c7e78b;
    font-size: 1.6rem;
}

.dashboard-page .empty-state h3 {
    margin-bottom: 7px;
    color: #e0f0d7;
    font-size: 1.05rem;
    font-weight: 850;
}

.dashboard-page .empty-state p {
    margin-bottom: 17px;
    color: #9eb3a2;
    font-size: .82rem;
}

/* RECENT REGISTRATIONS */

.dashboard-page .student-name {
    display: block;
    color: #e0f0d7;
    font-size: .83rem;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.dashboard-page .student-email {
    display: block;
    margin-top: 4px;
    color: #91a99a;
    font-size: .73rem;
    overflow-wrap: anywhere;
}

.dashboard-page .registered-event {
    color: #c6dfbf;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.dashboard-page .registered-date {
    color: #b2c5b5;
    font-size: .78rem;
    white-space: nowrap;
}

.dashboard-page .registered-badge {
    display: inline-block;
    padding: 6px 10px;
    border: 1px solid rgba(150,220,120,.2);
    border-radius: 30px;
    background: rgba(90,160,65,.16);
    color: #b8ed9d;
    font-size: .7rem;
    font-weight: 800;
    white-space: nowrap;
}

/* BOTTOM LINKS */

.dashboard-page .dashboard-bottom-links {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 3px;
}

.dashboard-page .dashboard-bottom-links a {
    color: #c4e5a5;
    font-size: .81rem;
    font-weight: 750;
    text-decoration: none;
}

.dashboard-page .dashboard-bottom-links a:hover {
    color: #e5f5d3;
    text-decoration: underline;
}

/* TABLET */

@media (max-width: 900px) {
    .dashboard-page .dashboard-stats {
        gap: 12px;
    }

    .dashboard-page .stat-card {
        padding: 18px;
    }

    .dashboard-page .panel-header,
    .dashboard-page .recent-heading {
        padding: 21px;
    }
}

/* MOBILE */

@media (max-width: 600px) {
    .dashboard-page {
        padding-top: 3px;
        padding-bottom: 28px;
    }

    .dashboard-page .dashboard-hero {
        margin-bottom: 19px;
        padding: 25px 20px;
        border-radius: 18px;
    }

    .dashboard-page .dashboard-hero h1 {
        font-size: 1.85rem;
        letter-spacing: -.6px;
    }

    .dashboard-page .dashboard-hero p {
        font-size: .84rem;
    }

    .dashboard-page .hero-bottom {
        align-items: flex-start;
        flex-direction: column;
        gap: 8px;
    }

    .dashboard-page .dashboard-stats {
        grid-template-columns: 1fr;
        gap: 11px;
        margin-bottom: 23px;
    }

    .dashboard-page .stat-card {
        display: grid;
        grid-template-columns: 50px minmax(0, 1fr);
        column-gap: 13px;
        align-items: center;
        padding: 16px;
        border-radius: 16px;
    }

    .dashboard-page .stat-top {
        grid-column: 1;
        grid-row: 1 / span 3;
    }

    .dashboard-page .stat-icon {
        width: 46px;
        height: 46px;
    }

    .dashboard-page .stat-caption {
        grid-column: 2;
        margin-top: 0;
        font-size: .76rem;
    }

    .dashboard-page .stat-number {
        grid-column: 2;
        font-size: 1.75rem;
    }

    .dashboard-page .stat-footnote {
        grid-column: 2;
        margin-top: 3px;
    }

    .dashboard-page .events-panel,
    .dashboard-page .recent-panel {
        border-radius: 16px;
    }

    .dashboard-page .panel-header,
    .dashboard-page .recent-heading {
        align-items: flex-start;
        flex-direction: column;
        padding: 19px 16px;
    }

    .dashboard-page .panel-header h2,
    .dashboard-page .recent-heading h2 {
        font-size: 1.2rem;
    }

    .dashboard-page .panel-actions {
        width: 100%;
    }

    .dashboard-page .panel-actions .btn-dashboard {
        flex: 1;
        padding: 9px 10px;
        font-size: .73rem;
    }

    .dashboard-page .event-toolbar {
        align-items: stretch;
        flex-direction: column;
        padding: 14px 16px;
    }

    .dashboard-page .event-search {
        width: 100%;
    }

    .dashboard-page .event-table,
    .dashboard-page .recent-table {
        min-width: 740px;
    }

    .dashboard-page .event-table thead th,
    .dashboard-page .event-table tbody td,
    .dashboard-page .recent-table thead th,
    .dashboard-page .recent-table tbody td {
        padding: 13px 11px;
    }

    .dashboard-page .dashboard-bottom-links {
        align-items: flex-start;
        flex-direction: column;
        padding: 0 3px;
    }
}

/* =========================================
   ADMIN DASHBOARD - COLORED GLASS BORDERS
   PTC CAMPUS THEME
========================================= */

/* HERO - SOFT LIME */
.dashboard-page .dashboard-hero {
    border: 1px solid rgba(199, 231, 139, 0.55) !important;
    box-shadow:
        0 15px 35px rgba(0, 0, 0, 0.20),
        0 0 15px rgba(199, 231, 139, 0.08) !important;
}

/* STATISTICS CARDS - DIFFERENT BORDER COLORS */
.dashboard-page .stat-card:nth-child(1) {
    border: 1px solid rgba(174, 204, 151, 0.65) !important;
}

.dashboard-page .stat-card:nth-child(2) {
    border: 1px solid rgba(130, 190, 210, 0.55) !important;
}

.dashboard-page .stat-card:nth-child(3) {
    border: 1px solid rgba(220, 190, 125, 0.55) !important;
}

/* STAT CARDS HOVER */
.dashboard-page .stat-card:hover {
    box-shadow:
        0 12px 30px rgba(0, 0, 0, 0.20),
        0 0 17px rgba(199, 231, 139, 0.13) !important;
}

/* CAMPUS EVENTS PANEL */
.dashboard-page .events-panel {
    border: 1px solid rgba(174, 204, 151, 0.60) !important;
    box-shadow:
        0 12px 32px rgba(0, 0, 0, 0.17),
        0 0 14px rgba(174, 204, 151, 0.06) !important;
}

/* RECENT REGISTRATIONS PANEL */
.dashboard-page .recent-panel {
    border: 1px solid rgba(199, 231, 139, 0.48) !important;
    box-shadow:
        0 12px 32px rgba(0, 0, 0, 0.17),
        0 0 14px rgba(199, 231, 139, 0.07) !important;
}

/* PANEL HEADERS */
.dashboard-page .panel-header,
.dashboard-page .recent-heading {
    border-bottom: 1px solid rgba(199, 231, 139, 0.28) !important;
}

/* SEARCH TOOLBAR */
.dashboard-page .event-toolbar {
    border-bottom: 1px solid rgba(174, 204, 151, 0.30) !important;
}

.dashboard-page .event-search input {
    border: 1px solid rgba(174, 204, 151, 0.55) !important;
}

.dashboard-page .event-search input:focus {
    border-color: #c7e78b !important;
    box-shadow: 0 0 0 3px rgba(199, 231, 139, 0.15) !important;
}

/* TABLE HEADER BORDERS */
.dashboard-page .event-table thead th,
.dashboard-page .recent-table thead th {
    border-bottom: 1px solid rgba(199, 231, 139, 0.40) !important;
}

/* TABLE ROW DIVIDERS */
.dashboard-page .event-table tbody td,
.dashboard-page .recent-table tbody td {
    border-bottom: 1px solid rgba(174, 204, 151, 0.20) !important;
}

/* CATEGORY LABEL */
.dashboard-page .category-label {
    border: 1px solid rgba(199, 231, 139, 0.45) !important;
}

/* ACTION BUTTONS */
.dashboard-page .btn-action {
    border-color: rgba(174, 204, 151, 0.40) !important;
}

.dashboard-page .btn-action:hover {
    border-color: #c7e78b !important;
    box-shadow: 0 0 9px rgba(199, 231, 139, 0.12);
}

/* EMPTY STATE */
.dashboard-page .empty-state {
    border-top: 1px solid rgba(174, 204, 151, 0.18);
}

/* BOTTOM LINKS */
.dashboard-page .dashboard-bottom-links a:hover {
    color: #c7e78b !important;
}

/* SMOOTH TRANSITION */
.dashboard-page .stat-card,
.dashboard-page .events-panel,
.dashboard-page .recent-panel,
.dashboard-page .btn-action {
    transition: all 0.3s ease;
}

/* ACCESSIBILITY */

@media (prefers-reduced-motion: reduce) {
    .dashboard-page *,
    .dashboard-page *::before,
    .dashboard-page *::after {
        transition: none !important;
        animation: none !important;
    }
}
</style>

<main class="dashboard-page">
    <div class="container">

        <!-- DASHBOARD HERO -->
        <section class="dashboard-hero">
            <span class="hero-label">CAMPUS EVENT HUB · ADMIN</span>

            <h1>Event Dashboard</h1>

            <p>
                Your campus activities, all in one place.
                Manage events, monitor student registrations,
                and keep every campus activity organized.
            </p>

            <div class="hero-bottom">
                <div class="hero-welcome">
                    Welcome back, <strong><?= e($adminName) ?></strong>!
                </div>

                <div class="hero-date">
                    <?= e(date('l, F j, Y')) ?>
                </div>
            </div>
        </section>

        <?php if ($pageError !== ''): ?>
            <div class="alert alert-danger dashboard-alert" role="alert">
                <?= e($pageError) ?>
            </div>
        <?php endif; ?>

        <!-- STATISTICS -->
        <section class="dashboard-stats" aria-label="Dashboard statistics">

            <article class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon" aria-hidden="true">▦</div>
                </div>
                <span class="stat-caption">Active Events</span>
                <strong class="stat-number">
                    <?= number_format((int) $stats['active_events']) ?>
                </strong>
                <small class="stat-footnote">Upcoming and ongoing events</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon" aria-hidden="true">♧</div>
                </div>
                <span class="stat-caption">Total Registrations</span>
                <strong class="stat-number">
                    <?= number_format((int) $stats['total_registrations']) ?>
                </strong>
                <small class="stat-footnote">Active registrations</small>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon" aria-hidden="true">♙</div>
                </div>
                <span class="stat-caption">Student Accounts</span>
                <strong class="stat-number">
                    <?= number_format((int) $stats['total_students']) ?>
                </strong>
                <small class="stat-footnote">Registered student accounts</small>
            </article>

        </section>

        <!-- CAMPUS EVENTS -->
        <section class="events-panel">

            <div class="panel-header">
                <div>
                    <span class="section-kicker">MANAGEMENT CENTER</span>
                    <h2>Campus Events</h2>
                    <p>Manage event details, check attendees, and update event status.</p>
                </div>

                <div class="panel-actions">
                    <a class="btn-dashboard btn-dashboard-primary" href="add-event.php">
                        ＋ Create Event
                    </a>

                    <a class="btn-dashboard btn-dashboard-secondary" href="event-history.php">
                        Event History ↗
                    </a>
                </div>
            </div>

            <div class="event-toolbar">
                <div class="event-count">
                    Showing <strong id="visibleEventCount"><?= count($events) ?></strong>
                    of <strong><?= count($events) ?></strong> events
                </div>

                <label class="event-search">
                    <span aria-hidden="true">⌕</span>
                    <input
                        type="search"
                        id="eventSearch"
                        placeholder="Search events..."
                        aria-label="Search events by title, venue, category, or status"
                    >
                </label>
            </div>

            <div class="table-responsive">
                <table class="table event-table align-middle">
                    <thead>
                        <tr>
                            <th>Event Details</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Attendees</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody id="eventTableBody">

                    <?php if (!empty($events)): ?>

                        <?php foreach ($events as $event): ?>
                            <?php
                            $status = strtolower(trim((string) ($event['status'] ?? 'unknown')));
                            $statusClass = preg_replace('/[^a-z0-9_-]/', '', $status);

                            $eventDate = (string) ($event['event_date'] ?? '');
                            $timestamp = $eventDate !== '' ? strtotime($eventDate) : false;
                            $todayTimestamp = strtotime(date('Y-m-d'));

                            $isFutureOrToday = $timestamp !== false
                                && $timestamp >= $todayTimestamp;

                            $canCancel = in_array($status, ['upcoming', 'ongoing'], true)
                                && $isFutureOrToday;

                            $eventTitle = (string) ($event['title'] ?? 'Untitled Event');
                            $eventVenue = (string) ($event['venue'] ?? 'Venue to be announced');
                            $eventCategory = (string) ($event['category'] ?? 'Uncategorized');
                            $eventCapacity = (int) ($event['capacity'] ?? 0);
                            $registeredCount = (int) ($event['registered_count'] ?? 0);

                            $searchText = strtolower(
                                $eventTitle . ' ' . $eventVenue . ' ' .
                                $eventCategory . ' ' . $status
                            );
                            ?>

                            <tr class="event-row" data-search="<?= e($searchText) ?>">
                                <td>
                                    <span class="event-title"><?= e($eventTitle) ?></span>
                                    <span class="event-venue"><?= e($eventVenue) ?></span>
                                </td>

                                <td>
                                    <span class="event-date">
                                        <?= $timestamp !== false ? e(date('M d, Y', $timestamp)) : 'Not set' ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="category-label"><?= e($eventCategory) ?></span>
                                </td>

                                <td>
                                    <span class="registration-count">
                                        <?= number_format($registeredCount) ?>
                                        <small>/ <?= number_format($eventCapacity) ?></small>
                                    </span>
                                </td>

                                <td>
                                    <span class="status-pill status-<?= e($statusClass ?: 'unknown') ?>">
                                        <?= e(ucfirst($status)) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="action-buttons">
                                        <a
                                            class="btn-action"
                                            href="edit-event.php?id=<?= (int) $event['id'] ?>"
                                        >Edit</a>

                                        <a
                                            class="btn-action"
                                            href="registrations.php?event_id=<?= (int) $event['id'] ?>"
                                        >Attendees</a>

                                        <?php if ($canCancel): ?>
                                            <form
                                                method="post"
                                                action="cancel-event.php"
                                                data-confirm="Cancel this event? Existing registration records will be kept."
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e(csrf_token()) ?>"
                                                >
                                                <input
                                                    type="hidden"
                                                    name="event_id"
                                                    value="<?= (int) $event['id'] ?>"
                                                >
                                                <button
                                                    class="btn-action btn-action-danger"
                                                    type="submit"
                                                >Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="noSearchResults" hidden>
                            <td colspan="6" class="empty-state">
                                <div class="empty-icon" aria-hidden="true">⌕</div>
                                <h3>No matching events</h3>
                                <p>Try another title, venue, category, or status.</p>
                            </td>
                        </tr>

                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <div class="empty-icon" aria-hidden="true">▦</div>
                                <h3>No events yet</h3>
                                <p>Create your first campus event to get started.</p>
                                <a class="btn-dashboard btn-dashboard-primary" href="add-event.php">
                                    ＋ Create Your First Event
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </section>

        <!-- RECENT STUDENT REGISTRATIONS -->
        <section class="recent-panel">

            <div class="recent-heading">
                <div>
                    <span class="section-kicker">STUDENT ACTIVITY</span>
                    <h2>Recent Student Registrations</h2>
                    <p>See which students registered for campus events and when.</p>
                </div>

                <a class="btn-dashboard btn-dashboard-primary" href="registrations.php">
                    View All Registrations →
                </a>
            </div>

            <div class="table-responsive">
                <table class="table recent-table align-middle">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Event</th>
                            <th>Date Registered</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (!empty($recentRegistrations)): ?>

                        <?php foreach ($recentRegistrations as $registration): ?>
                            <?php
                            $registeredAt = (string) ($registration['registered_at'] ?? '');
                            $registeredTimestamp = $registeredAt !== ''
                                ? strtotime($registeredAt)
                                : false;
                            ?>

                            <tr>
                                <td>
                                    <span class="student-name">
                                        <?= e($registration['student_name'] ?? 'Unknown Student') ?>
                                    </span>
                                    <span class="student-email">
                                        <?= e($registration['student_email'] ?? '') ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="registered-event">
                                        <?= e($registration['event_title'] ?? 'Unknown Event') ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="registered-date">
                                        <?= $registeredTimestamp !== false
                                            ? e(date('M d, Y · h:i A', $registeredTimestamp))
                                            : 'Not available' ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="registered-badge">Registered</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                <div class="empty-icon" aria-hidden="true">♙</div>
                                <h3>No student registrations yet</h3>
                                <p>New active student registrations will appear here.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="dashboard-bottom-links">
            <a href="event-history.php">← Review past and cancelled events</a>
            <a href="manage-events.php">Open full event management →</a>
        </div>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('eventSearch');
    const eventRows = Array.from(document.querySelectorAll('.event-row'));
    const visibleCount = document.getElementById('visibleEventCount');
    const noResults = document.getElementById('noSearchResults');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const keyword = this.value.trim().toLowerCase();
            let visible = 0;

            eventRows.forEach(function (row) {
                const matches = (row.dataset.search || '').includes(keyword);
                row.hidden = !matches;

                if (matches) {
                    visible++;
                }
            });

            if (visibleCount) {
                visibleCount.textContent = visible;
            }

            if (noResults) {
                noResults.hidden = visible !== 0 || eventRows.length === 0;
            }
        });
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(this.dataset.confirm || 'Are you sure?')) {
                event.preventDefault();
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>