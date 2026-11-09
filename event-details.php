<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Event Details';
$basePath = app_url();

if (!function_exists('event_detail_image')) {
    function event_detail_image(array $event): string
    {
        $title = strtolower(trim((string)($event['title'] ?? '')));
        $category = strtolower(trim((string)($event['category'] ?? '')));
        $text = $title . ' ' . $category;

        $images = [
            'seminar' => 'https://emp-media-prod.s3.amazonaws.com/20190620101618_5d0b5cf243548/thumbnails/1561025778_0_20190620101618_5d0b5cf243548.jpg',
            'workshop' => 'https://api.asapkerala.gov.in/uploads/course/image/Web-development-1723643575051-788843604.jpg',
            'sports' => 'assets/images/sportfest.jpg',
            'hackathon' => 'https://www.paterostechnologicalcollege.edu.ph/ASSETS/IMAGES/IICT/IICT6.jpg',
        ];

        if (strpos($text, 'hackathon') !== false) {
            return $images['hackathon'];
        }

        if (
            strpos($text, 'sport') !== false ||
            strpos($text, 'athletic') !== false
        ) {
            return $images['sports'];
        }

        if (
            strpos($text, 'web development') !== false ||
            strpos($text, 'web dev') !== false ||
            strpos($text, 'workshop') !== false
        ) {
            return $images['workshop'];
        }

        if (
            strpos($text, 'seminar') !== false ||
            strpos($text, 'innovation') !== false ||
            strpos($text, 'conference') !== false
        ) {
            return $images['seminar'];
        }

        return $images['hackathon'];
    }
}

$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$event = null;
$registeredCount = 0;
$loadError = false;

$currentUser = current_user();

$currentUserId = (int) ($currentUser['id'] ?? 0);

$isStudent = (
    $currentUserId > 0 &&
    strtolower(trim((string)($currentUser['role'] ?? ''))) === 'student'
);

$studentRegistrationStatus = '';
$studentRegistrationId = 0;
$studentWaitingStatus = '';

if (!$eventId || $eventId < 1) {
    http_response_code(404);
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT
                e.id,
                e.title,
                e.description,
                e.category,
                e.event_date,
                e.start_time,
                e.end_time,
                e.venue,
                e.organizer,
                e.capacity,
                e.status,
                e.created_by,
                u.full_name AS creator_name,
                (
                    SELECT COUNT(*)
                    FROM registrations r
                    WHERE r.event_id = e.id
                      AND r.status = 'registered'
                ) AS registered_count
            FROM events e
            LEFT JOIN users u ON u.id = e.created_by
            WHERE e.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$event) {
            http_response_code(404);
 } else {
    $registeredCount = max(
        0,
        (int)($event['registered_count'] ?? 0)
    );

    $pageTitle = (string)$event['title'] . ' | Event Details';

    /*
    |--------------------------------------------------------------------------
    | Check logged-in student's registration status
    |--------------------------------------------------------------------------
    */

    if ($isStudent) {

        // Check existing registration.
        $studentRegStmt = $pdo->prepare("
            SELECT id, status
            FROM registrations
            WHERE event_id = ?
              AND user_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $studentRegStmt->execute([
            (int)$event['id'],
            $currentUserId
        ]);

        $studentRegistration = $studentRegStmt->fetch(PDO::FETCH_ASSOC);

        if ($studentRegistration) {
            $studentRegistrationId = (int)$studentRegistration['id'];

            $studentRegistrationStatus = strtolower(
                trim((string)$studentRegistration['status'])
            );
        }

        // Check waiting-list status.
        $studentWaitingStmt = $pdo->prepare("
            SELECT status
            FROM event_waiting_list
            WHERE event_id = ?
              AND user_id = ?
            LIMIT 1
        ");

        $studentWaitingStmt->execute([
            (int)$event['id'],
            $currentUserId
        ]);

        $studentWaitingStatus = strtolower(
            trim((string)($studentWaitingStmt->fetchColumn() ?: ''))
        );
    }
}
    } catch (PDOException $ex) {
        error_log('Event Details Error: ' . $ex->getMessage());
        $loadError = true;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ==========================================
   CAMPUS EVENT HUB - GLASS EVENT DETAILS
========================================== */

.event-details-page {
    --mint: #66ffc0;
    --neon: #26f6a0;
    --text: #f3fff9;
    --muted: #a9c8bc;
    --glass: rgba(11, 45, 37, .72);
    --line: rgba(117, 255, 204, .25);

    position: relative;
    isolation: isolate;
    min-height: 100vh;
    padding: 24px 0 60px;
    overflow: hidden;
    color: var(--text);
    background:
        radial-gradient(ellipse at 15% 15%, rgba(0, 255, 166, .12), transparent 36%),
        radial-gradient(ellipse at 90% 55%, rgba(0, 151, 135, .13), transparent 38%),
        linear-gradient(145deg, #041813, #06271f 48%, #031610);
}

.event-details-page,
.event-details-page * {
    box-sizing: border-box;
}

.event-details-page::before,
.event-details-page::after {
    position: absolute;
    z-index: -1;
    content: "";
    width: 340px;
    height: 340px;
    border-radius: 50%;
    filter: blur(110px);
    opacity: .18;
    pointer-events: none;
}

.event-details-page::before {
    top: 160px;
    left: -220px;
    background: #00ffa2;
}

.event-details-page::after {
    top: 700px;
    right: -220px;
    background: #008c75;
}

.event-details-page .details-container {
    width: min(100% - 40px, 1440px);
    margin: 0 auto;
}

.event-details-page a {
    text-decoration: none;
}

.event-details-page .back-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
    padding: 11px 17px;
    border: 1px solid var(--line);
    border-radius: 999px;
    background: rgba(6, 37, 29, .75);
    color: #dffff0;
    font-size: 14px;
    font-weight: 700;
    backdrop-filter: blur(15px);
    transition: .25s ease;
}

.event-details-page .back-link:hover {
    border-color: var(--mint);
    background: rgba(24, 103, 75, .5);
    box-shadow: 0 0 22px rgba(38, 246, 160, .13);
    transform: translateX(-3px);
}

/* Hero banner */

.event-details-page .event-hero {
    position: relative;
    isolation: isolate;
    display: flex;
    min-height: 350px;
    align-items: flex-end;
    overflow: hidden;
    padding: clamp(24px, 4vw, 48px);
    border: 1px solid rgba(100, 255, 194, .42);
    border-radius: 25px;
    background-color: #092b21;
    background-image:
        linear-gradient(
            90deg,
            rgba(2, 21, 16, .95) 0%,
            rgba(3, 31, 23, .78) 43%,
            rgba(3, 25, 20, .2) 100%
        ),
        var(--event-cover);
    background-size: cover;
    background-position: center;
    box-shadow:
        0 18px 50px rgba(0, 0, 0, .22),
        inset 0 1px 0 rgba(255, 255, 255, .12);
}

.event-details-page .event-hero::before {
    position: absolute;
    z-index: -1;
    inset: 0;
    content: "";
    background: linear-gradient(
        0deg,
        rgba(1, 20, 15, .8),
        transparent 75%
    );
}

.event-details-page .event-hero::after {
    position: absolute;
    right: -50px;
    bottom: -110px;
    width: 270px;
    height: 270px;
    content: "";
    border: 1px solid rgba(102, 255, 192, .3);
    border-radius: 50%;
    box-shadow:
        0 0 0 24px rgba(102, 255, 192, .035),
        0 0 0 48px rgba(102, 255, 192, .025);
    pointer-events: none;
}

.event-details-page .hero-content {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 900px;
}

.event-details-page .hero-category {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 13px;
    padding: 8px 14px;
    border: 1px solid rgba(105, 255, 194, .38);
    border-radius: 999px;
    background: rgba(0, 180, 116, .22);
    color: #a9ffd7;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    backdrop-filter: blur(12px);
}

.event-details-page .hero-content h1 {
    margin: 0 0 20px;
    color: #fff;
    font-size: clamp(32px, 4.2vw, 58px);
    font-weight: 900;
    line-height: 1.12;
    letter-spacing: -1.5px;
    overflow-wrap: anywhere;
    text-shadow: 0 4px 24px rgba(0, 0, 0, .35);
}

.event-details-page .hero-content h1 span {
    color: var(--mint);
    text-shadow: 0 0 24px rgba(38, 246, 160, .22);
}

.event-details-page .hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 14px 26px;
}

.event-details-page .hero-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #f0fff8;
    font-size: 15px;
    font-weight: 600;
}

.event-details-page .hero-meta-item .meta-icon {
    color: var(--mint);
    font-size: 20px;
}

/* Main columns */

.event-details-page .details-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.8fr) minmax(300px, .9fr);
    align-items: start;
    gap: 24px;
    margin-top: 25px;
}

.event-details-page .glass-card {
    min-width: 0;
    border: 1px solid rgba(116, 255, 205, .28);
    border-radius: 23px;
    background:
        linear-gradient(135deg, rgba(24, 83, 68, .36), rgba(4, 34, 27, .66));
    box-shadow:
        0 18px 45px rgba(0, 0, 0, .17),
        inset 0 1px 0 rgba(255, 255, 255, .08);
    backdrop-filter: blur(22px);
    -webkit-backdrop-filter: blur(22px);
}

.event-details-page .about-card {
    padding: clamp(22px, 3vw, 34px);
}

.event-details-page .section-heading {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 0 0 18px;
    color: #f4fff9;
    font-size: clamp(24px, 2.5vw, 34px);
    font-weight: 900;
    letter-spacing: -.6px;
}

.event-details-page .heading-icon {
    display: grid;
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    place-items: center;
    border: 1px solid rgba(102, 255, 192, .35);
    border-radius: 15px;
    background: rgba(38, 246, 160, .13);
    color: var(--mint);
    font-size: 23px;
    box-shadow: 0 0 20px rgba(38, 246, 160, .08);
}

.event-details-page .event-description {
    margin: 0;
    color: #d1e8de;
    font-size: 15px;
    line-height: 1.9;
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.event-details-page .detail-divider {
    height: 1px;
    margin: 25px 0;
    border: 0;
    background: linear-gradient(
        90deg,
        rgba(102, 255, 192, .3),
        rgba(255, 255, 255, .07),
        transparent
    );
}

/* Information tiles */

.event-details-page .info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px;
}

.event-details-page .info-tile {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 13px;
    padding: 17px;
    border: 1px solid rgba(116, 255, 205, .17);
    border-radius: 16px;
    background: rgba(3, 35, 27, .48);
    transition: border-color .25s ease, background .25s ease;
}

.event-details-page .info-tile:hover {
    border-color: rgba(102, 255, 192, .48);
    background: rgba(14, 77, 57, .45);
}

.event-details-page .info-icon {
    display: grid;
    width: 47px;
    height: 47px;
    flex-shrink: 0;
    place-items: center;
    border: 1px solid rgba(102, 255, 192, .3);
    border-radius: 50%;
    background: rgba(14, 167, 107, .24);
    color: var(--mint);
    font-size: 20px;
}

.event-details-page .info-copy {
    min-width: 0;
}

.event-details-page .info-copy small {
    display: block;
    margin-bottom: 5px;
    color: #9fc5b4;
    font-size: 12px;
    font-weight: 600;
}

.event-details-page .info-copy strong {
    display: block;
    color: #f0fff7;
    font-size: 14px;
    font-weight: 800;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

/* Tags */

.event-details-page .event-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    margin-top: 23px;
}

.event-details-page .event-tag {
    padding: 8px 12px;
    border: 1px solid rgba(102, 255, 192, .22);
    border-radius: 999px;
    background: rgba(4, 43, 32, .6);
    color: #b9ffdc;
    font-size: 12px;
    font-weight: 700;
}

/* Registration card */

.event-details-page .registration-card {
    position: sticky;
    top: 20px;
    overflow: hidden;
    padding: 29px;
}

.event-details-page .registration-card::before {
    display: block;
    height: 3px;
    margin: -29px -29px 26px;
    content: "";
    background: linear-gradient(90deg, transparent, #26f6a0, #d1ffe7, #26f6a0, transparent);
    box-shadow: 0 0 18px rgba(38, 246, 160, .4);
}

.event-details-page .registration-eyebrow {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 15px;
    color: var(--mint);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 1.7px;
    text-transform: uppercase;
}

.event-details-page .registration-card h2 {
    margin: 0 0 20px;
    color: #f4fff9;
    font-size: clamp(24px, 2.4vw, 32px);
    font-weight: 900;
    line-height: 1.2;
}

.event-details-page .spots-row {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 9px;
    margin-bottom: 18px;
}

.event-details-page .spots-number {
    color: var(--mint);
    font-size: clamp(48px, 5vw, 70px);
    font-weight: 900;
    line-height: 1;
    letter-spacing: -2px;
    text-shadow: 0 0 24px rgba(38, 246, 160, .18);
}

.event-details-page .spots-label {
    color: #d1e8de;
    font-size: 13px;
    font-weight: 600;
}

.event-details-page .capacity-track {
    height: 9px;
    margin-bottom: 13px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, .05);
    border-radius: 999px;
    background: rgba(179, 219, 201, .16);
}

.event-details-page .capacity-track span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #13d98c, #9dffd1);
    box-shadow: 0 0 15px rgba(38, 246, 160, .45);
}

.event-details-page .registration-note {
    margin: 0 0 23px;
    color: #afcfc0;
    font-size: 13px;
    line-height: 1.7;
}

.event-details-page .register-button {
    display: flex;
    width: 100%;
    min-height: 58px;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 15px 18px;
    border: 1px solid rgba(199, 255, 224, .6);
    border-radius: 15px;
    background: linear-gradient(110deg, #9dffbf, #25efa1);
    color: #04271b;
    font-size: 14px;
    font-weight: 900;
    text-align: center;
    box-shadow: 0 8px 25px rgba(38, 246, 160, .18);
    transition: transform .25s ease, box-shadow .25s ease;
}

.event-details-page .register-button:hover {
    transform: translateY(-3px);
    color: #032319;
    box-shadow: 0 10px 32px rgba(38, 246, 160, .32);
}

.event-details-page .register-button.is-disabled {
    border-color: rgba(255, 255, 255, .12);
    background: rgba(150, 170, 160, .15);
    color: #a9c0b5;
    box-shadow: none;
    cursor: not-allowed;
}

.event-details-page .registration-footnote {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-top: 17px;
    color: #9fc5b4;
    font-size: 12px;
    line-height: 1.7;
}

.event-details-page .registration-footnote span {
    color: var(--mint);
}

/* Error / missing event */

.event-details-page .message-card {
    padding: 45px 25px;
    border: 1px solid var(--line);
    border-radius: 22px;
    background: var(--glass);
    text-align: center;
    backdrop-filter: blur(18px);
}

.event-details-page .message-card h2 {
    margin-bottom: 12px;
    color: #fff;
    font-weight: 900;
}

.event-details-page .message-card p {
    color: var(--muted);
}

/* Responsive */

@media (max-width: 1000px) {
    .event-details-page .details-layout {
        grid-template-columns: minmax(0, 1fr);
    }

    .event-details-page .registration-card {
        position: relative;
        top: auto;
    }
}

@media (max-width: 600px) {
    .event-details-page {
        padding-top: 16px;
    }

    .event-details-page .details-container {
        width: min(100% - 24px, 1440px);
    }

    .event-details-page .event-hero {
        min-height: 350px;
        padding: 23px;
        border-radius: 19px;
        background-position: center;
    }

    .event-details-page .hero-content h1 {
        font-size: 35px;
        letter-spacing: -.7px;
    }

    .event-details-page .hero-meta {
        flex-direction: column;
        gap: 12px;
    }

    .event-details-page .hero-meta-item {
        font-size: 13px;
    }

    .event-details-page .details-layout {
        gap: 17px;
        margin-top: 17px;
    }

    .event-details-page .about-card,
    .event-details-page .registration-card {
        padding: 21px;
        border-radius: 19px;
    }

    .event-details-page .registration-card::before {
        margin: -21px -21px 22px;
    }

    .event-details-page .section-heading {
        font-size: 25px;
    }

    .event-details-page .heading-icon {
        width: 42px;
        height: 42px;
    }

    .event-details-page .info-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .event-details-page .info-tile {
        padding: 14px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .event-details-page *,
    .event-details-page *::before,
    .event-details-page *::after {
        animation: none !important;
        transition: none !important;
        scroll-behavior: auto !important;
    }
}
</style>

<section class="event-details-page">
    <div class="details-container">

        <a class="back-link" href="<?= e($basePath) ?>events.php">
            <span aria-hidden="true">←</span>
            Back to events
        </a>

        <?php if ($loadError): ?>

            <div class="message-card">
                <h2>Unable to load event</h2>
                <p>There was a problem retrieving the event information. Please try again later.</p>
            </div>

        <?php elseif (!$event): ?>

            <div class="message-card">
                <h2>Event not found</h2>
                <p>This event may have been removed or the event link is invalid.</p>
                <a class="register-button" href="<?= e($basePath) ?>events.php">
                    Browse available events →
                </a>
            </div>

        <?php else: ?>

            <?php
            $title = trim((string)($event['title'] ?? 'Campus Event'));
            $categoryName = trim((string)($event['category'] ?? ''));
            $description = trim((string)($event['description'] ?? ''));
            $venue = trim((string)($event['venue'] ?? ''));
            $organizer = trim((string)(
                $event['organizer'] ?: ($event['creator_name'] ?? '')
            ));

            if ($categoryName === '') {
                $categoryName = 'Campus Event';
            }

            if ($description === '') {
                $description = 'Discover this campus activity and learn more about what to expect.';
            }

            $dateValue = (string)($event['event_date'] ?? '');
            $dateTimestamp = strtotime($dateValue);
            $formattedDate = $dateTimestamp !== false
                ? date('F j, Y', $dateTimestamp)
                : 'Date to be announced';

            $startTime = !empty($event['start_time'])
                ? format_time((string)$event['start_time'])
                : 'Time to be announced';

            $endTime = !empty($event['end_time'])
                ? format_time((string)$event['end_time'])
                : '';

            $timeDisplay = $startTime;
            if ($endTime !== '') {
                $timeDisplay .= ' – ' . $endTime;
            }

            $capacity = max(0, (int)($event['capacity'] ?? 0));
            $remaining = $capacity > 0
                ? max(0, $capacity - $registeredCount)
                : null;

            $occupancy = $capacity > 0
                ? min(100, (int)round(($registeredCount / $capacity) * 100))
                : 0;

            $isFull = $capacity > 0 && $remaining === 0;
            $isPast = $dateTimestamp !== false
                && date('Y-m-d', $dateTimestamp) < date('Y-m-d');

            $eventStatus = strtolower((string)($event['status'] ?? ''));
            $isUnavailable = $isPast || $eventStatus !== 'upcoming';

            $imageUrl = event_detail_image($event);
            $safeImageUrl = htmlspecialchars(
                $imageUrl,
                ENT_QUOTES,
                'UTF-8'
            );

            $highlightTitle = preg_replace(
                '/(seminar|workshop|hackathon|sports|festival)/i',
                '<span>$1</span>',
                htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                1
            );

            $tags = [];
            foreach (preg_split('/[\s,]+/', $categoryName) ?: [] as $word) {
                $word = trim($word);
                if ($word !== '') {
                    $tags[] = '#' . preg_replace('/[^a-zA-Z0-9_-]/', '', $word);
                }
            }
            $tags = array_slice(array_unique($tags), 0, 4);
            ?>

            <header
                class="event-hero"
                style="--event-cover: url('<?= $safeImageUrl ?>');"
            >
                <div class="hero-content">
                    <span class="hero-category">
                        <span aria-hidden="true">✦</span>
                        <?= e($categoryName) ?>
                    </span>

                    <h1><?= $highlightTitle ?></h1>

                    <div class="hero-meta">
                        <div class="hero-meta-item">
                            <span class="meta-icon" aria-hidden="true">▦</span>
                            <span><?= e($formattedDate) ?></span>
                        </div>

                        <div class="hero-meta-item">
                            <span class="meta-icon" aria-hidden="true">◷</span>
                            <span><?= e($timeDisplay) ?></span>
                        </div>

                        <div class="hero-meta-item">
                            <span class="meta-icon" aria-hidden="true">⌖</span>
                            <span><?= e($venue !== '' ? $venue : 'Venue to be announced') ?></span>
                        </div>
                    </div>

                    <?php if ($tags): ?>
                        <div class="event-tags">
                            <?php foreach ($tags as $tag): ?>
                                <span class="event-tag"><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </header>

            <div class="details-layout">

                <article class="glass-card about-card">
                    <h2 class="section-heading">
                        <span class="heading-icon" aria-hidden="true">✦</span>
                        About this event
                    </h2>

                    <p class="event-description"><?= e($description) ?></p>

                    <hr class="detail-divider">

                    <div class="info-grid">
                        <div class="info-tile">
                            <span class="info-icon" aria-hidden="true">▦</span>
                            <div class="info-copy">
                                <small>Date</small>
                                <strong><?= e($formattedDate) ?></strong>
                            </div>
                        </div>

                        <div class="info-tile">
                            <span class="info-icon" aria-hidden="true">◷</span>
                            <div class="info-copy">
                                <small>Time</small>
                                <strong><?= e($timeDisplay) ?></strong>
                            </div>
                        </div>

                        <div class="info-tile">
                            <span class="info-icon" aria-hidden="true">⌖</span>
                            <div class="info-copy">
                                <small>Venue</small>
                                <strong><?= e($venue !== '' ? $venue : 'To be announced') ?></strong>
                            </div>
                        </div>

                        <div class="info-tile">
                            <span class="info-icon" aria-hidden="true">♙</span>
                            <div class="info-copy">
                                <small>Organizer</small>
                                <strong><?= e($organizer !== '' ? $organizer : 'Campus Organizer') ?></strong>
                            </div>
                        </div>
                    </div>
                </article>

                <aside class="glass-card registration-card">
                    <div class="registration-eyebrow">
                        <span aria-hidden="true">✦</span>
                        Save your spot
                    </div>

                    <h2>Join this event</h2>

                    <?php if ($capacity > 0): ?>

                        <div class="spots-row">
                            <span class="spots-number"><?= $remaining ?></span>
                            <span class="spots-label">
                                <?= $remaining === 1 ? 'spot' : 'spots' ?> left
                            </span>
                        </div>

                        <div
                            class="capacity-track"
                            role="progressbar"
                            aria-label="Event registration capacity"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="<?= $occupancy ?>"
                        >
                            <span style="width: <?= $occupancy ?>%"></span>
                        </div>

                        <p class="registration-note">
                            <?= $registeredCount ?> registered out of
                            <?= $capacity ?> available seats.
                        </p>

                    <?php else: ?>

                        <div class="spots-row">
                            <span class="spots-number">∞</span>
                            <span class="spots-label">capacity not specified</span>
                        </div>

                        <p class="registration-note">
                            Check the event details for availability information.
                        </p>

                    <?php endif; ?>

                    <?php if ($isUnavailable): ?>

    <span class="register-button is-disabled">
        <?= $isPast
            ? 'Event has Ended'
            : 'Registration Unavailable' ?>
    </span>

<?php elseif ($isStudent && $studentRegistrationStatus === 'registered'): ?>

    <a
        class="register-button"
        href="<?= e($basePath) ?>ticket.php?registration_id=<?= $studentRegistrationId ?>"
    >
        ✓ Already Registered - View Ticket →
    </a>

<?php elseif ($isStudent && $studentRegistrationStatus === 'attended'): ?>

    <span class="register-button is-disabled">
        ✓ Attendance Already Recorded
    </span>

<?php elseif ($isStudent && $studentWaitingStatus === 'waiting'): ?>

    <span class="register-button is-disabled">
        ⏳ Already on Waiting List
    </span>

<?php elseif ($isFull && $isStudent): ?>

    <a
        class="register-button"
        href="<?= e($basePath) ?>register-event.php?id=<?= (int)$event['id'] ?>"
    >
        <span aria-hidden="true">⏳</span>
        Join Waiting List
        <span aria-hidden="true">→</span>
    </a>

<?php elseif ($isFull): ?>

    <span class="register-button is-disabled">
        Event is Full
    </span>

<?php else: ?>

    <a
        class="register-button"
        href="<?= e($basePath) ?>register-event.php?id=<?= (int)$event['id'] ?>"
    >
        <span aria-hidden="true">♙</span>
        Register for Event
        <span aria-hidden="true">→</span>
    </a>

<?php endif; ?>

                    <div class="registration-footnote">
                        <span aria-hidden="true">ⓘ</span>
                        <span>Review the event date, time, and venue before registering.</span>
                    </div>
                </aside>

            </div>

        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>