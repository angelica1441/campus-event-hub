<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';
$basePath = app_url();

$events = [];
$loadError = false;

/*
|--------------------------------------------------------------------------
| EVENT IMAGE MAPPING
|--------------------------------------------------------------------------
*/

if (!function_exists('homepage_event_image')) {
    function homepage_event_image(array $event): string
    {
        $title = strtolower(trim((string) ($event['title'] ?? '')));
        $category = strtolower(trim((string) ($event['category'] ?? '')));

        $seminar = 'https://emp-media-prod.s3.amazonaws.com/20190620101618_5d0b5cf243548/thumbnails/1561025778_0_20190620101618_5d0b5cf243548.jpg';

        $workshop = 'https://api.asapkerala.gov.in/uploads/course/image/Web-development-1723643575051-788843604.jpg';

        $sports = 'https://scontent.fmnl17-4.fna.fbcdn.net/v/t39.30808-6/865945162_1578262454105566_1870471755244472758_n.jpg?stp=dst-jpg_tt6&cstp=mx2048x1536&ctp=s2048x1536&_nc_cat=107&_nc_map=urlgen_bucketless&ccb=1-7&_nc_sid=833d8c&_nc_eui2=AeE41D9xweh527idimI1-3KjCowDGtcmX-IKjAMa1yZf4jBiE9eMdPXdJMafc-o-Sa96esffbatppW9KGq2G571Q&_nc_ohc=3M0vA0VjTFMQ7kNvwECj6eQ&_nc_oc=AdqVIsK2avZqniFEtilPrD05uTH69npbk08X8QtpcUQMtSWAP0FltB9bAnZ9vKz7twI&_nc_zt=23&_nc_ht=scontent.fmnl17-4.fna&_nc_gid=l7nsTP4KXE3MlXsijabWeg&_nc_ss=7b2a8&oh=00_AQT3QOjlbBMkWsZ-HGVtEAOjWbQGt1kjaKo1lxTn4OS1AQ&oe=6AE8DCE6';

        $hackathon = 'https://www.paterostechnologicalcollege.edu.ph/ASSETS/IMAGES/IICT/IICT6.jpg';

        if (strpos($title, 'hackathon') !== false) {
            return $hackathon;
        }

        if (
            strpos($title, 'sport') !== false ||
            strpos($category, 'sport') !== false ||
            strpos($title, 'athletic') !== false ||
            strpos($category, 'athletic') !== false
        ) {
            return $sports;
        }

        if (
            strpos($title, 'web development') !== false ||
            strpos($title, 'web dev') !== false ||
            strpos($title, 'workshop') !== false ||
            strpos($category, 'workshop') !== false
        ) {
            return $workshop;
        }

        if (
            strpos($title, 'seminar') !== false ||
            strpos($title, 'innovation') !== false ||
            strpos($category, 'seminar') !== false
        ) {
            return $seminar;
        }

        return $hackathon;
    }
}

/*
|--------------------------------------------------------------------------
| LOAD UPCOMING EVENTS
|--------------------------------------------------------------------------
*/

try {
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
        WHERE e.status = 'upcoming'
          AND e.event_date >= CURDATE()
          AND (
              COALESCE(e.capacity, 0) <= 0
              OR (
                  SELECT COUNT(*)
                  FROM registrations r2
                  WHERE r2.event_id = e.id
                    AND r2.status = 'registered'
              ) < e.capacity
          )
        ORDER BY e.event_date ASC, e.start_time ASC
        LIMIT 6
    ";

    $stmt = $pdo->query($sql);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    error_log('Homepage Events Error: ' . $ex->getMessage());
    $loadError = true;
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* =========================================================
   CAMPUS EVENT HUB — PREMIUM GLASS HOMEPAGE
   ========================================================= */

.homepage {
    --green: #9cffb5;
    --green-bright: #64ffab;
    --green-deep: #0b3b2c;
    --glass: rgba(255, 255, 255, .055);
    --glass-border: rgba(220, 255, 235, .15);
    --muted: #b8d2c5;

    position: relative;
    isolation: isolate;
    overflow: hidden;
    color: #f4fff8;
    background:
        radial-gradient(ellipse at 12% 8%, rgba(32, 155, 104, .18), transparent 32%),
        radial-gradient(ellipse at 92% 48%, rgba(33, 142, 107, .13), transparent 35%),
        #061a15;
    font-family: inherit;
}

.homepage *,
.homepage *::before,
.homepage *::after {
    box-sizing: border-box;
}

.homepage a {
    text-decoration: none;
}

.homepage .container {
    position: relative;
    z-index: 1;
}

/* HERO */

.homepage .hero-section {
    position: relative;
    display: flex;
    align-items: center;
    min-height: 570px;
    padding: 86px 0 78px;
    overflow: hidden;
    background:
        linear-gradient(
            90deg,
            rgba(3, 23, 17, .96) 0%,
            rgba(3, 28, 20, .84) 48%,
            rgba(3, 25, 19, .48) 100%
        ),
        url("https://www.paterostechnologicalcollege.edu.ph/ASSETS/IMAGES/ABOUTUS/ptcfront.png")
        center 45% / cover no-repeat;
}

.homepage .hero-section::before {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(0deg, #061a15 0%, transparent 30%, transparent 85%, rgba(2, 18, 13, .2));
}

.homepage .hero-section::after {
    content: "";
    position: absolute;
    top: 10%;
    right: 5%;
    width: 330px;
    height: 330px;
    border-radius: 50%;
    background: rgba(100, 255, 171, .15);
    filter: blur(100px);
    pointer-events: none;
}

.homepage .hero-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, .8fr);
    align-items: center;
    gap: 38px;
}

.homepage .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: var(--green);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 2.2px;
}

.homepage .eyebrow-dot {
    width: 9px;
    height: 9px;
    flex-shrink: 0;
    border-radius: 50%;
    background: var(--green-bright);
    box-shadow: 0 0 14px var(--green-bright);
}

.homepage .hero-copy h1 {
    margin: 23px 0 19px;
    color: #fff;
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(45px, 6vw, 76px);
    font-weight: 800;
    line-height: 1.05;
    letter-spacing: -2px;
}

.homepage .hero-copy h1 em {
    color: var(--green);
    font-style: normal;
}

.homepage .hero-copy > p {
    max-width: 550px;
    margin: 0;
    color: #d1e7da;
    font-size: 15px;
    line-height: 1.9;
}

.homepage .hero-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    margin-top: 29px;
}

.homepage .btn-cream {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 49px;
    padding: 13px 21px;
    border: 1px solid var(--green);
    border-radius: 13px;
    background: var(--green);
    color: #08261b !important;
    font-size: 13px;
    font-weight: 900;
    box-shadow: 0 8px 25px rgba(100, 255, 171, .13);
    transition: transform .25s ease, box-shadow .25s ease, background .25s ease;
}

.homepage .btn-cream:hover {
    transform: translateY(-3px);
    background: #c4ffd4;
    box-shadow: 0 12px 30px rgba(100, 255, 171, .25);
}

.homepage .hero-text-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 45px;
    padding: 10px 13px;
    border: 1px solid rgba(255, 255, 255, .14);
    border-radius: 12px;
    background: rgba(255, 255, 255, .055);
    color: #f1fff6;
    font-size: 12px;
    font-weight: 800;
    backdrop-filter: blur(12px);
    transition: .25s ease;
}

.homepage .hero-text-link:hover {
    border-color: rgba(156, 255, 181, .6);
    background: rgba(156, 255, 181, .1);
    color: var(--green);
}

.homepage .hero-note {
    margin-top: 22px;
    color: #c3dbce;
    font-size: 12px;
}

/* HERO GLASS PANEL */

.homepage .hero-glass {
    padding: 26px;
    border: 1px solid rgba(255, 255, 255, .18);
    border-radius: 25px;
    background: linear-gradient(145deg, rgba(255,255,255,.13), rgba(255,255,255,.035));
    box-shadow: 0 25px 70px rgba(0,0,0,.2);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

.homepage .hero-glass-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 23px;
}

.homepage .hero-glass-label {
    color: #eafff0;
    font-size: 12px;
    font-weight: 800;
}

.homepage .live-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 10px;
    border: 1px solid rgba(156, 255, 181, .25);
    border-radius: 30px;
    background: rgba(156, 255, 181, .08);
    color: var(--green);
    font-size: 10px;
    font-weight: 900;
}

.homepage .live-pill::before {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--green-bright);
    box-shadow: 0 0 10px var(--green-bright);
}

.homepage .hero-glass h2 {
    margin: 0 0 10px;
    color: #fff;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 29px;
    font-weight: 800;
}

.homepage .hero-glass > p {
    margin: 0 0 22px;
    color: #c3dcd0;
    font-size: 13px;
    line-height: 1.8;
}

.homepage .hero-feature {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 0;
    border-top: 1px solid rgba(255, 255, 255, .1);
}

.homepage .hero-feature-icon {
    display: grid;
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    place-items: center;
    border: 1px solid rgba(156, 255, 181, .2);
    border-radius: 13px;
    background: rgba(156, 255, 181, .09);
    color: var(--green);
    font-size: 19px;
}

.homepage .hero-feature strong {
    display: block;
    color: #f4fff8;
    font-size: 12px;
    font-weight: 900;
}

.homepage .hero-feature span {
    display: block;
    margin-top: 4px;
    color: #b5d0c2;
    font-size: 11px;
}

/* BENEFITS STRIP */

.homepage .stats-strip {
    position: relative;
    padding: 25px 0;
    border-top: 1px solid rgba(156, 255, 181, .16);
    border-bottom: 1px solid rgba(156, 255, 181, .16);
    background: rgba(255, 255, 255, .025);
    backdrop-filter: blur(12px);
}

.homepage .stats-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.homepage .stats-grid > div {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 7px 18px;
    border-left: 2px solid rgba(156, 255, 181, .8);
}

.homepage .stats-grid strong {
    color: #f1fff5;
    font-size: 15px;
    font-weight: 900;
}

.homepage .stats-grid span {
    color: #adc9ba;
    font-size: 12px;
}

/* EVENTS SECTION */

.homepage .homepage-events {
    position: relative;
    padding: 80px 0;
    background:
        radial-gradient(ellipse at 0% 25%, rgba(45, 190, 122, .09), transparent 32%),
        radial-gradient(ellipse at 100% 70%, rgba(45, 145, 125, .08), transparent 32%),
        #071d17;
}

.homepage .section-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 35px;
}

.homepage .eyebrow-dark {
    color: var(--green);
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 2px;
}

.homepage .section-heading h2 {
    margin: 12px 0 10px;
    color: #f6fff8;
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(35px, 4vw, 49px);
    font-weight: 800;
    letter-spacing: -.8px;
}

.homepage .section-heading p {
    max-width: 580px;
    margin: 0;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.8;
}

.homepage .btn-outline-green {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-shrink: 0;
    min-height: 45px;
    padding: 12px 17px;
    border: 1px solid rgba(156, 255, 181, .45);
    border-radius: 12px;
    background: rgba(156, 255, 181, .06);
    color: #c2ffd2;
    font-size: 12px;
    font-weight: 900;
    backdrop-filter: blur(12px);
    transition: .25s ease;
}

.homepage .btn-outline-green:hover {
    transform: translateY(-2px);
    border-color: var(--green);
    background: var(--green);
    color: #062017;
}

/* EVENT GRID */

.homepage .homepage-event-grid {
    --bs-gutter-x: 24px;
    --bs-gutter-y: 25px;
}

/* GLASS EVENT CARD */

.homepage .event-card {
    --event-accent: #9cffb5;
    --event-rgb: 156, 255, 181;

    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, .16);
    border-radius: 22px;
    background: linear-gradient(
        145deg,
        rgba(255, 255, 255, .095),
        rgba(255, 255, 255, .035)
    );
    box-shadow: 0 16px 40px rgba(0, 0, 0, .15);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    transition: transform .3s ease, border-color .3s ease, box-shadow .3s ease;
}

.homepage .event-card:hover {
    transform: translateY(-7px);
    border-color: rgba(var(--event-rgb), .58);
    box-shadow:
        0 0 28px rgba(var(--event-rgb), .09),
        0 24px 50px rgba(0, 0, 0, .25);
}

/* EVENT CATEGORY THEMES */

.homepage .category-seminar {
    --event-accent: #9cffb5;
    --event-rgb: 156, 255, 181;
}

.homepage .category-workshop {
    --event-accent: #d6b0ff;
    --event-rgb: 197, 150, 255;
}

.homepage .category-sports {
    --event-accent: #80d9ff;
    --event-rgb: 104, 204, 255;
}

.homepage .category-competition {
    --event-accent: #ffd09a;
    --event-rgb: 255, 190, 121;
}

.homepage .category-default {
    --event-accent: #9cffb5;
    --event-rgb: 156, 255, 181;
}

/* EVENT PHOTO HEADER */

.homepage .event-image {
    position: relative;
    width: 100%;
    height: 235px;
    flex-shrink: 0;
    overflow: hidden;
    background: #0b3024;
}

.homepage .event-image img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform .65s cubic-bezier(.2, .7, .2, 1);
}

.homepage .event-card:hover .event-image img {
    transform: scale(1.075);
}

.homepage .event-image::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        180deg,
        rgba(1, 12, 8, .55) 0%,
        rgba(1, 12, 8, .02) 40%,
        rgba(1, 12, 8, .18) 55%,
        rgba(1, 12, 8, .82) 100%
    );
    pointer-events: none;
}

.homepage .event-image-overlay {
    position: absolute;
    z-index: 2;
    inset: 0;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    padding: 15px;
    pointer-events: none;
}

.homepage .category-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    max-width: calc(100% - 80px);
    padding: 8px 12px;
    border: 1px solid rgba(255, 255, 255, .24);
    border-radius: 30px;
    background: rgba(4, 27, 19, .58);
    color: #fff;
    font-size: 10px;
    font-weight: 900;
    overflow-wrap: anywhere;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}

.homepage .category-pill::before {
    content: "✦";
    color: var(--event-accent);
}

.homepage .event-date-badge {
    position: absolute;
    right: 15px;
    bottom: 14px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 65px;
    min-height: 75px;
    padding: 9px;
    border: 1px solid rgba(255, 255, 255, .28);
    border-radius: 15px;
    background: rgba(4, 27, 19, .65);
    box-shadow: 0 6px 25px rgba(0, 0, 0, .2);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}

.homepage .event-date-badge strong {
    color: var(--event-accent);
    font-size: 27px;
    font-weight: 900;
    line-height: 1;
}

.homepage .event-date-badge small {
    margin-top: 6px;
    color: #fff;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* CARD CONTENT */

.homepage .event-card-body {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-width: 0;
    padding: 22px 20px 20px;
}

.homepage .event-card-body h3 {
    margin: 0 0 11px;
    color: #f5fff8;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 21px;
    font-weight: 800;
    line-height: 1.38;
    overflow-wrap: anywhere;
    transition: color .25s ease;
}

.homepage .event-card:hover .event-card-body h3 {
    color: var(--event-accent);
}

.homepage .event-description {
    min-height: 47px;
    margin: 0 0 17px;
    color: #c1d8ca;
    font-size: 12px;
    line-height: 1.8;
    overflow-wrap: anywhere;
}

.homepage .event-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px 10px;
    margin: 0 0 20px;
}

.homepage .event-meta > span {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    min-width: 0;
    color: #e0eee5;
    font-size: 11px;
    line-height: 1.65;
    overflow-wrap: anywhere;
}

.homepage .event-meta .meta-icon {
    flex-shrink: 0;
    color: var(--event-accent);
    font-size: 15px;
}

.homepage .slot-line {
    margin-top: auto;
    padding-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, .11);
}

.homepage .slot-labels {
    margin-bottom: 10px;
    color: #c8e0d1;
    font-size: 11px;
    font-weight: 800;
}

.homepage .slot-track {
    width: 100%;
    height: 6px;
    overflow: hidden;
    border-radius: 20px;
    background: rgba(255, 255, 255, .12);
}

.homepage .slot-track i {
    display: block;
    height: 100%;
    border-radius: 20px;
    background: var(--event-accent);
    box-shadow: 0 0 12px rgba(var(--event-rgb), .3);
}

.homepage .event-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 17px;
    padding: 12px 14px;
    border: 1px solid rgba(var(--event-rgb), .35);
    border-radius: 11px;
    background: rgba(var(--event-rgb), .07);
    color: var(--event-accent);
    font-size: 12px;
    font-weight: 900;
    transition: .25s ease;
}

.homepage .event-link > span {
    font-size: 18px;
    transition: transform .25s ease;
}

.homepage .event-link:hover {
    border-color: var(--event-accent);
    background: var(--event-accent);
    color: #072117;
}

.homepage .event-link:hover > span {
    transform: translateX(4px);
}

/* EMPTY STATE */

.homepage .empty-state {
    padding: 48px 22px;
    border: 1px solid rgba(255, 255, 255, .15);
    border-radius: 22px;
    background: rgba(255, 255, 255, .045);
    text-align: center;
    backdrop-filter: blur(16px);
}

.homepage .empty-icon {
    color: var(--green);
    font-size: 42px;
}

.homepage .empty-state h3 {
    margin: 14px 0 10px;
    color: #f5fff8;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 25px;
    font-weight: 800;
}

.homepage .empty-state p {
    max-width: 570px;
    margin: 0 auto 20px;
    color: #bfd8c9;
    font-size: 13px;
    line-height: 1.8;
}

.homepage .btn-green {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 18px;
    border: 1px solid var(--green);
    border-radius: 11px;
    background: var(--green);
    color: #062017;
    font-size: 12px;
    font-weight: 900;
}

/* BOTTOM CTA */

.homepage .bottom-cta {
    position: relative;
    padding: 58px 0;
    overflow: hidden;
    border-top: 1px solid rgba(156, 255, 181, .15);
    background:
        radial-gradient(ellipse at 85% 50%, rgba(100, 255, 171, .12), transparent 40%),
        linear-gradient(120deg, #061d17, #103829);
}

.homepage .bottom-cta-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 30px;
}

.homepage .bottom-cta h2 {
    max-width: 650px;
    margin: 12px 0;
    color: #f3fff6;
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(28px, 4vw, 41px);
    font-weight: 800;
    line-height: 1.2;
}

.homepage .bottom-cta p {
    max-width: 620px;
    margin: 0;
    color: #b6d2c0;
    font-size: 13px;
    line-height: 1.8;
}

/* ERROR MESSAGE */

.homepage .homepage-error {
    padding: 18px;
    border: 1px solid rgba(255, 130, 130, .3);
    border-radius: 14px;
    background: rgba(255, 80, 80, .08);
    color: #ffd4d4;
}

/* RESPONSIVE */

@media (max-width: 991px) {
    .homepage .hero-grid {
        grid-template-columns: 1fr;
    }

    .homepage .hero-glass {
        max-width: 540px;
    }

    .homepage .hero-section {
        min-height: auto;
    }
}

@media (max-width: 767px) {
    .homepage .hero-section {
        padding: 57px 0 48px;
    }

    .homepage .hero-copy h1 {
        font-size: clamp(42px, 10vw, 58px);
        letter-spacing: -1px;
    }

    .homepage .hero-copy > p {
        font-size: 13px;
    }

    .homepage .hero-glass {
        padding: 21px;
        border-radius: 20px;
    }

    .homepage .stats-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .homepage .stats-grid > div {
        padding: 5px 14px;
    }

    .homepage .homepage-events {
        padding: 53px 0;
    }

    .homepage .section-heading {
        align-items: flex-start;
        flex-direction: column;
        gap: 18px;
        margin-bottom: 27px;
    }

    .homepage .section-heading h2 {
        font-size: 36px;
    }

    .homepage .event-image {
        height: 230px;
    }

    .homepage .bottom-cta-inner {
        align-items: flex-start;
        flex-direction: column;
        gap: 22px;
    }
}

@media (max-width: 420px) {
    .homepage .hero-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .homepage .hero-actions a {
        width: 100%;
    }

    .homepage .hero-copy h1 {
        font-size: 42px;
    }

    .homepage .section-heading h2 {
        font-size: 31px;
    }

    .homepage .event-image {
        height: 195px;
    }

    .homepage .event-card-body {
        padding: 18px 15px;
    }

    .homepage .event-meta {
        gap: 10px 7px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .homepage *,
    .homepage *::before,
    .homepage *::after {
        scroll-behavior: auto !important;
        animation: none !important;
        transition: none !important;
    }
}
</style>

<div class="homepage">

    <!-- HERO -->
    <section class="hero-section">
        <div class="container hero-grid">

            <div class="hero-copy">
                <span class="eyebrow">
                    <span class="eyebrow-dot"></span>
                    YOUR CAMPUS, YOUR COMMUNITY
                </span>

                <h1>
                    Make campus life<br>
                    <em>happen.</em>
                </h1>

                <p>
                    Find seminars, workshops, student activities, and campus
                    celebrations. Discover what's happening and save your spot
                    in the PTC community.
                </p>

                <div class="hero-actions">
                    <a class="btn-cream btn-lg"
                       href="<?= e($basePath) ?>events.php">
                        Explore Events <span aria-hidden="true">→</span>
                    </a>

                    <?php if (!is_logged_in()): ?>

                        <a class="hero-text-link"
                           href="<?= e($basePath) ?>login.php">
                            Log In
                        </a>

                        <a class="hero-text-link"
                           href="<?= e($basePath) ?>register.php">
                            Create Student Account
                        </a>

                    <?php elseif (is_admin()): ?>

                        <a class="hero-text-link"
                           href="<?= e($basePath) ?>admin/dashboard.php">
                            Admin Dashboard →
                        </a>

                    <?php elseif (is_student()): ?>

                        <a class="hero-text-link"
                           href="<?= e($basePath) ?>my-registrations.php">
                            My Registrations →
                        </a>

                    <?php endif; ?>
                </div>

                <div class="hero-note">
                    ✦ Discover activities &nbsp;·&nbsp; Register easily
                    &nbsp;·&nbsp; Be part of campus life
                </div>
            </div>

            <div class="hero-glass">
                <div class="hero-glass-top">
                    <span class="hero-glass-label">CAMPUS EVENT HUB</span>
                    <span class="live-pill">CAMPUS LIFE</span>
                </div>

                <h2>Your next experience awaits.</h2>

                <p>
                    One community, many opportunities. Find an event that
                    matches your interests and make every campus moment count.
                </p>

                <div class="hero-feature">
                    <div class="hero-feature-icon" aria-hidden="true">⌕</div>
                    <div>
                        <strong>Discover something new</strong>
                        <span>Explore seminars, workshops, sports and more.</span>
                    </div>
                </div>

                <div class="hero-feature">
                    <div class="hero-feature-icon" aria-hidden="true">▣</div>
                    <div>
                        <strong>Save your spot</strong>
                        <span>Check event details and available seats.</span>
                    </div>
                </div>

                <div class="hero-feature">
                    <div class="hero-feature-icon" aria-hidden="true">✦</div>
                    <div>
                        <strong>Make campus memories</strong>
                        <span>Connect with activities across the community.</span>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- BENEFITS -->
    <section class="stats-strip">
        <div class="container stats-grid">
            <div>
                <strong>Discover</strong>
                <span>Activities that inspire you</span>
            </div>

            <div>
                <strong>Register</strong>
                <span>Find events and save your place</span>
            </div>

            <div>
                <strong>Participate</strong>
                <span>Make the most of campus life</span>
            </div>
        </div>
    </section>

    <!-- AVAILABLE EVENTS -->
    <section class="homepage-events">
        <div class="container">

            <div class="section-heading">
                <div>
                    <span class="eyebrow-dark">
                        YOUR NEXT EXPERIENCE STARTS HERE
                    </span>

                    <h2>Available Events<span style="color:#9cffb5;">.</span></h2>

                    <p>
                        Explore upcoming campus activities, view event details,
                        and find your next opportunity to participate.
                    </p>
                </div>

                <a class="btn-outline-green"
                   href="<?= e($basePath) ?>events.php">
                    View All Events <span aria-hidden="true">→</span>
                </a>
            </div>

            <?php if ($loadError): ?>

                <div class="homepage-error" role="alert">
                    We couldn't load the available events.
                    Please refresh the page or try again later.
                </div>

            <?php elseif (empty($events)): ?>

                <div class="empty-state">
                    <div class="empty-icon" aria-hidden="true">✦</div>

                    <h3>New experiences are on the way!</h3>

                    <p>
                        There are no available upcoming events right now.
                        Check back soon for the next campus activity.
                    </p>

                    <a class="btn-green"
                       href="<?= e($basePath) ?>events.php">
                        Explore Events →
                    </a>
                </div>

            <?php else: ?>

                <div class="row homepage-event-grid">

                    <?php foreach ($events as $event): ?>

                        <?php
                        $capacity = max(0, (int) ($event['capacity'] ?? 0));
                        $registeredCount = max(0, (int) ($event['registered_count'] ?? 0));
                        $remaining = max(0, $capacity - $registeredCount);

                        $occupancy = $capacity > 0
                            ? min(100, (int) round(($registeredCount / $capacity) * 100))
                            : 0;

                        $eventTimestamp = strtotime((string) ($event['event_date'] ?? ''));
                        $validEventDate = $eventTimestamp !== false;

                        $day = $validEventDate ? date('d', $eventTimestamp) : '--';
                        $month = $validEventDate ? date('M', $eventTimestamp) : 'TBA';

                        $description = trim((string) ($event['description'] ?? ''));

                        if ($description === '') {
                            $description = 'Discover this campus event and find out more details.';
                        }

                        $shortDescription = function_exists('mb_strimwidth')
                            ? mb_strimwidth($description, 0, 125, '...')
                            : (
                                strlen($description) > 125
                                    ? substr($description, 0, 122) . '...'
                                    : $description
                            );

                        $categoryName = trim((string) ($event['category'] ?? 'Campus Event'));

                        if ($categoryName === '') {
                            $categoryName = 'Campus Event';
                        }

                        $normalizedCategory = strtolower($categoryName);
                        $eventTitle = strtolower(trim((string) ($event['title'] ?? '')));

                        $eventImage = homepage_event_image($event);

                        if (
                            strpos($normalizedCategory, 'workshop') !== false ||
                            strpos($eventTitle, 'web development') !== false ||
                            strpos($eventTitle, 'web dev') !== false ||
                            strpos($eventTitle, 'workshop') !== false
                        ) {
                            $categoryClass = 'category-workshop';
                        } elseif (
                            strpos($normalizedCategory, 'sport') !== false ||
                            strpos($eventTitle, 'sport') !== false ||
                            strpos($eventTitle, 'athletic') !== false
                        ) {
                            $categoryClass = 'category-sports';
                        } elseif (
                            strpos($normalizedCategory, 'competition') !== false ||
                            strpos($eventTitle, 'hackathon') !== false
                        ) {
                            $categoryClass = 'category-competition';
                        } elseif (
                            strpos($normalizedCategory, 'seminar') !== false ||
                            strpos($eventTitle, 'seminar') !== false ||
                            strpos($eventTitle, 'innovation') !== false
                        ) {
                            $categoryClass = 'category-seminar';
                        } else {
                            $categoryClass = 'category-default';
                        }
                        ?>

                        <div class="col-md-6 col-xl-4">
                            <article class="event-card <?= e($categoryClass) ?>">

                                <!-- EVENT PHOTO HEADER -->
                                <div class="event-image">
                                    <img
                                        src="<?= e($eventImage) ?>"
                                        alt="<?= e((string) $event['title']) ?>"
                                        loading="lazy"
                                        decoding="async"
                                        onerror="this.onerror=null;this.src='https://www.paterostechnologicalcollege.edu.ph/ASSETS/IMAGES/IICT/IICT6.jpg';"
                                    >

                                    <div class="event-image-overlay">
                                        <span class="category-pill">
                                            <?= e($categoryName) ?>
                                        </span>

                                        <span class="event-date-badge">
                                            <strong><?= e($day) ?></strong>
                                            <small><?= e($month) ?></small>
                                        </span>
                                    </div>
                                </div>

                                <!-- GLASS EVENT INFORMATION -->
                                <div class="event-card-body">

                                    <h3>
                                        <?= e((string) $event['title']) ?>
                                    </h3>

                                    <p class="event-description">
                                        <?= e($shortDescription) ?>
                                    </p>

                                    <div class="event-meta">

                                        <span>
                                            <span class="meta-icon" aria-hidden="true">▦</span>
                                            <span>
                                                <?= e(format_date((string) $event['event_date'])) ?>
                                            </span>
                                        </span>

                                        <span>
                                            <span class="meta-icon" aria-hidden="true">⌖</span>
                                            <span>
                                                <?= e(
                                                    !empty($event['venue'])
                                                        ? (string) $event['venue']
                                                        : 'Venue TBA'
                                                ) ?>
                                            </span>
                                        </span>

                                        <?php if (!empty($event['start_time'])): ?>
                                            <span>
                                                <span class="meta-icon" aria-hidden="true">◷</span>
                                                <span>
                                                    <?= e(format_time((string) $event['start_time'])) ?>

                                                    <?php if (!empty($event['end_time'])): ?>
                                                        – <?= e(format_time((string) $event['end_time'])) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($event['organizer'])): ?>
                                            <span>
                                                <span class="meta-icon" aria-hidden="true">♧</span>
                                                <span><?= e((string) $event['organizer']) ?></span>
                                            </span>
                                        <?php endif; ?>

                                    </div>

                                    <!-- SEAT AVAILABILITY -->
                                    <div class="slot-line">
                                        <div class="slot-labels">
                                            <?php if ($capacity > 0): ?>
                                                <?= $remaining ?> of <?= $capacity ?> spots available
                                            <?php else: ?>
                                                Open availability
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

                                    <!-- KEEP EXISTING EVENT DETAILS ROUTE -->
                                    <a
                                        class="event-link"
                                        href="<?= e($basePath) ?>event-details.php?id=<?= (int) $event['id'] ?>"
                                    >
                                        View Event Details
                                        <span aria-hidden="true">→</span>
                                    </a>

                                </div>
                            </article>
                        </div>

                    <?php endforeach; ?>

                </div>

                <div class="text-center mt-5">
                    <a class="btn-outline-green"
                       href="<?= e($basePath) ?>events.php">
                        Discover More Events <span aria-hidden="true">→</span>
                    </a>
                </div>

            <?php endif; ?>

        </div>
    </section>

    <!-- BOTTOM CALL TO ACTION -->
    <section class="bottom-cta">
        <div class="container bottom-cta-inner">

            <div>
                <?php if (!is_logged_in()): ?>

                    <span class="eyebrow">DON'T MISS OUT</span>

                    <h2>Your next campus memory starts here.</h2>

                    <p>
                        Discover workshops, seminars, and campus activities.
                        Explore events anytime, even without an account.
                    </p>

                <?php elseif (is_admin()): ?>

                    <span class="eyebrow">ADMINISTRATION</span>

                    <h2>Keep campus events organized.</h2>

                    <p>
                        Manage events, review registrations, and keep your
                        campus activities up to date.
                    </p>

                <?php else: ?>

                    <span class="eyebrow">YOUR CAMPUS JOURNEY</span>

                    <h2>Your next campus memory starts here.</h2>

                    <p>
                        Find activities that interest you and keep track
                        of your event registrations.
                    </p>

                <?php endif; ?>
            </div>

            <?php if (!is_logged_in()): ?>

                <a href="<?= e($basePath) ?>register.php"
                   class="btn-cream btn-lg">
                    Join the Community →
                </a>

            <?php elseif (is_admin()): ?>

                <a href="<?= e($basePath) ?>admin/dashboard.php"
                   class="btn-cream btn-lg">
                    Manage Events →
                </a>

            <?php else: ?>

                <a href="<?= e($basePath) ?>my-registrations.php"
                   class="btn-cream btn-lg">
                    My Registrations →
                </a>

            <?php endif; ?>

        </div>
    </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>