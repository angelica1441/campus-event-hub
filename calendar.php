<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Event Calendar';
$basePath = app_url();

// Get selected month and year
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);

$year = ($year && $year >= 2020 && $year <= 2100)
    ? $year
    : (int) date('Y');

$month = ($month && $month >= 1 && $month <= 12)
    ? $month
    : (int) date('n');

$firstDay = new DateTimeImmutable(
    sprintf('%04d-%02d-01', $year, $month)
);

$daysInMonth = (int) $firstDay->format('t');
$startWeekday = (int) $firstDay->format('N'); // Monday = 1

$previousMonth = $firstDay->modify('-1 month');
$nextMonth = $firstDay->modify('+1 month');

$today = date('Y-m-d');

$eventsByDate = [];
$calendarError = '';

try {
    $rangeStart = $firstDay->format('Y-m-d 00:00:00');
    $rangeEnd = $firstDay->modify('+1 month')->format('Y-m-d 00:00:00');

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            category,
            event_date,
            start_time,
            end_time,
            venue,
            location,
            status
        FROM events
        WHERE event_date >= ?
          AND event_date < ?
          AND LOWER(TRIM(status)) != 'cancelled'
        ORDER BY event_date ASC, start_time ASC
    ");

    $stmt->execute([$rangeStart, $rangeEnd]);

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($events as $event) {
        $eventDate = date('Y-m-d', strtotime($event['event_date']));

        $eventsByDate[$eventDate][] = $event;
    }

} catch (PDOException $exception) {
    error_log('Calendar error: ' . $exception->getMessage());
    $calendarError = 'Unable to load calendar events. Please try again later.';
}

function calendar_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function calendar_time($time): string
{
    if (empty($time)) {
        return '';
    }

    $timestamp = strtotime((string) $time);

    return $timestamp ? date('g:i A', $timestamp) : '';
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.calendar-page {
    min-height: 85vh;
    padding: 35px 0 60px;
    background:
        radial-gradient(circle at 10% 5%, rgba(44, 130, 87, .20), transparent 35%),
        linear-gradient(135deg, #071a12, #10291e 55%, #071a12);
    color: #f1fff5;
}

.calendar-container {
    width: min(1200px, 94%);
    margin: auto;
}

.calendar-hero {
    margin-bottom: 28px;
}

.calendar-hero h1 {
    color: #baff80;
    font-size: clamp(27px, 4vw, 38px);
    font-weight: 800;
    margin-bottom: 8px;
}

.calendar-hero p {
    color: #c0d8c8;
    margin: 0;
}

.calendar-card {
    background: rgba(12, 39, 27, .92);
    border: 1px solid rgba(105, 255, 155, .20);
    border-radius: 20px;
    padding: 22px;
    box-shadow: 0 15px 45px rgba(0, 0, 0, .25);
}

.calendar-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.calendar-toolbar h2 {
    color: #baff80;
    font-size: 25px;
    font-weight: 800;
    margin: 0;
}

.calendar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.calendar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 15px;
    border-radius: 10px;
    text-decoration: none;
    color: #eaffef;
    background: #193e2b;
    border: 1px solid rgba(105, 255, 155, .25);
    font-size: 13px;
    font-weight: 700;
    transition: .2s;
}

.calendar-btn:hover {
    color: #071a12;
    background: #a8e878;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 7px;
}

.calendar-weekday {
    text-align: center;
    padding: 12px 3px;
    color: #a8d96c;
    font-weight: 800;
    font-size: 12px;
    text-transform: uppercase;
}

.calendar-day {
    min-height: 125px;
    background: rgba(255, 255, 255, .035);
    border: 1px solid rgba(255, 255, 255, .07);
    border-radius: 12px;
    padding: 9px;
    min-width: 0;
}

.calendar-day.empty {
    background: transparent;
    border: 1px dashed rgba(255, 255, 255, .04);
}

.calendar-day.today {
    border: 1px solid #69ff9b;
    background: rgba(105, 255, 155, .09);
}

.day-number {
    color: #e7f8ec;
    font-size: 14px;
    font-weight: 800;
    margin-bottom: 8px;
}

.today .day-number {
    color: #baff80;
}

.calendar-event {
    display: block;
    padding: 7px;
    margin-bottom: 6px;
    border-radius: 7px;
    background: rgba(105, 255, 155, .12);
    border-left: 3px solid #69ff9b;
    text-decoration: none;
    color: #effff3;
    font-size: 11px;
    line-height: 1.4;
    overflow-wrap: anywhere;
    transition: .2s;
}

.calendar-event:hover {
    color: #baff80;
    background: rgba(105, 255, 155, .22);
}

.event-time {
    display: block;
    color: #a8d96c;
    font-size: 10px;
    margin-top: 3px;
}

.no-events {
    color: #9bb4a2;
    font-size: 11px;
    margin-top: 5px;
}

.calendar-legend {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #b9d2c0;
    font-size: 12px;
    margin-top: 18px;
}

.legend-dot {
    width: 10px;
    height: 10px;
    background: #69ff9b;
    border-radius: 50%;
}

.calendar-error {
    background: rgba(220, 53, 69, .12);
    color: #ffb5bd;
    padding: 13px;
    border-radius: 10px;
    margin-bottom: 15px;
}

@media (max-width: 700px) {
    .calendar-card {
        padding: 12px;
    }

    .calendar-grid {
        gap: 3px;
    }

    .calendar-day {
        min-height: 90px;
        padding: 5px 3px;
        border-radius: 7px;
    }

    .calendar-weekday {
        font-size: 9px;
        padding: 8px 0;
    }

    .day-number {
        font-size: 12px;
        margin-bottom: 5px;
    }

    .calendar-event {
        font-size: 9px;
        padding: 5px 3px;
        border-left-width: 2px;
    }

    .event-time {
        font-size: 8px;
    }

    .calendar-toolbar h2 {
        font-size: 20px;
    }

    .calendar-btn {
        padding: 8px 10px;
        font-size: 11px;
    }
}

@media (max-width: 380px) {
    .calendar-day {
        min-height: 75px;
    }

    .calendar-event {
        font-size: 8px;
    }
}
</style>

<main class="calendar-page">
    <div class="calendar-container">

        <div class="calendar-hero">
            <h1>Event Calendar</h1>
            <p>Stay updated with upcoming campus activities and scheduled events.</p>
        </div>

        <div class="calendar-card">

            <?php if ($calendarError !== ''): ?>
                <div class="calendar-error">
                    <?= calendar_escape($calendarError) ?>
                </div>
            <?php endif; ?>

            <div class="calendar-toolbar">

                <h2>
                    <?= calendar_escape($firstDay->format('F Y')) ?>
                </h2>

                <div class="calendar-actions">

                    <a class="calendar-btn"
                       href="calendar.php?year=<?= (int) date('Y') ?>&month=<?= (int) date('n') ?>">
                        Today
                    </a>

                    <a class="calendar-btn"
                       href="calendar.php?year=<?= $previousMonth->format('Y') ?>&month=<?= $previousMonth->format('n') ?>">
                        &laquo; Previous
                    </a>

                    <a class="calendar-btn"
                       href="calendar.php?year=<?= $nextMonth->format('Y') ?>&month=<?= $nextMonth->format('n') ?>">
                        Next &raquo;
                    </a>

                </div>
            </div>

            <div class="calendar-grid">

                <?php
                $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

                foreach ($weekdays as $weekday):
                ?>
                    <div class="calendar-weekday">
                        <?= $weekday ?>
                    </div>
                <?php endforeach; ?>

                <?php
                // Empty cells before the first day of the month
                for ($blank = 1; $blank < $startWeekday; $blank++):
                ?>
                    <div class="calendar-day empty"></div>
                <?php endfor; ?>

                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>

                    <?php
                    $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isToday = ($dateKey === $today);
                    $dayEvents = $eventsByDate[$dateKey] ?? [];
                    ?>

                    <div class="calendar-day <?= $isToday ? 'today' : '' ?>">

                        <div class="day-number">
                            <?= $day ?>
                            <?php if ($isToday): ?>
                                <small style="color:#69ff9b;">Today</small>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($dayEvents)): ?>

                            <?php foreach ($dayEvents as $event): ?>

                                <?php
                                $eventTime = calendar_time($event['start_time'] ?? '');
                                $eventTitle = $event['title'] ?? 'Untitled Event';
                                ?>

                                <a class="calendar-event"
                                   href="event-details.php?id=<?= (int) $event['id'] ?>">

                                    <strong>
                                        <?= calendar_escape($eventTitle) ?>
                                    </strong>

                                    <?php if ($eventTime !== ''): ?>
                                        <span class="event-time">
                                            <?= calendar_escape($eventTime) ?>
                                        </span>
                                    <?php endif; ?>

                                </a>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="no-events">No events</div>

                        <?php endif; ?>

                    </div>

                <?php endfor; ?>

            </div>

            <div class="calendar-legend">
                <span class="legend-dot"></span>
                <span>Scheduled campus events</span>
            </div>

        </div>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>