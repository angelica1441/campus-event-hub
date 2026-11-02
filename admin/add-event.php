<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Add Event';
$basePath = app_url();

$errors = [];

$values = [
    'title' => '',
    'description' => '',
    'category' => 'Academic',
    'event_date' => '',
    'start_time' => '',
    'end_time' => '',
    'venue' => '',
    'organizer' => '',
    'capacity' => '100'
];

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

/*
|--------------------------------------------------------------------------
| Process Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    foreach ($values as $key => $value) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    // Validate event title
    if (
        $values['title'] === '' ||
        mb_strlen($values['title']) > 150
    ) {
        $errors[] = 'Enter an event title with a maximum of 150 characters.';
    }

    // Validate description
    if ($values['description'] === '') {
        $errors[] = 'Event description is required.';
    }

    // Validate category
    if (!in_array($values['category'], $categories, true)) {
        $errors[] = 'Please select a valid event category.';
    }

    // Validate venue
    if (
        $values['venue'] === '' ||
        mb_strlen($values['venue']) > 150
    ) {
        $errors[] = 'Venue is required and must not exceed 150 characters.';
    }

    // Validate organizer
    if (
        $values['organizer'] === '' ||
        mb_strlen($values['organizer']) > 100
    ) {
        $errors[] = 'Organizer is required and must not exceed 100 characters.';
    }

    // Validate event date
    $date = DateTime::createFromFormat(
        '!Y-m-d',
        $values['event_date']
    );

    if (
        !$date ||
        $date->format('Y-m-d') !== $values['event_date']
    ) {
        $errors[] = 'Please enter a valid event date.';
    } elseif ($values['event_date'] < date('Y-m-d')) {
        $errors[] = 'The event date cannot be in the past.';
    }

    // Validate time format
    $startTimeValid = preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $values['start_time']
    );

    $endTimeValid = preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $values['end_time']
    );

    if (!$startTimeValid || !$endTimeValid) {
        $errors[] = 'Please enter valid start and end times.';
    } elseif ($values['end_time'] <= $values['start_time']) {
        $errors[] = 'End time must be later than start time.';
    }

    // Validate capacity
    $capacity = filter_var(
        $values['capacity'],
        FILTER_VALIDATE_INT
    );

    if (
        $capacity === false ||
        $capacity < 1 ||
        $capacity > 100000
    ) {
        $errors[] = 'Capacity must be a whole number between 1 and 100000.';
    }

    /*
    |--------------------------------------------------------------------------
    | Save Event
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {
        try {
            $currentUser = current_user();

            if (
                !is_array($currentUser) ||
                !isset($currentUser['id'])
            ) {
                throw new RuntimeException(
                    'Unable to identify the current administrator.'
                );
            }

            $sql = "
                INSERT INTO events (
                    title,
                    description,
                    category,
                    event_date,
                    start_time,
                    end_time,
                    venue,
                    organizer,
                    capacity,
                    status,
                    created_by
                ) VALUES (
                    :title,
                    :description,
                    :category,
                    :event_date,
                    :start_time,
                    :end_time,
                    :venue,
                    :organizer,
                    :capacity,
                    'active',
                    :created_by
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':title' => $values['title'],
                ':description' => $values['description'],
                ':category' => $values['category'],
                ':event_date' => $values['event_date'],
                ':start_time' => $values['start_time'],
                ':end_time' => $values['end_time'],
                ':venue' => $values['venue'],
                ':organizer' => $values['organizer'],
                ':capacity' => $capacity,
                ':created_by' => $currentUser['id']
            ]);

            set_flash(
                'success',
                'Event created successfully!'
            );

            redirect('admin/dashboard.php');
            exit;

        } catch (Throwable $exception) {
            error_log(
                'Create event error: ' . $exception->getMessage()
            );

            $errors[] = 'Unable to create the event. Please check your database structure and try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<section class="page-hero">
    <div class="container">

        <a
            class="back-link"
            href="dashboard.php"
        >
            &larr; Back to Dashboard
        </a>

        <div class="mt-3">
            <span class="text-uppercase small fw-bold">
                Event Management
            </span>

            <h1 class="mt-2">Create an Event</h1>

            <p>
                Add a campus activity and share it with students.
                Complete the details below to publish your event.
            </p>
        </div>

    </div>
</section>

<!-- Event Form -->
<section class="section-space">
    <div class="container form-page-wrap">

        <!-- Validation Errors -->
        <?php if (!empty($errors)): ?>
            <div
                class="alert alert-danger"
                role="alert"
            >
                <strong>Please check the following:</strong>

                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form
            method="post"
            class="form-panel"
            autocomplete="off"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <!-- Basic Information -->
            <div class="form-section-heading">
                <h2>Event Information</h2>
                <p>Provide the name and description of your event.</p>
            </div>

            <div class="mb-3">
                <label
                    class="form-label"
                    for="title"
                >
                    Event Title <span class="text-danger">*</span>
                </label>

                <input
                    class="form-control"
                    type="text"
                    id="title"
                    name="title"
                    maxlength="150"
                    placeholder="Enter event title"
                    value="<?= e($values['title']) ?>"
                    required
                >
            </div>

            <div class="mb-4">
                <label
                    class="form-label"
                    for="description"
                >
                    Description <span class="text-danger">*</span>
                </label>

                <textarea
                    class="form-control"
                    id="description"
                    name="description"
                    rows="5"
                    placeholder="Describe the purpose and details of the event..."
                    required
                ><?= e($values['description']) ?></textarea>
            </div>

            <!-- Category and Organizer -->
            <div class="row g-3 mb-3">

                <div class="col-md-6">
                    <label
                        class="form-label"
                        for="category"
                    >
                        Category <span class="text-danger">*</span>
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
                                <?= $values['category'] === $category ? 'selected' : '' ?>
                            >
                                <?= e($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label
                        class="form-label"
                        for="organizer"
                    >
                        Organizer <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control"
                        type="text"
                        id="organizer"
                        name="organizer"
                        maxlength="100"
                        placeholder="Enter organizer name"
                        value="<?= e($values['organizer']) ?>"
                        required
                    >
                </div>

            </div>

            <!-- Date and Time -->
            <div class="form-section-heading mt-4">
                <h2>Date and Schedule</h2>
                <p>Set the date and duration of the event.</p>
            </div>

            <div class="row g-3 mb-3">

                <div class="col-md-6">
                    <label
                        class="form-label"
                        for="event_date"
                    >
                        Event Date <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control"
                        type="date"
                        id="event_date"
                        name="event_date"
                        min="<?= date('Y-m-d') ?>"
                        value="<?= e($values['event_date']) ?>"
                        required
                    >
                </div>

                <div class="col-md-3 col-6">
                    <label
                        class="form-label"
                        for="start_time"
                    >
                        Start Time <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control"
                        type="time"
                        id="start_time"
                        name="start_time"
                        value="<?= e($values['start_time']) ?>"
                        required
                    >
                </div>

                <div class="col-md-3 col-6">
                    <label
                        class="form-label"
                        for="end_time"
                    >
                        End Time <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control"
                        type="time"
                        id="end_time"
                        name="end_time"
                        value="<?= e($values['end_time']) ?>"
                        required
                    >
                </div>

            </div>

            <!-- Venue and Capacity -->
            <div class="form-section-heading mt-4">
                <h2>Location and Capacity</h2>
                <p>Tell students where the event will take place.</p>
            </div>

            <div class="row g-3">

                <div class="col-md-8">
                    <label
                        class="form-label"
                        for="venue"
                    >
                        Venue <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control"
                        type="text"
                        id="venue"
                        name="venue"
                        maxlength="150"
                        placeholder="Enter event venue"
                        value="<?= e($values['venue']) ?>"
                        required
                    >
                </div>

                <div class="col-md-4">
                    <label
                        class="form-label"
                        for="capacity"
                    >
                        Capacity <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control"
                        type="number"
                        id="capacity"
                        name="capacity"
                        min="1"
                        max="100000"
                        step="1"
                        placeholder="100"
                        value="<?= e($values['capacity']) ?>"
                        required
                    >

                    <small class="form-text">
                        Maximum number of attendees.
                    </small>
                </div>

            </div>

            <!-- Form Actions -->
            <div class="form-actions mt-4">

                <button
                    class="btn btn-green"
                    type="submit"
                >
                    Create Event
                </button>

                <a
                    class="btn btn-light"
                    href="dashboard.php"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>