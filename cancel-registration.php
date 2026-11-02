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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        (string)$_SESSION['csrf_token'],
        (string)($_POST['csrf_token'] ?? '')
    )
) {
    $_SESSION['flash_error'] =
        'Security token expired. Refresh and try again.';

    redirect('my-registrations.php');
}

$id = filter_input(
    INPUT_POST,
    'registration_id',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    $_SESSION['flash_error'] = 'Invalid registration.';
    redirect('my-registrations.php');
}

try {
    $stmt = $pdo->prepare(
        "UPDATE registrations
         SET status = 'cancelled'
         WHERE id = ?
           AND user_id = ?
           AND LOWER(TRIM(status)) = 'registered'"
    );

    $stmt->execute([
        $id,
        (int)($user['id'] ?? 0)
    ]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['flash_success'] = 'Registration cancelled.';
    } else {
        $_SESSION['flash_error'] =
            'Registration not found or already cancelled.';
    }

} catch (PDOException $exception) {
    error_log('Cancel registration: ' . $exception->getMessage());

    $_SESSION['flash_error'] =
        'Could not cancel registration. Check database status values.';
}

redirect('my-registrations.php');