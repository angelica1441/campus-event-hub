
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Manage Events';
$basePath = app_url();

$search = trim($_GET['search'] ?? '');
$events = [];
$totalEvents = 0;
$totalRegistrations = 0;
$pageError = '';

try {
    /*
     * MANAGE EVENTS:
     * Show only future events whose status is Upcoming or Ongoing.
     * Completed and cancelled events belong in Event History.
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
        WHERE LOWER(COALESCE(e.status, '')) IN ('upcoming', 'ongoing')
          AND e.event_date >= CURDATE()
    ";

    $params = [];

    if ($search !== '') {
        $sql .= "
            AND (
                e.title LIKE :search_title
                OR e.category LIKE :search_category
                OR e.venue LIKE :search_venue
            )
        ";

        $keyword = '%' . $search . '%';
        $params['search_title'] = $keyword;
        $params['search_category'] = $keyword;
        $params['search_venue'] = $keyword;
    }

    $sql .= " ORDER BY e.event_date ASC, e.start_time ASC, e.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalEvents = count($events);

    foreach ($events as $event) {
        $totalRegistrations += (int)($event['registered_count'] ?? 0);
    }

} catch (PDOException $exception) {
    error_log('Manage Events Error: ' . $exception->getMessage());
    $pageError = 'Unable to load events. Please check your database structure.';
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="ceh-manage-hero">
    <div class="container ceh-manage-hero-inner">
        <div class="ceh-manage-copy">
            <span class="ceh-manage-eyebrow">
                <span class="ceh-live-dot"></span>
                ADMINISTRATION / EVENT MANAGEMENT
            </span>

            <h1>Manage your <span>campus events.</span></h1>

            <p>
                Organize upcoming activities, monitor student registrations,
                and keep your PTC community connected.
            </p>

            <div class="ceh-manage-hero-actions">
                <a
                    href="<?= e($basePath . 'admin/add-event.php') ?>"
                    class="ceh-primary-btn"
                >
                    <span aria-hidden="true">+</span> Create New Event
                </a>

                <a
                    href="<?= e($basePath . 'admin/event-history.php') ?>"
                    class="ceh-secondary-btn"
                >
                    View Event History <span aria-hidden="true">→</span>
                </a>
            </div>
        </div>

        <div class="ceh-manage-hero-art" aria-hidden="true">
            <div class="ceh-art-orbit ceh-art-orbit-one"></div>
            <div class="ceh-art-orbit ceh-art-orbit-two"></div>

            <div class="ceh-art-calendar">
                <div class="ceh-art-calendar-top"></div>
                <div class="ceh-art-calendar-date">PTC</div>
                <div class="ceh-art-calendar-label">CAMPUS EVENTS</div>
                <div class="ceh-art-calendar-grid">
                    <i></i><i></i><i></i><i></i>
                    <i></i><i></i><i></i><i></i>
                    <i></i><i></i><i></i><i></i>
                </div>
            </div>

            <div class="ceh-art-floating ceh-art-floating-top">
                <span>✦</span> Campus spirit
            </div>

            <div class="ceh-art-floating ceh-art-floating-bottom">
                <span>✓</span> Event planner
            </div>
        </div>
    </div>
</section>

<section class="ceh-manage-section">
    <div class="container">

        <?php if ($pageError !== ''): ?>
            <div class="alert alert-danger mb-4">
                <?= e($pageError) ?>
            </div>
        <?php endif; ?>

        <div class="ceh-manage-heading">
            <div>
                <span class="ceh-section-kicker">YOUR EVENT WORKSPACE</span>
                <h2>Upcoming &amp; ongoing events</h2>
                <p>
                    Only active campus events appear here.
                    Past and cancelled events are kept in Event History.
                </p>
            </div>

            <a
                href="<?= e($basePath . 'admin/dashboard.php') ?>"
                class="ceh-back-link"
            >
                ← Admin Dashboard
            </a>
        </div>

        <div class="ceh-manage-stats">
            <article class="ceh-mini-stat">
                <div class="ceh-mini-stat-icon ceh-icon-green">▦</div>
                <div>
                    <span>Active events</span>
                    <strong><?= number_format($totalEvents) ?></strong>
                    <small>Upcoming and ongoing</small>
                </div>
            </article>

            <article class="ceh-mini-stat">
                <div class="ceh-mini-stat-icon ceh-icon-sage">♙</div>
                <div>
                    <span>Registrations</span>
                    <strong><?= number_format($totalRegistrations) ?></strong>
                    <small>For listed events</small>
                </div>
            </article>

            <article class="ceh-mini-stat">
                <div class="ceh-mini-stat-icon ceh-icon-gold">◷</div>
                <div>
                    <span>Next event</span>
                    <strong class="ceh-next-event-value">
                        <?= !empty($events)
                            ? e(date('M d', strtotime($events[0]['event_date'])))
                            : '—' ?>
                    </strong>
                    <small>
                        <?= !empty($events)
                            ? e($events[0]['title'])
                            : 'No upcoming events' ?>
                    </small>
                </div>
            </article>
        </div>

        <div class="ceh-event-toolbar">
            <div class="ceh-event-toolbar-title">
                <h3>Event directory</h3>
                <span><?= number_format($totalEvents) ?> events</span>
            </div>

            <form
                method="GET"
                action="<?= e($basePath . 'admin/manage-events.php') ?>"
                class="ceh-event-search"
            >
                <span aria-hidden="true">⌕</span>

                <input
                    type="search"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Search title, category, or venue..."
                    aria-label="Search events"
                >

                <?php if ($search !== ''): ?>
                    <a
                        class="ceh-clear-search"
                        href="<?= e($basePath . 'admin/manage-events.php') ?>"
                    >
                        Clear
                    </a>
                <?php endif; ?>

                <button type="submit">Search</button>
            </form>
        </div>

        <?php if (empty($events) && $pageError === ''): ?>
            <div class="ceh-empty-state">
                <div class="ceh-empty-icon">▦</div>

                <h3>
                    <?= $search !== ''
                        ? 'No matching events'
                        : 'No active events yet' ?>
                </h3>

                <p>
                    <?= $search !== ''
                        ? 'Try another keyword or clear your search.'
                        : 'Create a new campus activity to get started.' ?>
                </p>

                <div class="ceh-empty-actions">
                    <?php if ($search !== ''): ?>
                        <a
                            href="<?= e($basePath . 'admin/manage-events.php') ?>"
                            class="ceh-primary-btn"
                        >
                            Clear Search
                        </a>
                    <?php else: ?>
                        <a
                            href="<?= e($basePath . 'admin/add-event.php') ?>"
                            class="ceh-primary-btn"
                        >
                            + Create First Event
                        </a>
                    <?php endif; ?>

                    <a
                        href="<?= e($basePath . 'admin/event-history.php') ?>"
                        class="ceh-secondary-light-btn"
                    >
                        Check Event History
                    </a>
                </div>
            </div>
        <?php elseif (!empty($events)): ?>

            <div class="ceh-event-list">
                <?php foreach ($events as $event): ?>
                    <?php
                    $status = strtolower(trim($event['status'] ?? ''));

                    $isOngoing = $status === 'ongoing';

                    $dateLabel = !empty($event['event_date'])
                        ? date('M d, Y', strtotime($event['event_date']))
                        : 'Date not set';

                    $timeLabel = !empty($event['start_time'])
                        ? date('g:i A', strtotime($event['start_time']))
                        : 'Time not set';

                    $registrationCount = (int)($event['registered_count'] ?? 0);
                    $capacity = isset($event['capacity']) && $event['capacity'] !== ''
                        ? (int)$event['capacity']
                        : 0;

                    $capacityPercent = $capacity > 0
                        ? min(100, (int)round(($registrationCount / $capacity) * 100))
                        : 0;
                    ?>

                    <article class="ceh-event-row">
                        <div class="ceh-event-date">
                            <span>
                                <?= !empty($event['event_date'])
                                    ? e(date('M', strtotime($event['event_date'])))
                                    : 'PTC' ?>
                            </span>

                            <strong>
                                <?= !empty($event['event_date'])
                                    ? e(date('d', strtotime($event['event_date'])))
                                    : '—' ?>
                            </strong>

                            <small>
                                <?= !empty($event['event_date'])
                                    ? e(date('D', strtotime($event['event_date'])))
                                    : 'Event' ?>
                            </small>
                        </div>

                        <div class="ceh-event-main">
                            <div class="ceh-event-title-line">
                                <h3><?= e($event['title'] ?? 'Untitled Event') ?></h3>

                                <span class="ceh-status <?= $isOngoing
                                    ? 'ceh-status-ongoing'
                                    : 'ceh-status-upcoming' ?>">
                                    <span></span>
                                    <?= $isOngoing ? 'Ongoing' : 'Upcoming' ?>
                                </span>
                            </div>

                            <div class="ceh-event-meta">
                                <span>
                                    <span aria-hidden="true">⌖</span>
                                    <?= e($event['venue'] ?? 'Venue not specified') ?>
                                </span>

                                <span>
                                    <span aria-hidden="true">◷</span>
                                    <?= e($timeLabel) ?>
                                </span>

                                <span>
                                    <span aria-hidden="true">◇</span>
                                    <?= e($event['category'] ?? 'General') ?>
                                </span>
                            </div>

                            <div class="ceh-event-capacity">
                                <div class="ceh-capacity-label">
                                    <span>Student registrations</span>
                                    <strong>
                                        <?= number_format($registrationCount) ?>
                                        <?= $capacity > 0
                                            ? ' / ' . number_format($capacity)
                                            : '' ?>
                                    </strong>
                                </div>

                                <?php if ($capacity > 0): ?>
                                    <div class="ceh-capacity-track">
                                        <span style="width: <?= $capacityPercent ?>%"></span>
                                    </div>
                                <?php else: ?>
                                    <div class="ceh-capacity-unlimited">
                                        No capacity limit specified
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="ceh-event-actions">
                            <a
                                class="ceh-action-btn ceh-action-edit"
                                href="<?= e($basePath . 'admin/edit-event.php?id=' . (int)$event['id']) ?>"
                            >
                                Edit Event
                            </a>

                            <a
                                class="ceh-action-btn ceh-action-view"
                                href="<?= e($basePath . 'admin/registrations.php?event_id=' . (int)$event['id']) ?>"
                            >
                                Registrations
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="ceh-history-promo">
            <div class="ceh-history-promo-icon">↺</div>

            <div>
                <h3>Looking for a past event?</h3>
                <p>
                    Completed and cancelled campus events are organized
                    separately in your event history.
                </p>
            </div>

            <a href="<?= e($basePath . 'admin/event-history.php') ?>">
                Explore History <span aria-hidden="true">→</span>
            </a>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>