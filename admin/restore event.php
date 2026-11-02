
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

// Allow POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/event-history.php');
}

// Verify CSRF token
require_valid_csrf();

// Validate event ID
$id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    set_flash('danger', 'Invalid event ID.');
    redirect('admin/event-history.php');
}

try {
    // Check whether the event exists
    $checkStmt = $pdo->prepare(
        'SELECT id, event_date, status
         FROM events
         WHERE id = ?'
    );

    $checkStmt->execute([$id]);
    $event = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        set_flash('danger', 'Event not found.');
        redirect('admin/event-history.php');
    }

    // Only future or today's events can be restored
    if ($event['event_date'] < date('Y-m-d')) {
        set_flash(
            'warning',
            'Past events cannot be restored to active listings.'
        );
        redirect('admin/event-history.php');
    }

    // Check whether it is already active
    if ($event['status'] === 'active') {
        set_flash(
            'warning',
            'This event is already active.'
        );
        redirect('admin/event-history.php');
    }

    // Restore event
    $restoreStmt = $pdo->prepare(
        "UPDATE events
         SET status = 'active'
         WHERE id = ?
         AND event_date >= CURDATE()"
    );

    $restoreStmt->execute([$id]);

    if ($restoreStmt->rowCount() > 0) {
        set_flash(
            'success',
            'Event successfully restored to active listings.'
        );
    } else {
        set_flash(
            'warning',
            'The event was not restored. Please check its current status and date.'
        );
    }

} catch (PDOException $e) {
    error_log('Restore event error: ' . $e->getMessage());

    set_flash(
        'danger',
        'A database error occurred while restoring the event.'
    );
}

redirect('admin/event-history.php');
