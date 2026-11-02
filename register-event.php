<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

$user = current_user();

if (!$user || strtolower((string)($user['role'] ?? '')) !== 'student') {
    redirect('admin/dashboard.php');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
}

if (!$id || $id < 1) {
    $_SESSION['flash_error'] = 'Invalid event.';
    redirect('events.php');
}

$userId = (int)($user['id'] ?? $_SESSION['user_id'] ?? 0);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id, title, event_date, status, capacity
         FROM events
         WHERE id = ?
         FOR UPDATE"
    );

    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Event not found.';
        redirect('events.php');
    }

    $eventStatus = strtolower((string)$event['status']);

    if (
        $eventStatus !== 'upcoming' ||
        (
            !empty($event['event_date']) &&
            date('Y-m-d', strtotime((string)$event['event_date'])) < date('Y-m-d')
        )
    ) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Registration is closed for this event.';
        redirect('event-details.php?id=' . $id);
    }

    $stmt = $pdo->prepare(
        "SELECT id, status
         FROM registrations
         WHERE user_id = ? AND event_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([$userId, $id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        $existing &&
        strtolower((string)$existing['status']) === 'registered'
    ) {
        $pdo->commit();

        $_SESSION['flash_success'] =
            'You are already registered for this event.';

        redirect('my-registrations.php');
    }

    $capacity = (int)($event['capacity'] ?? 0);

    if ($capacity > 0) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM registrations
             WHERE event_id = ?
             AND LOWER(TRIM(status)) = 'registered'"
        );

        $stmt->execute([$id]);

        if ((int)$stmt->fetchColumn() >= $capacity) {
            $pdo->rollBack();

            $_SESSION['flash_error'] =
                'This event is already full.';

            redirect('event-details.php?id=' . $id);
        }
    }

    if ($existing) {
        $stmt = $pdo->prepare(
            "UPDATE registrations
             SET status = 'registered',
                 registered_at = CURRENT_TIMESTAMP
             WHERE id = ? AND user_id = ?"
        );

        $stmt->execute([(int)$existing['id'], $userId]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO registrations
                (user_id, event_id, status, registered_at)
             VALUES
                (?, ?, 'registered', CURRENT_TIMESTAMP)"
        );

        $stmt->execute([$userId, $id]);
    }

    $pdo->commit();

    $_SESSION['flash_success'] =
        'Registration successful! Your ticket is ready.';

    redirect('my-registrations.php');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Register event: ' . $e->getMessage());

    $_SESSION['flash_error'] =
        'Registration failed. Check the registrations table columns and try again.';

    redirect('event-details.php?id=' . $id);
}