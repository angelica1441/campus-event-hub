<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

$basePath = '/campus-event-hub/';
$user = $user ?? ($_SESSION['user'] ?? null);
$flash = $flash ?? ($_SESSION['flash'] ?? null);

$role = strtolower((string) ($user['role'] ?? 'visitor'));

if (!in_array($role, ['admin', 'student'], true)) {
    $role = 'visitor';
}

$fullName = trim((string) ($user['full_name'] ?? 'User'));

if ($fullName === '') {
    $fullName = 'User';
}

$initial = strtoupper(substr($fullName, 0, 1));

$flashType = 'success';

if (is_array($flash) && !empty($flash['type'])) {
    $allowedFlashTypes = ['success', 'danger', 'warning', 'info', 'primary'];

    $flashType = in_array($flash['type'], $allowedFlashTypes, true)
        ? $flash['type']
        : 'success';
}

unset($_SESSION['flash']);

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');

if (!function_exists('cehNavActive')) {
    function cehNavActive(array $pages, string $currentPage): string
    {
        return in_array($currentPage, $pages, true) ? ' active' : '';
    }
}

/* =====================================
   PAGE URLS
===================================== */

$homeUrl = $basePath . 'index.php';
$eventsUrl = $basePath . 'events.php';
$calendarUrl = $basePath . 'calendar.php';
$loginUrl = $basePath . 'login.php';
$registerUrl = $basePath . 'register.php';

$dashboardUrl = $basePath . 'admin/dashboard.php';
$historyUrl = $basePath . 'admin/event-history.php';
$adminEventsUrl = $basePath . 'admin/manage-events.php';
$adminRegistrationsUrl = $basePath . 'admin/registrations.php';
$studentVerificationsUrl = $basePath . 'admin/student-verifications.php';

$studentProfileUrl = $basePath . 'student/profile.php';
$studentVerificationUrl = $basePath . 'student-verification.php';
$studentAttendanceUrl = $basePath . 'student/attendance-history.php';
$studentRegistrationsUrl = $basePath . 'my-registrations.php';

$logoutUrl = $basePath . 'logout.php';

$ptcLogoUrl = 'https://paterostechnologicalcollege.edu.ph/ASSETS/IMAGES/LOGO/logo-ptc.png';
$localLogoUrl = $basePath . 'assets/images/logo-ptc.png';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle ?? 'Campus Event Hub | PTC') ?></title>

    <meta name="description"
        content="Discover campus events, participate in activities, and connect with the Pateros Technological College community.">

    <meta name="theme-color" content="#071d18">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet"
        href="/campus-event-hub/assets/css/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --ptc-bg: #071d18;
            --ptc-sidebar: #092820;
            --ptc-panel: #0d3328;
            --ptc-green: #24b77c;
            --ptc-green-bright: #56efa8;
            --ptc-lime: #d2f89b;
            --ptc-text: #f3fff7;
            --ptc-muted: #a0bdae;
            --ptc-border: rgba(183, 235, 199, .14);
            --ptc-sidebar-width: 270px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--ptc-text);
            background:
                radial-gradient(
                    ellipse at 85% 0%,
                    rgba(31, 159, 104, .09),
                    transparent 34%
                ),
                var(--ptc-bg);
            font-family: 'DM Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        #sidebarToggle {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
        }

        /* SIDEBAR */

        .app-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1045;
            display: flex;
            flex-direction: column;
            width: var(--ptc-sidebar-width);
            overflow-x: hidden;
            overflow-y: auto;
            padding: 22px 16px 17px;
            border-right: 1px solid var(--ptc-border);
            background:
                radial-gradient(
                    ellipse at 0% 100%,
                    rgba(37, 170, 112, .14),
                    transparent 42%
                ),
                linear-gradient(180deg, #0b3026 0%, #071e18 100%);
            box-shadow: 10px 0 35px rgba(0, 0, 0, .17);
            scrollbar-width: thin;
            scrollbar-color: #285d46 transparent;
        }

        /* BRAND */

        .sidebar-brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            width: 100%;
            min-width: 0;
            padding: 8px 4px 25px;
            border-bottom: 1px solid var(--ptc-border);
            color: #fff;
            text-align: center;
            text-decoration: none;
        }

        .sidebar-logo {
            position: relative;
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: 150px;
            height: 150px;
            padding: 0;
            margin: 0 auto;
            overflow: hidden;
            border: 3px solid var(--ptc-lime);
            border-radius: 50%;
            background: #fff;
            box-shadow:
                0 0 0 5px rgba(210, 248, 155, .08),
                0 10px 30px rgba(0, 0, 0, .32),
                0 0 28px rgba(86, 239, 168, .15);
            text-decoration: none;
            transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
        }

        .sidebar-logo:hover {
            transform: scale(1.04);
            border-color: var(--ptc-green-bright);
            box-shadow:
                0 0 0 6px rgba(86, 239, 168, .10),
                0 12px 32px rgba(0, 0, 0, .35),
                0 0 30px rgba(86, 239, 168, .22);
        }

        .sidebar-logo img {
            display: block;
            width: 100%;
            height: 100%;
            max-width: none;
            max-height: none;
            padding: 0;
            margin: 0;
            border: 0;
            border-radius: 50%;
            object-fit: cover;
            object-position: center;
        }

        .sidebar-brand-copy {
            display: block;
            flex: 0 0 auto;
            width: 100%;
            min-width: 0;
            color: #fff;
            text-align: center;
            text-decoration: none;
        }

        .sidebar-brand-copy strong {
            display: block;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -.5px;
        }

        .sidebar-brand-copy small {
            display: block;
            margin-top: 7px;
            color: var(--ptc-lime);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.3px;
        }

        /* NAVIGATION */

        .sidebar-section-label {
            margin: 25px 10px 10px;
            color: #80ad94;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.7px;
            text-transform: uppercase;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .sidebar-menu li {
            min-width: 0;
        }

        .sidebar-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            min-height: 47px;
            padding: 11px 13px;
            border: 1px solid transparent;
            border-radius: 12px;
            background: transparent;
            color: #c2d9cc;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 700;
            text-align: left;
            text-decoration: none;
            transition: background .2s ease, color .2s ease, border-color .2s ease, transform .2s ease;
        }

        .sidebar-link i {
            display: inline-flex;
            flex: 0 0 22px;
            align-items: center;
            justify-content: center;
            width: 22px;
            color: #9dc5ad;
            font-size: 18px;
            transition: color .2s ease, transform .2s ease;
        }

        .sidebar-link:hover {
            transform: translateX(2px);
            border-color: rgba(180, 239, 198, .12);
            background: rgba(255, 255, 255, .045);
            color: #fff;
        }

        .sidebar-link:hover i {
            color: var(--ptc-lime);
            transform: scale(1.06);
        }

        .sidebar-link.active {
            border-color: rgba(86, 239, 168, .23);
            background:
                linear-gradient(
                    110deg,
                    rgba(35, 177, 117, .25),
                    rgba(23, 105, 72, .19)
                );
            color: #fff;
            box-shadow:
                inset 3px 0 0 var(--ptc-green-bright),
                0 5px 18px rgba(0, 0, 0, .08);
        }

        .sidebar-link.active i {
            color: var(--ptc-green-bright);
        }

        .sidebar-divider {
            flex: 0 0 auto;
            height: 1px;
            margin: 18px 7px 16px;
            background: var(--ptc-border);
        }

        /* FIGHTING DUCKS */

        .fighting-ducks-card {
            position: relative;
            isolation: isolate;
            flex: 0 0 auto;
            margin-top: 0;
            padding: 16px 14px 14px;
            overflow: hidden;
            border: 1px solid rgba(190, 238, 174, .2);
            border-radius: 18px;
            background: linear-gradient(145deg, rgba(22, 75, 53, .97), rgba(7, 35, 27, .99));
            box-shadow: 0 12px 28px rgba(0, 0, 0, .14);
        }

        .ducks-glow {
            position: absolute;
            z-index: -1;
            top: -65px;
            right: -55px;
            width: 155px;
            height: 155px;
            border-radius: 50%;
            background: rgba(192, 245, 134, .13);
            filter: blur(35px);
            pointer-events: none;
        }

        .ducks-image-wrap {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 112px;
            margin: -2px -3px 10px;
            overflow: hidden;
            border: 1px solid rgba(210, 248, 155, .12);
            border-radius: 13px;
            background: radial-gradient(ellipse at center, rgba(210, 248, 155, .09), rgba(3, 22, 16, .18));
        }

        .fighting-ducks-image {
            display: block;
            width: 100%;
            height: 112px;
            object-fit: contain;
            object-position: center;
            padding: 5px;
            filter: drop-shadow(0 6px 10px rgba(0, 0, 0, .22));
        }

        .ducks-fallback {
            display: none;
            padding: 20px;
            font-size: 57px;
            line-height: 1;
        }

        .ducks-tag {
            margin-bottom: 6px;
            color: var(--ptc-lime);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1.8px;
        }

        .fighting-ducks-card h3 {
            margin: 0;
            color: #f3fff7;
            font-family: 'Outfit', sans-serif;
            font-size: 25px;
            font-weight: 800;
            line-height: 1.02;
            letter-spacing: -.8px;
        }

        .fighting-ducks-card p {
            margin: 10px 0 0;
            color: #c4dfce;
            font-size: 11px;
            line-height: 1.65;
        }

        .ducks-bottom-line {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-top: 14px;
            color: var(--ptc-lime);
            font-size: 13px;
        }

        .ducks-bottom-line span {
            flex: 1;
            height: 1px;
            background: rgba(210, 248, 155, .25);
        }

        /* COLLEGE INFORMATION */

        .sidebar-campus-card {
            flex: 0 0 auto;
            margin-top: 12px;
            padding: 13px 12px;
            border: 1px solid var(--ptc-border);
            border-radius: 14px;
            background: rgba(255, 255, 255, .025);
        }

        .sidebar-campus-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border: 1px solid rgba(210, 248, 155, .16);
            border-radius: 11px;
            background: rgba(210, 248, 155, .07);
            color: var(--ptc-lime);
            font-size: 17px;
        }

        .sidebar-campus-card strong {
            display: block;
            margin-top: 9px;
            color: #f1fff5;
            font-family: 'Outfit', sans-serif;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.5;
        }

        .sidebar-campus-card p {
            margin: 5px 0 0;
            color: var(--ptc-muted);
            font-size: 11px;
            line-height: 1.6;
        }

        /* LOGOUT */

        .sidebar-bottom {
            flex: 0 0 auto;
            margin-top: 15px;
        }

        .sidebar-bottom form {
            margin: 0;
        }

        .sidebar-bottom .sidebar-link {
            color: #f0b7b7;
            cursor: pointer;
        }

        .sidebar-bottom .sidebar-link i {
            color: #efa0a0;
        }

        .sidebar-bottom .sidebar-link:hover {
            border-color: rgba(239, 160, 160, .16);
            background: rgba(205, 67, 67, .08);
            color: #fff;
        }

        /* TOPBAR */

        .app-topbar {
            position: sticky;
            top: 0;
            z-index: 1035;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            min-height: 76px;
            margin-left: var(--ptc-sidebar-width);
            padding: 12px 30px;
            border-bottom: 1px solid var(--ptc-border);
            background: rgba(7, 29, 24, .91);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .topbar-left,
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
        }

        .mobile-menu-button {
            display: none;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border: 1px solid var(--ptc-border);
            border-radius: 12px;
            background: var(--ptc-panel);
            color: var(--ptc-lime);
            cursor: pointer;
        }

        .topbar-heading {
            min-width: 0;
        }

        .topbar-heading strong {
            display: block;
            color: #f4fff7;
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -.25px;
        }

        .topbar-heading small {
            display: block;
            margin-top: 3px;
            color: var(--ptc-muted);
            font-size: 11px;
        }

        .topbar-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border: 1px solid var(--ptc-border);
            border-radius: 50px;
            background: rgba(20, 67, 49, .42);
            color: #c9ead5;
            font-size: 11px;
            font-weight: 700;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #57e7a2;
            box-shadow: 0 0 9px rgba(87, 231, 162, .6);
        }

        /* ACCOUNT DROPDOWN */

        .role-selector {
            position: relative;
        }

        .role-selector summary {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 46px;
            padding: 6px 11px 6px 7px;
            border: 1px solid rgba(159, 220, 180, .2);
            border-radius: 13px;
            background: linear-gradient(135deg, #123e31, #0b2d24);
            color: #fff;
            cursor: pointer;
            list-style: none;
        }

        .role-selector summary::-webkit-details-marker {
            display: none;
        }

        .role-selector summary:hover {
            border-color: rgba(197, 245, 138, .46);
            background: #154634;
        }

        .account-avatar {
            display: inline-flex;
            flex: 0 0 34px;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border: 1px solid rgba(197, 245, 138, .24);
            border-radius: 11px;
            background: linear-gradient(135deg, #29bb83, #116c4c);
            color: #fff;
            font-size: 13px;
            font-weight: 800;
        }

        .account-copy {
            min-width: 0;
        }

        .account-copy strong {
            display: block;
            max-width: 145px;
            overflow: hidden;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .account-copy small {
            display: block;
            margin-top: 3px;
            color: var(--ptc-lime);
            font-size: 10px;
            text-transform: capitalize;
        }

        .role-selector summary > i {
            color: #a6d8b6;
            font-size: 12px;
        }

        .role-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            z-index: 1100;
            width: 245px;
            padding: 9px;
            border: 1px solid var(--ptc-border);
            border-radius: 16px;
            background: #0b2b23;
            box-shadow: 0 18px 45px rgba(0, 0, 0, .38);
        }

        .role-dropdown-title {
            padding: 9px 10px 11px;
            color: #83ad95;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .role-option {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px 10px;
            border: 1px solid transparent;
            border-radius: 11px;
            color: #e7f7ec;
            text-decoration: none;
        }

        .role-option:hover {
            border-color: var(--ptc-border);
            background: #164735;
            color: #fff;
        }

        .role-option > i:first-child {
            display: inline-flex;
            flex: 0 0 35px;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            border-radius: 11px;
            background: rgba(197, 245, 138, .1);
            color: var(--ptc-lime);
            font-size: 17px;
        }

        .role-option strong {
            display: block;
            font-size: 12px;
        }

        .role-option small {
            display: block;
            margin-top: 3px;
            color: var(--ptc-muted);
            font-size: 10px;
        }

        /* MAIN CONTENT */

        .app-main {
            min-height: calc(100vh - 76px);
            margin-left: var(--ptc-sidebar-width);
            padding: 27px 30px 42px;
            color: var(--ptc-text);
        }

        .app-main > .container,
        .app-main > .container-fluid {
            max-width: 1600px;
        }

        .site-flash {
            margin-bottom: 20px;
            border-radius: 12px;
            font-size: 13px;
        }

        .app-main .section-heading h2 {
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            letter-spacing: -.4px;
        }

        .app-main .section-heading p {
            color: var(--ptc-muted);
        }

        .app-main .table-responsive {
            max-width: 100%;
        }

        .app-main .page-hero,
        .app-main .events-hero,
        .app-main .auth-hero,
        .app-main .banner-hero {
            overflow: hidden;
            border: 1px solid var(--ptc-border);
            border-radius: 20px;
        }

        .sidebar-overlay {
            display: none;
        }

        /* TABLET */

        @media (max-width: 1100px) {
            :root {
                --ptc-sidebar-width: 235px;
            }

            .app-sidebar {
                padding-right: 13px;
                padding-left: 13px;
            }

            .sidebar-logo {
                width: 125px;
                height: 125px;
            }

            .app-topbar {
                padding-right: 20px;
                padding-left: 20px;
            }

            .app-main {
                padding-right: 20px;
                padding-left: 20px;
            }

            .topbar-status {
                display: none;
            }
        }

        /* MOBILE */

        @media (max-width: 767.98px) {
            .app-sidebar {
                width: min(300px, 86vw);
                padding: 20px 15px 18px;
                transform: translateX(-105%);
                transition: transform .28s ease;
            }

            #sidebarToggle:checked ~ .app-sidebar {
                transform: translateX(0);
            }

            .sidebar-logo {
                width: 135px;
                height: 135px;
            }

            .sidebar-brand {
                padding-top: 5px;
                padding-bottom: 22px;
            }

            .sidebar-brand-copy strong {
                font-size: 17px;
            }

            .sidebar-overlay {
                position: fixed;
                inset: 0;
                z-index: 1040;
                display: block;
                background: rgba(0, 0, 0, .65);
                opacity: 0;
                pointer-events: none;
                transition: opacity .25s ease;
            }

            #sidebarToggle:checked ~ .sidebar-overlay {
                opacity: 1;
                pointer-events: auto;
            }

            .app-topbar {
                min-height: 67px;
                margin-left: 0;
                padding: 10px 13px;
            }

            .mobile-menu-button {
                display: inline-flex;
                flex: 0 0 40px;
                width: 40px;
                height: 40px;
            }

            .topbar-left {
                gap: 10px;
            }

            .topbar-heading strong {
                font-size: 13px;
            }

            .topbar-heading small {
                font-size: 10px;
            }

            .topbar-right {
                gap: 7px;
            }

            .role-selector summary {
                gap: 7px;
                min-height: 42px;
                padding: 5px 8px 5px 5px;
            }

            .account-avatar {
                flex-basis: 30px;
                width: 30px;
                height: 30px;
            }

            .account-copy strong {
                max-width: 88px;
                font-size: 11px;
            }

            .role-dropdown {
                position: fixed;
                top: 62px;
                right: 10px;
                width: min(270px, calc(100vw - 20px));
            }

            .app-main {
                min-height: calc(100vh - 67px);
                margin-left: 0;
                padding: 19px 13px 30px;
            }

            .app-main .page-hero,
            .app-main .events-hero,
            .app-main .auth-hero,
            .app-main .banner-hero {
                border-radius: 15px;
            }

            body:has(#sidebarToggle:checked) {
                overflow: hidden;
            }
        }

        @media (max-width: 380px) {
            .topbar-heading small {
                display: none;
            }

            .topbar-heading strong {
                max-width: 105px;
                font-size: 11px;
            }

            .account-copy strong {
                max-width: 65px;
            }

            .app-main {
                padding-right: 10px;
                padding-left: 10px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body class="<?= ($role === 'admin' && $currentPage === 'dashboard.php') ? 'admin-home-page' : '' ?>">

<input
    type="checkbox"
    id="sidebarToggle"
    aria-label="Toggle navigation menu"
>

<!-- SIDEBAR -->

<aside class="app-sidebar">

    <!-- PTC BRAND -->
    <div class="sidebar-brand">

        <a
            class="sidebar-logo"
            href="https://www.facebook.com/share/1BR3WP5eBY/"
            target="_blank"
            rel="noopener noreferrer"
            onclick="return confirm('Are you sure you want to visit the official Pateros Technological College Facebook page?');"
            title="Visit PTC Official Facebook Page"
        >
            <img
                src="<?= e($ptcLogoUrl) ?>"
                alt="Pateros Technological College Logo"
                width="150"
                height="150"
                onerror="this.onerror=null;this.src='<?= e($localLogoUrl) ?>';"
            >
        </a>

        <a
            class="sidebar-brand-copy"
            href="<?= e($homeUrl) ?>"
            title="Campus Event Hub Home"
        >
            <strong>Campus Event Hub</strong>
            <small>PTC · CAMPUS COMMUNITY</small>
        </a>

    </div>

    <!-- ADMIN NAVIGATION -->
    <?php if ($role === 'admin'): ?>

        <div class="sidebar-section-label">Administration</div>

        <ul class="sidebar-menu">

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['dashboard.php'], $currentPage) ?>"
                    href="<?= e($dashboardUrl) ?>"
                >
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['manage-events.php', 'add-event.php', 'edit-event.php'], $currentPage) ?>"
                    href="<?= e($adminEventsUrl) ?>"
                >
                    <i class="bi bi-calendar2-event"></i>
                    <span>Manage Events</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['registrations.php'], $currentPage) ?>"
                    href="<?= e($adminRegistrationsUrl) ?>"
                >
                    <i class="bi bi-people-fill"></i>
                    <span>Registrations</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['student-verifications.php'], $currentPage) ?>"
                    href="<?= e($studentVerificationsUrl) ?>"
                >
                    <i class="bi bi-person-vcard-fill"></i>
                    <span>Student Verifications</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['event-history.php'], $currentPage) ?>"
                    href="<?= e($historyUrl) ?>"
                >
                    <i class="bi bi-clock-history"></i>
                    <span>Event History</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['events.php'], $currentPage) ?>"
                    href="<?= e($eventsUrl) ?>"
                >
                    <i class="bi bi-globe2"></i>
                    <span>Public Events Page</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['calendar.php'], $currentPage) ?>"
                    href="<?= e($calendarUrl) ?>"
                >
                    <i class="bi bi-calendar3"></i>
                    <span>Event Calendar</span>
                </a>
            </li>

        </ul>

    <!-- STUDENT NAVIGATION -->
    <?php elseif ($role === 'student'): ?>

        <div class="sidebar-section-label">Main Menu</div>

        <ul class="sidebar-menu">

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['index.php'], $currentPage) ?>"
                    href="<?= e($homeUrl) ?>"
                >
                    <i class="bi bi-house-door-fill"></i>
                    <span>Home</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['events.php', 'event-details.php'], $currentPage) ?>"
                    href="<?= e($eventsUrl) ?>"
                >
                    <i class="bi bi-calendar2-event"></i>
                    <span>Browse Events</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['calendar.php'], $currentPage) ?>"
                    href="<?= e($calendarUrl) ?>"
                >
                    <i class="bi bi-calendar3"></i>
                    <span>Event Calendar</span>
                </a>
            </li>

        </ul>

        <div class="sidebar-section-label">My Activities</div>

        <ul class="sidebar-menu">

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['my-registrations.php'], $currentPage) ?>"
                    href="<?= e($studentRegistrationsUrl) ?>"
                >
                    <i class="bi bi-ticket-perforated-fill"></i>
                    <span>My Registrations</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['attendance-history.php'], $currentPage) ?>"
                    href="<?= e($studentAttendanceUrl) ?>"
                >
                    <i class="bi bi-calendar2-check-fill"></i>
                    <span>Attendance History</span>
                </a>
            </li>

        </ul>

        <div class="sidebar-section-label">My Account</div>

        <ul class="sidebar-menu">

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['student-verification.php'], $currentPage) ?>"
                    href="<?= e($studentVerificationUrl) ?>"
                >
                    <i class="bi bi-person-vcard-fill"></i>
                    <span>Student Verification</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['profile.php', 'change-password.php', 'registration-history.php'], $currentPage) ?>"
                    href="<?= e($studentProfileUrl) ?>"
                >
                    <i class="bi bi-person-circle"></i>
                    <span>My Profile</span>
                </a>
            </li>

        </ul>

    <!-- VISITOR NAVIGATION -->
    <?php else: ?>

        <div class="sidebar-section-label">Explore Campus</div>

        <ul class="sidebar-menu">

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['index.php'], $currentPage) ?>"
                    href="<?= e($homeUrl) ?>"
                >
                    <i class="bi bi-house-door-fill"></i>
                    <span>Home</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['events.php', 'event-details.php'], $currentPage) ?>"
                    href="<?= e($eventsUrl) ?>"
                >
                    <i class="bi bi-calendar2-event"></i>
                    <span>Campus Events</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['calendar.php'], $currentPage) ?>"
                    href="<?= e($calendarUrl) ?>"
                >
                    <i class="bi bi-calendar3"></i>
                    <span>Event Calendar</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['login.php'], $currentPage) ?>"
                    href="<?= e($loginUrl) ?>"
                >
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Log In</span>
                </a>
            </li>

            <li>
                <a
                    class="sidebar-link<?= cehNavActive(['register.php'], $currentPage) ?>"
                    href="<?= e($registerUrl) ?>"
                >
                    <i class="bi bi-person-plus-fill"></i>
                    <span>Create Account</span>
                </a>
            </li>

        </ul>

    <?php endif; ?>

    <div class="sidebar-divider"></div>

    <!-- FIGHTING DUCKS -->
    <section class="fighting-ducks-card" aria-label="PTC Fighting Ducks campus spirit">

        <div class="ducks-glow"></div>

        <div class="ducks-image-wrap">

            <img
                src="<?= e($basePath) ?>assets/images/fighting-ducks.png"
                alt="Fighting Ducks campus mascot"
                class="fighting-ducks-image"
                onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='block';"
            >

            <div class="ducks-fallback" aria-label="Duck mascot">
                🦆
            </div>

        </div>

        <div class="ducks-tag">PTC CAMPUS SPIRIT</div>

        <h3>FIGHTING<br>DUCKS</h3>

        <p>One PTC. One Community. One Family.</p>

        <div class="ducks-bottom-line">
            <span></span>
            <i class="bi bi-stars"></i>
            <span></span>
        </div>

    </section>

    <!-- COLLEGE INFORMATION -->
    <section class="sidebar-campus-card">

        <span class="sidebar-campus-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </span>

        <strong>Pateros Technological College</strong>

        <p>
            Discover opportunities, join campus activities,
            and connect with your college community.
        </p>

    </section>

    <!-- SIDEBAR LOGOUT -->
    <?php if ($user): ?>

        <div class="sidebar-bottom">

            <form action="<?= e($logoutUrl) ?>" method="post">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <button class="sidebar-link" type="submit">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Log Out</span>
                </button>

            </form>

        </div>

    <?php endif; ?>

</aside>

<label
    class="sidebar-overlay"
    for="sidebarToggle"
    aria-label="Close navigation menu"
></label>

<!-- TOPBAR -->

<header class="app-topbar">

    <div class="topbar-left">

        <label
            class="mobile-menu-button"
            for="sidebarToggle"
            aria-label="Open navigation menu"
        >
            <i class="bi bi-list fs-4"></i>
        </label>

        <div class="topbar-heading">

            <strong>
                <?php if ($role === 'admin'): ?>
                    Administration Portal
                <?php elseif ($role === 'student'): ?>
                    Student Portal
                <?php else: ?>
                    Campus Community
                <?php endif; ?>
            </strong>

            <small>Pateros Technological College</small>

        </div>

    </div>

    <div class="topbar-right">

        <span class="topbar-status">
            <span class="status-dot"></span>
            Campus Hub
        </span>

        <details class="role-selector">

            <summary>

                <span class="account-avatar">

                    <?php if ($user): ?>
                        <?= e($initial) ?>
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>

                </span>

                <span class="account-copy">

                    <strong>
                        <?= e($user ? $fullName : 'Choose Account') ?>
                    </strong>

                    <small>
                        <?= e($role === 'visitor' ? 'Guest' : ucfirst($role)) ?>
                    </small>

                </span>

                <i class="bi bi-chevron-down"></i>

            </summary>

            <div class="role-dropdown">

                <div class="role-dropdown-title">
                    Account Profiles
                </div>

                <?php if ($user): ?>

                    <div
                        class="role-option"
                        style="cursor: default; background: rgba(255,255,255,0.03);"
                    >

                        <i class="bi bi-person-check-fill"></i>

                        <div>
                            <strong><?= e($fullName) ?></strong>
                            <small>
                                Signed in as <?= e(ucfirst($role)) ?>
                            </small>
                        </div>

                    </div>

                    <div class="p-2 pt-1">

                        <form action="<?= e($logoutUrl) ?>" method="post">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(csrf_token()) ?>"
                            >

                            <button
                                type="submit"
                                class="btn btn-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2 py-2"
                                style="border-radius: 9px; font-weight: 700; font-size: 12px;"
                            >
                                <i class="bi bi-box-arrow-left"></i>
                                Log Out
                            </button>

                        </form>

                    </div>

                <?php else: ?>

                    <a class="role-option" href="<?= e($loginUrl) ?>">

                        <i class="bi bi-box-arrow-in-right"></i>

                        <div>
                            <strong>Log In</strong>
                            <small>Access your account</small>
                        </div>

                    </a>

                    <a class="role-option" href="<?= e($registerUrl) ?>">

                        <i class="bi bi-person-plus-fill"></i>

                        <div>
                            <strong>Create Account</strong>
                            <small>Join the PTC community</small>
                        </div>

                    </a>

                <?php endif; ?>

            </div>

        </details>

    </div>

</header>

<!-- MAIN CONTENT WRAPPER -->

<main class="app-main">

    <div class="container-fluid px-0">

        <?php if (!empty($flash) && is_array($flash)): ?>

            <div
                class="alert alert-<?= e($flashType) ?> alert-dismissible fade show site-flash shadow-sm"
                role="alert"
            >

                <div class="d-flex align-items-center gap-2">

                    <?php if ($flashType === 'success'): ?>

                        <i class="bi bi-check-circle-fill fs-5"></i>

                    <?php elseif ($flashType === 'danger'): ?>

                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>

                    <?php elseif ($flashType === 'warning'): ?>

                        <i class="bi bi-exclamation-circle-fill fs-5"></i>

                    <?php else: ?>

                        <i class="bi bi-info-circle-fill fs-5"></i>

                    <?php endif; ?>

                    <div>
                        <?= e($flash['message'] ?? $flash['msg'] ?? 'Operation completed successfully.') ?>
                    </div>

                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>

            </div>

        <?php endif; ?>