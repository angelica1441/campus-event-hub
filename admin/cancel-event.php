
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/dashboard.php');
}

require_valid_csrf();

$id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    set_flash('danger', 'Invalid event.');
    redirect('admin/dashboard.php');
}

try {
    $stmt = $pdo->prepare("
        UPDATE events
        SET status = 'cancelled'
        WHERE id = ?
          AND status IN ('upcoming', 'ongoing')
    ");

    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        set_flash(
            'success',
            'Event cancelled successfully. Registration records have been preserved.'
        );
    } else {
        set_flash(
            'warning',
            'Event was not found or is already cancelled, completed, or unavailable for cancellation.'
        );
    }

} catch (PDOException $exception) {
    error_log('Cancel event error: ' . $exception->getMessage());

    set_flash(
        'danger',
        'Unable to cancel the event. Please try again.'
    );
}

redirect('admin/dashboard.php');
