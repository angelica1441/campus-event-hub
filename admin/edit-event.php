
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    redirect('admin/dashboard.php');
}

$pageTitle = 'Edit Event';
$basePath = app_url();

$errors = [];

$categories = [
    'Academic',
    'Sports',
    'Cultural',
    'Seminar',
    'Workshop',
    'Social',
    'Career',
    'Other'
];

$statuses = ['active', 'archived', 'cancelled'];

$fields = [
    'title',
    'description',
    'category',
    'event_date',
    'start_time',
    'end_time',
    'venue',
    'organizer',
    'capacity',
    'status'
];

try {
    // Get event details
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$id]);

    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        set_flash('danger', 'Event not found.');
        redirect('admin/dashboard.php');
    }

    // Prepare current values
    $values = [];

    foreach ($fields as $field) {
        $values[$field] = (string) ($event[$field] ?? '');
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_valid_csrf();

        foreach ($fields as $field) {
            $values[$field] = trim(
                (string) ($_POST[$field] ?? '')
            );
        }

        // Validate title
        if (
            $values['title'] === '' ||
            mb_strlen($values['title']) > 150
        ) {
            $errors[] =
                'Event title is required and must not exceed 150 characters.';
        }

        // Validate description
        if ($values['description'] === '') {
            $errors[] = 'Description is required.';
        }

        // Validate category
        if (!in_array($values['category'], $categories, true)) {
            $errors[] = 'Please select a valid category.';
        }

        // Validate venue and organizer
        if ($values['venue'] === '') {
            $errors[] = 'Venue is required.';
        }

        if ($values['organizer'] === '') {
            $errors[] = 'Organizer is required.';
        }

        // Validate date
        $date = DateTime::createFromFormat(
            '!Y-m-d',
            $values['event_date']
        );

        if (
            !$date ||
            $date->format('Y-m-d') !== $values['event_date']
        ) {
            $errors[] = 'Please enter a valid event date.';
        }

        // Validate time
        $startTime = $values['start_time'];
        $endTime = $values['end_time'];

        $validStart = preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            $startTime
        );

        $validEnd = preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            $endTime
        );

        if (!$validStart || !$validEnd) {
            $errors[] = 'Please enter valid start and end times.';
        } elseif ($endTime <= $startTime) {
            $errors[] = 'End time must be later than start time.';
        }

        // Validate capacity
        $capacity = filter_var(
            $values['capacity'],
            FILTER_VALIDATE_INT
        );

        if ($capacity === false || $capacity < 1) {
            $errors[] = 'Capacity must be a positive whole number.';
        }

        // Validate status
        if (!in_array($values['status'], $statuses, true)) {
            $errors[] = 'Please select a valid event status.';
        }

        // Check the current registered attendees
        if ($capacity !== false && $capacity >= 1) {
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM registrations
                 WHERE event_id = ?
                 AND status = 'registered'"
            );

            $countStmt->execute([$id]);

            $registeredCount = (int) $countStmt->fetchColumn();

            if ($capacity < $registeredCount) {
                $errors[] =
                    "Capacity cannot be lower than the current number of registered attendees ({$registeredCount}).";
            }
        }

        // Update event when validation passes
        if (empty($errors)) {
            $updateStmt = $pdo->prepare(
                'UPDATE events
                 SET title = ?,
                     description = ?,
                     category = ?,
                     event_date = ?,
                     start_time = ?,
                     end_time = ?,
                     venue = ?,
                     organizer = ?,
                     capacity = ?,
                     status = ?
                 WHERE id = ?'
            );

            $updateStmt->execute([
                $values['title'],
                $values['description'],
                $values['category'],
                $values['event_date'],
                $startTime,
                $endTime,
                $values['venue'],
                $values['organizer'],
                $capacity,
                $values['status'],
                $id
            ]);

            set_flash(
                'success',
                'Event details updated successfully.'
            );

            redirect('admin/dashboard.php');
        }
    }
} catch (PDOException $e) {
    error_log('Edit Event Database Error: ' . $e->getMessage());

    $errors[] =
        'A database error occurred. Please verify your database columns and try again.';
    
    // Keep the page from failing with an undefined variable
    if (!isset($values)) {
        $values = array_fill_keys($fields, '');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <a href="dashboard.php" class="back-link">
            &larr; Back to Dashboard
        </a>

        <h1 class="mt-3">Edit Event</h1>

        <p>Update the event details and availability.</p>
    </div>
</section>

<section class="section-space">
    <div class="container form-page-wrap">

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <strong>Please check the following:</strong>

                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form-panel">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <div class="mb-4">
                <label for="title" class="form-label">
                    Event Title
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="title"
                    name="title"
                    maxlength="150"
                    value="<?= e($values['title']) ?>"
                    required
                >
            </div>

            <div class="mb-4">
                <label for="description" class="form-label">
                    Event Description
                </label>

                <textarea
                    class="form-control"
                    id="description"
                    name="description"
                    rows="5"
                    required
                ><?= e($values['description']) ?></textarea>
            </div>

            <div class="row g-3">

                <div class="col-md-6">
                    <label for="category" class="form-label">
                        Category
                    </label>

                    <select
                        class="form-select"
                        id="category"
                        name="category"
                        required
                    >
                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= e($category) ?>"
                                <?= $values['category'] === $category
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="organizer" class="form-label">
                        Organizer
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="organizer"
                        name="organizer"
                        value="<?= e($values['organizer']) ?>"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label for="event_date" class="form-label">
                        Event Date
                    </label>

                    <input
                        type="date"
                        class="form-control"
                        id="event_date"
                        name="event_date"
                        value="<?= e($values['event_date']) ?>"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label for="start_time" class="form-label">
                        Start Time
                    </label>

                    <input
                        type="time"
                        class="form-control"
                        id="start_time"
                        name="start_time"
                        value="<?= e(substr($values['start_time'], 0, 5)) ?>"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label for="end_time" class="form-label">
                        End Time
                    </label>

                    <input
                        type="time"
                        class="form-control"
                        id="end_time"
                        name="end_time"
                        value="<?= e(substr($values['end_time'], 0, 5)) ?>"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label for="venue" class="form-label">
                        Venue
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="venue"
                        name="venue"
                        value="<?= e($values['venue']) ?>"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label for="capacity" class="form-label">
                        Capacity
                    </label>

                    <input
                        type="number"
                        class="form-control"
                        id="capacity"
                        name="capacity"
                        min="1"
                        step="1"
                        value="<?= e($values['capacity']) ?>"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">
                        Event Status
                    </label>

                    <select
                        class="form-select"
                        id="status"
                        name="status"
                        required
                    >
                        <?php foreach ($statuses as $status): ?>
                            <option
                                value="<?= e($status) ?>"
                                <?= $values['status'] === $status
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e(ucfirst($status)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-green">
                    Save Changes
                </button>

                <a href="dashboard.php" class="btn btn-light">
                    Cancel
                </a>
            </div>

        </form>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
