<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Browse Events';
$basePath = app_url();

$q = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));

$categories = [];
$events = [];
$loadError = false;
$errorMessage = '';

/*
|--------------------------------------------------------------------------
| EVENT IMAGE MAPPING
|--------------------------------------------------------------------------
| Assign a relevant image based on the event title/category.
| These are display-only mappings; no database changes are required.
*/
if (!function_exists('campus_event_image')) {
    function campus_event_image(array $event): string
    {
        $title = strtolower(trim((string) ($event['title'] ?? '')));
        $category = strtolower(trim((string) ($event['category'] ?? '')));
        $text = $title . ' ' . $category;

        $images = [
            'seminar' => 'https://emp-media-prod.s3.amazonaws.com/20190620101618_5d0b5cf243548/thumbnails/1561025778_0_20190620101618_5d0b5cf243548.jpg',

            'workshop' => 'https://api.asapkerala.gov.in/uploads/course/image/Web-development-1723643575051-788843604.jpg',

            'sports' => 'https://scontent.fmnl17-4.fna.fbcdn.net/v/t39.30808-6/865945162_1578262454105566_1870471755244472758_n.jpg?stp=dst-jpg_tt6&cstp=mx2048x1536&ctp=s2048x1536&_nc_cat=107&_nc_map=urlgen_bucketless&ccb=1-7&_nc_sid=833d8c&_nc_eui2=AeE41D9xweh527idimI1-3KjCowDGtcmX-IKjAMa1yZf4jBiE9eMdPXdJMafc-o-Sa96esffbatppW9KGq2G571Q&_nc_ohc=3M0vA0VjTFMQ7kNvwECj6eQ&_nc_oc=AdqVIsK2avZqniFEtilPrD05uTH69npbk08X8QtpcUQMtSWAP0FltB9bAnZ9vKz7twI&_nc_zt=23&_nc_ht=scontent.fmnl17-4.fna&_nc_gid=l7nsTP4KXE3MlXsijabWeg&_nc_ss=7b2a8&oh=00_AQT3QOjlbBMkWsZ-HGVtEAOjWbQGt1kjaKo1lxTn4OS1AQ&oe=6AE8DCE6',

            'hackathon' => 'https://www.paterostechnologicalcollege.edu.ph/ASSETS/IMAGES/IICT/IICT6.jpg',
        ];

        if (strpos($text, 'hackathon') !== false) {
            return $images['hackathon'];
        }

        if (
            strpos($text, 'sport') !== false ||
            strpos($text, 'athletic') !== false ||
            strpos($text, 'basketball') !== false ||
            strpos($text, 'volleyball') !== false
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

try {
    $categoryStmt = $pdo->query("
        SELECT DISTINCT category
        FROM events
        WHERE status = 'upcoming'
          AND category IS NOT NULL
          AND category <> ''
        ORDER BY category ASC
    ");

    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

    $where = [
        "e.status = 'upcoming'",
        "e.event_date >= CURDATE()"
    ];

    $params = [];

    if ($q !== '') {
        $where[] = "(
            e.title LIKE :title_q
            OR e.description LIKE :description_q
            OR e.venue LIKE :venue_q
            OR e.organizer LIKE :organizer_q
        )";

        $searchTerm = '%' . $q . '%';

        $params['title_q'] = $searchTerm;
        $params['description_q'] = $searchTerm;
        $params['venue_q'] = $searchTerm;
        $params['organizer_q'] = $searchTerm;
    }

    if ($category !== '' && in_array($category, $categories, true)) {
        $where[] = 'e.category = :category';
        $params['category'] = $category;
    }

    $sql = "
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
            (
                SELECT COUNT(*)
                FROM registrations r
                WHERE r.event_id = e.id
                  AND r.status = 'registered'
            ) AS registered_count
        FROM events e
        WHERE " . implode(' AND ', $where) . "
        ORDER BY e.event_date ASC, e.start_time ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    error_log('Events Page Error: ' . $ex->getMessage());
    $loadError = true;
    $errorMessage = 'Unable to load events. Please check your database configuration and events table.';
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* =========================================================
   CAMPUS EVENT HUB — PREMIUM EMERALD EVENT EXPLORER
========================================================= */

.events-page-section {
    --green: #42ffad;
    --green-light: #a0ffd0;
    --page-bg: #031713;
    --card-bg: #092923;

    position: relative;
    isolation: isolate;
    min-height: 75vh;
    overflow: hidden;
    padding: 36px 0 70px;
    background:
        radial-gradient(ellipse at 5% 5%, rgba(0, 255, 157, .10), transparent 30%),
        radial-gradient(ellipse at 95% 40%, rgba(0, 190, 145, .09), transparent 34%),
        linear-gradient(135deg, #031713 0%, #06251e 52%, #031713 100%);
    color: #f2fff8;
}

.events-page-section .container {
    position: relative;
    z-index: 1;
    max-width: 1450px;
}

.events-page-section,
.events-page-section * {
    box-sizing: border-box;
}

.events-page-section::before,
.events-page-section::after {
    position: absolute;
    z-index: -1;
    width: 340px;
    height: 340px;
    border-radius: 50%;
    content: "";
    pointer-events: none;
    filter: blur(110px);
    opacity: .13;
}

.events-page-section::before {
    top: 250px;
    left: -220px;
    background: #00ffa3;
}

.events-page-section::after {
    right: -230px;
    bottom: 100px;
    background: #00c8a0;
}

/* Keep the event page typography consistent */
.events-page-section h1,
.events-page-section h2,
.events-page-section h3,
.events-hero h1 {
    font-family: inherit;
}

/* =========================================================
   HERO — MATCHES EVENT DETAILS DESIGN
========================================================= */

.events-hero {
    position: relative;
    isolation: isolate;
    display: flex;
    min-height: 290px;
    align-items: center;
    overflow: hidden;
    padding: 55px 0;
    border-bottom: 1px solid rgba(66, 255, 173, .35);
    background:
        linear-gradient(90deg, rgba(2, 24, 18, .97) 0%, rgba(3, 38, 29, .85) 55%, rgba(2, 24, 18, .55) 100%),
        url("https://www.paterostechnologicalcollege.edu.ph/ASSETS/IMAGES/IICT/IICT6.jpg") center 42% / cover no-repeat !important;
    color: #fff;
}

.events-hero::before {
    position: absolute;
    z-index: -1;
    inset: 0;
    background: linear-gradient(110deg, transparent 55%, rgba(66, 255, 173, .08));
    content: "";
}

.events-hero::after {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, #21ffad, #c5ffe2, #21ffad, transparent);
    box-shadow: 0 0 20px rgba(33, 255, 173, .6);
    content: "";
}

.events-hero .container {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 1450px;
}

.events-hero .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 17px;
    padding: 9px 14px;
    border: 1px solid rgba(66, 255, 173, .3);
    border-radius: 50px;
    background: rgba(13, 105, 74, .4);
    color: #9dffd0 !important;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 2.4px;
}

.events-hero .eyebrow::before {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #42ffad;
    box-shadow: 0 0 12px #42ffad;
    content: "";
}

.events-hero h1 {
    max-width: 850px;
    margin: 0 0 14px;
    color: #fff !important;
    font-size: clamp(35px, 5vw, 58px);
    font-weight: 900;
    letter-spacing: -1.8px;
    line-height: 1.12;
    text-shadow: 0 0 30px rgba(66, 255, 173, .12);
}

.events-hero h1 span {
    color: #71ffbd;
}

.events-hero p {
    max-width: 640px;
    margin: 0;
    color: #c5e9d9 !important;
    font-size: 16px;
    line-height: 1.8;
}

/* =========================================================
   FILTER PANEL
========================================================= */

.events-filter-panel {
    display: flex;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 16px;
    margin: 0 0 35px;
    padding: 22px;
    border: 1px solid rgba(66, 255, 173, .24);
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(10, 43, 34, .96), rgba(4, 29, 24, .96));
    box-shadow: 0 15px 45px rgba(0, 0, 0, .16), inset 0 1px rgba(255, 255, 255, .035);
}

.events-filter-panel .filter-search {
    flex: 2 1 260px;
    min-width: 0;
}

.events-filter-panel .filter-category {
    flex: 1 1 190px;
    min-width: 0;
}

.events-filter-panel label {
    display: block;
    margin-bottom: 9px;
    color: #b8f9d9;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .4px;
}

.events-filter-panel .form-control,
.events-filter-panel .form-select {
    width: 100%;
    min-height: 49px;
    border: 1px solid #285347;
    border-radius: 12px;
    background-color: #061e18;
    color: #f1fff8;
    font-size: 14px;
}

.events-filter-panel .form-control::placeholder {
    color: #7fa99a;
}

.events-filter-panel .form-select option {
    background: #08251d;
    color: #fff;
}

.events-filter-panel .form-control:focus,
.events-filter-panel .form-select:focus {
    border-color: #42ffad;
    outline: none;
    box-shadow: 0 0 0 3px rgba(66, 255, 173, .11);
}

.events-search-btn,
.btn-clear-filters {
    display: inline-flex;
    min-height: 49px;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 12px 19px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 900;
    text-decoration: none;
    white-space: nowrap;
    transition: .25s ease;
}

.events-search-btn {
    border: 1px solid #42ffad;
    background: linear-gradient(135deg, #a6ffd0, #37f5a4);
    color: #032319;
    box-shadow: 0 0 18px rgba(66, 255, 173, .13);
}

.events-search-btn:hover {
    transform: translateY(-2px);
    border-color: #d0ffe5;
    background: #b9ffda;
    color: #032319;
    box-shadow: 0 0 24px rgba(66, 255, 173, .28);
}

.btn-clear-filters {
    border: 1px solid #345e50;
    background: rgba(255, 255, 255, .035);
    color: #d7f8e8;
}

.btn-clear-filters:hover {
    border-color: #42ffad;
    background: rgba(66, 255, 173, .09);
    color: #fff;
}

/* =========================================================
   RESULTS HEADER
========================================================= */

.results-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 24px;
}

.results-heading h2 {
    margin: 0 0 7px;
    color: #f1fff8;
    font-size: clamp(22px, 3vw, 29px);
    font-weight: 900;
    letter-spacing: -.6px;
}

.results-heading > div > span {
    color: #94b9a8;
    font-size: 13px;
}

/* =========================================================
   EVENT GRID AND CARDS
========================================================= */

.events-grid {
    align-items: stretch;
}

.events-grid > div {
    display: flex;
    min-width: 0;
}

.events-grid .event-card {
    --event-accent: #42ffad;
    --event-soft: rgba(66, 255, 173, .12);

    position: relative;
    display: flex;
    width: 100%;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(66, 255, 173, .23);
    border-radius: 20px;
    background:
        radial-gradient(circle at 100% 0%, var(--event-soft), transparent 42%),
        linear-gradient(155deg, #0c3026, #061d17 80%);
    box-shadow: 0 12px 30px rgba(0, 0, 0, .19), inset 0 1px rgba(255, 255, 255, .04);
    transition: transform .28s ease, border-color .28s ease, box-shadow .28s ease;
}

.events-grid .event-card::before {
    position: absolute;
    z-index: 3;
    top: 0;
    right: 0;
    left: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--event-accent), #fff, var(--event-accent), transparent);
    content: "";
    opacity: .9;
}

.events-grid .event-card:hover {
    transform: translateY(-6px);
    border-color: var(--event-accent);
    box-shadow: 0 0 24px var(--event-soft), 0 20px 45px rgba(0, 0, 0, .28);
}

.events-grid .event-card.category-seminar {
    --event-accent: #63ffb2;
    --event-soft: rgba(99, 255, 178, .13);
}

.events-grid .event-card.category-workshop {
    --event-accent: #c0a0ff;
    --event-soft: rgba(192, 160, 255, .13);
}

.events-grid .event-card.category-sports {
    --event-accent: #76caff;
    --event-soft: rgba(118, 202, 255, .13);
}

.events-grid .event-card.category-competition {
    --event-accent: #ffc47c;
    --event-soft: rgba(255, 196, 124, .13);
}

.events-grid .event-card.category-default {
    --event-accent: #6dffd0;
    --event-soft: rgba(109, 255, 208, .11);
}

/* Actual event photo */
.event-image {
    position: relative;
    height: 210px;
    overflow: hidden;
    background: #09251d;
}

.event-image::after {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(1, 20, 14, .03) 30%, rgba(2, 23, 17, .9) 100%);
    content: "";
    pointer-events: none;
}

.event-image img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform .6s ease, filter .4s ease;
}

.event-card:hover .event-image img {
    transform: scale(1.055);
    filter: saturate(1.08);
}

.event-image-overlay {
    position: absolute;
    z-index: 2;
    right: 16px;
    bottom: 15px;
    left: 16px;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
}

.events-grid .category-pill {
    display: inline-flex;
    max-width: 72%;
    align-items: center;
    padding: 8px 12px;
    overflow-wrap: anywhere;
    border: 1px solid color-mix(in srgb, var(--event-accent) 50%, transparent);
    border-radius: 30px;
    background: rgba(3, 35, 26, .84);
    color: var(--event-accent);
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 1px;
    text-transform: uppercase;
    backdrop-filter: blur(12px);
}

.events-grid .event-date-badge {
    display: flex;
    min-width: 59px;
    flex-shrink: 0;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 9px 10px;
    border: 1px solid rgba(117, 255, 193, .35);
    border-radius: 12px;
    background: rgba(3, 36, 27, .88);
    color: #b5ffda;
    box-shadow: 0 0 18px rgba(66, 255, 173, .08);
    backdrop-filter: blur(12px);
}

.events-grid .event-date-badge strong {
    font-size: 24px;
    font-weight: 900;
    line-height: 1.05;
}

.events-grid .event-date-badge small {
    margin-top: 4px;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* Card content */
.events-grid .event-card-body {
    display: flex;
    flex: 1;
    flex-direction: column;
    padding: 20px;
}

.events-grid .event-card-body h3 {
    margin: 0 0 10px;
    color: #f0fff7;
    font-size: 21px;
    font-weight: 900;
    line-height: 1.4;
    overflow-wrap: anywhere;
    transition: color .25s ease;
}

.events-grid .event-card:hover .event-card-body h3 {
    color: var(--event-accent);
}

.events-grid .event-description {
    min-height: 48px;
    margin: 0;
    color: #a9c9ba;
    font-size: 13px;
    line-height: 1.8;
    overflow-wrap: anywhere;
}

.events-grid .event-meta {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 20px 0;
}

.events-grid .event-meta div {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    color: #c5e2d4;
    font-size: 13px;
    line-height: 1.6;
}

.events-grid .event-meta div > span:first-child {
    display: inline-flex;
    width: 21px;
    flex-shrink: 0;
    justify-content: center;
    color: var(--event-accent);
    font-size: 17px;
}

.events-grid .event-meta div > span:last-child {
    min-width: 0;
    overflow-wrap: anywhere;
}

/* Availability */
.events-grid .slot-line {
    margin-top: auto;
    padding-top: 16px;
    border-top: 1px solid rgba(190, 255, 220, .10);
}

.events-grid .slot-labels {
    margin-bottom: 10px;
    color: #c6e8d6;
    font-size: 12px;
    font-weight: 800;
}

.events-grid .slots-full {
    color: #ff9ba8;
}

.events-grid .slot-track {
    width: 100%;
    height: 7px;
    overflow: hidden;
    border-radius: 20px;
    background: #1a4134;
}

.events-grid .slot-track i {
    display: block;
    height: 100%;
    border-radius: 20px;
    background: linear-gradient(90deg, #20d99b, #a5ffd0);
    box-shadow: 0 0 12px rgba(66, 255, 173, .25);
    transition: width .5s ease;
}

/* Details button */
.events-grid .event-link {
    display: flex;
    min-height: 48px;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 20px;
    padding: 12px 15px;
    border: 1px solid rgba(66, 255, 173, .35);
    border-radius: 12px;
    background: rgba(66, 255, 173, .08);
    color: #85ffc3;
    font-size: 13px;
    font-weight: 900;
    text-decoration: none;
    transition: .25s ease;
}

.events-grid .event-link > span:last-child {
    font-size: 20px;
    transition: transform .25s ease;
}

.events-grid .event-link:hover {
    transform: translateY(-2px);
    border-color: #a6ffd0;
    background: linear-gradient(135deg, #a6ffd0, #42ffad);
    color: #032319;
    box-shadow: 0 0 22px rgba(66, 255, 173, .2);
}

.events-grid .event-link:hover > span:last-child {
    transform: translateX(4px);
}

/* =========================================================
   EMPTY / ERROR STATES
========================================================= */

.events-empty-state {
    padding: 52px 24px;
    border: 1px solid rgba(66, 255, 173, .22);
    border-radius: 20px;
    background: linear-gradient(145deg, rgba(10, 43, 34, .95), rgba(4, 27, 22, .95));
    text-align: center;
    box-shadow: 0 15px 40px rgba(0, 0, 0, .13);
}

.events-empty-state .empty-icon {
    margin-bottom: 12px;
    color: #69ffb7;
    font-size: 48px;
    text-shadow: 0 0 22px rgba(66, 255, 173, .4);
}

.events-empty-state h3 {
    margin-bottom: 10px;
    color: #f0fff7;
    font-weight: 900;
}

.events-empty-state p {
    max-width: 480px;
    margin: 0 auto 22px;
    color: #a9c9ba;
    line-height: 1.8;
}

.events-empty-state .btn-outline-green {
    border: 1px solid #42ffad;
    border-radius: 10px;
    color: #8dffca;
}

.events-empty-state .btn-outline-green:hover {
    background: #42ffad;
    color: #032319;
    box-shadow: 0 0 20px rgba(66, 255, 173, .22);
}

.events-error {
    padding: 20px;
    border: 1px solid rgba(255, 120, 140, .35);
    border-radius: 15px;
    background: rgba(90, 18, 32, .35);
    color: #ffdce2;
}

/* =========================================================
   RESPONSIVE LAYOUT
========================================================= */

@media (min-width: 1200px) {
    .events-grid .event-image {
        height: 220px;
    }
}

@media (max-width: 767px) {
    .events-hero {
        min-height: 250px;
        padding: 42px 0;
        background-position: center;
    }

    .events-hero h1 {
        font-size: 36px;
        letter-spacing: -1px;
    }

    .events-hero p {
        font-size: 14px;
    }

    .events-page-section {
        padding: 27px 0 45px;
    }

    .events-filter-panel {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
        padding: 17px;
        border-radius: 16px;
    }

    .events-filter-panel .filter-search,
    .events-filter-panel .filter-category {
        width: 100%;
        flex: 1 1 auto;
    }

    .events-search-btn,
    .btn-clear-filters {
        width: 100%;
    }

    .results-heading h2 {
        font-size: 23px;
    }

    .events-grid .event-card:hover {
        transform: translateY(-3px);
    }

    .event-image {
        height: 200px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .events-grid .event-card,
    .events-grid .event-image img,
    .events-grid .event-link,
    .events-grid .event-link > span:last-child {
        transition: none !important;
    }

    .events-grid .event-card:hover,
    .events-grid .event-link:hover {
        transform: none;
    }
}
</style>

<section class="page-hero events-hero">
    <div class="container">
        <span class="eyebrow">CAMPUS LIFE • PATEROS TECHNOLOGICAL COLLEGE</span>
        <h1>Explore Campus <span>Events</span></h1>
        <p>
            Discover seminars, workshops, competitions, and campus activities.
            Find your next experience and save your spot.
        </p>
    </div>
</section>

<section class="section-space events-page-section">
    <div class="container">

        <form
            class="filter-panel events-filter-panel"
            method="GET"
            action="<?= e($basePath) ?>events.php"
        >
            <div class="filter-search">
                <label for="q">Search events</label>
                <input
                    id="q"
                    class="form-control"
                    type="search"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Search seminars, sports, workshops..."
                    maxlength="150"
                >
            </div>

            <div class="filter-category">
                <label for="category">Event category</label>
                <select class="form-select" name="category" id="category">
                    <option value="">All categories</option>

                    <?php foreach ($categories as $c): ?>
                        <option
                            value="<?= e((string) $c) ?>"
                            <?= $category === $c ? 'selected' : '' ?>
                        >
                            <?= e((string) $c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn events-search-btn" type="submit">
                <span aria-hidden="true">⌕</span>
                Search Events
            </button>

            <?php if ($q !== '' || $category !== ''): ?>
                <a
                    class="btn btn-clear-filters"
                    href="<?= e($basePath) ?>events.php"
                >
                    Clear Filters
                </a>
            <?php endif; ?>
        </form>

        <?php if ($loadError): ?>

            <div class="events-error" role="alert">
                <strong>Unable to load events.</strong>
                <?= e($errorMessage) ?>
                Please check the PHP error log for the exact database error.
            </div>

        <?php else: ?>

            <div class="results-heading">
                <div>
                    <h2>
                        <?= count($events) ?>
                        Event<?= count($events) === 1 ? '' : 's' ?> Found
                    </h2>

                    <span>
                        <?= ($q !== '' || $category !== '')
                            ? 'Results matching your selected filters'
                            : 'Discover upcoming activities around campus' ?>
                    </span>
                </div>
            </div>

            <?php if (empty($events)): ?>

                <div class="empty-state events-empty-state">
                    <div class="empty-icon" aria-hidden="true">⌕</div>
                    <h3>No events found</h3>
                    <p>
                        There are no upcoming events matching your search.
                        Try a different keyword or clear your filters.
                    </p>

                    <a
                        href="<?= e($basePath) ?>events.php"
                        class="btn btn-outline-green"
                    >
                        View All Events
                    </a>
                </div>

            <?php else: ?>

                <div class="row g-4 events-grid">

                    <?php foreach ($events as $event): ?>

                        <?php
                        $capacity = max(0, (int) ($event['capacity'] ?? 0));
                        $registeredCount = max(0, (int) ($event['registered_count'] ?? 0));

                        $remaining = $capacity > 0
                            ? max(0, $capacity - $registeredCount)
                            : null;

                        $occupancy = $capacity > 0
                            ? min(100, (int) round(($registeredCount / $capacity) * 100))
                            : 0;

                        $isFull = $capacity > 0 && $remaining === 0;

                        $eventTimestamp = strtotime((string) ($event['event_date'] ?? ''));

                        $day = $eventTimestamp !== false
                            ? date('d', $eventTimestamp)
                            : '--';

                        $month = $eventTimestamp !== false
                            ? date('M', $eventTimestamp)
                            : 'TBA';

                        $description = trim((string) ($event['description'] ?? ''));

                        if ($description === '') {
                            $description = 'Discover this campus activity and view the event details.';
                        }

                        $shortDescription = function_exists('mb_strimwidth')
                            ? mb_strimwidth($description, 0, 130, '...')
                            : (strlen($description) > 130
                                ? substr($description, 0, 127) . '...'
                                : $description);

                        $categoryName = trim((string) ($event['category'] ?? ''));
                        $categoryLower = strtolower($categoryName);
                        $eventTitleLower = strtolower((string) ($event['title'] ?? ''));

                        if (
                            strpos($categoryLower, 'workshop') !== false ||
                            strpos($eventTitleLower, 'workshop') !== false
                        ) {
                            $categoryClass = 'category-workshop';
                        } elseif (
                            strpos($categoryLower, 'sport') !== false ||
                            strpos($categoryLower, 'athletic') !== false ||
                            strpos($eventTitleLower, 'sport') !== false ||
                            strpos($eventTitleLower, 'athletic') !== false
                        ) {
                            $categoryClass = 'category-sports';
                        } elseif (
                            strpos($categoryLower, 'competition') !== false ||
                            strpos($categoryLower, 'contest') !== false ||
                            strpos($eventTitleLower, 'hackathon') !== false
                        ) {
                            $categoryClass = 'category-competition';
                        } elseif (
                            strpos($categoryLower, 'seminar') !== false ||
                            strpos($categoryLower, 'conference') !== false ||
                            strpos($eventTitleLower, 'seminar') !== false ||
                            strpos($eventTitleLower, 'innovation') !== false
                        ) {
                            $categoryClass = 'category-seminar';
                        } else {
                            $categoryClass = 'category-default';
                        }

                        $imageUrl = campus_event_image($event);
                        ?>

                        <div class="col-md-6 col-xl-4">

                            <article class="event-card <?= e($categoryClass) ?> h-100">

                                <div class="event-image">
                                    <img
                                        src="<?= e($imageUrl) ?>"
                                        alt="<?= e((string) $event['title']) ?>"
                                        loading="lazy"
                                        referrerpolicy="no-referrer"
                                    >

                                    <div class="event-image-overlay">
                                        <span class="category-pill">
                                            <?= e($categoryName !== '' ? $categoryName : 'Campus Event') ?>
                                        </span>

                                        <span class="event-date-badge">
                                            <strong><?= e($day) ?></strong>
                                            <small><?= e($month) ?></small>
                                        </span>
                                    </div>
                                </div>

                                <div class="event-card-body">

                                    <h3><?= e((string) $event['title']) ?></h3>

                                    <p class="event-description">
                                        <?= e($shortDescription) ?>
                                    </p>

                                    <div class="event-meta">

                                        <div>
                                            <span aria-hidden="true">▦</span>
                                            <span>
                                                <?= e(format_date((string) $event['event_date'])) ?>
                                            </span>
                                        </div>

                                        <?php if (!empty($event['start_time'])): ?>
                                            <div>
                                                <span aria-hidden="true">◷</span>
                                                <span>
                                                    <?= e(format_time((string) $event['start_time'])) ?>

                                                    <?php if (!empty($event['end_time'])): ?>
                                                        – <?= e(format_time((string) $event['end_time'])) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <div>
                                            <span aria-hidden="true">⌖</span>
                                            <span>
                                                <?= e((string) ($event['venue'] ?? 'Venue to be announced')) ?>
                                            </span>
                                        </div>

                                    </div>

                                    <div class="slot-line">

                                        <div class="slot-labels">

                                            <?php if ($isFull): ?>

                                                <span class="slots-full">Event is full</span>

                                            <?php elseif ($capacity > 0): ?>

                                                <span>
                                                    <?= (int) $remaining ?> of <?= $capacity ?> spots available
                                                </span>

                                            <?php else: ?>

                                                <span>Check event details for availability</span>

                                            <?php endif; ?>

                                        </div>

                                        <?php if ($capacity > 0): ?>

                                            <div
                                                class="slot-track"
                                                role="progressbar"
                                                aria-label="Registration capacity"
                                                aria-valuemin="0"
                                                aria-valuemax="100"
                                                aria-valuenow="<?= $occupancy ?>"
                                            >
                                                <i style="width: <?= $occupancy ?>%"></i>
                                            </div>

                                        <?php endif; ?>

                                    </div>

                                    <a
                                        class="event-link"
                                        href="<?= e($basePath) ?>event-details.php?id=<?= (int) $event['id'] ?>"
                                    >
                                        <span>View Event Details</span>
                                        <span aria-hidden="true">→</span>
                                    </a>

                                </div>

                            </article>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>