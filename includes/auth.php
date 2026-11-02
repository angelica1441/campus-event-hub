<?php
declare(strict_types=1);

// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Escape output safely
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

// Application base URL
if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $root = defined('APP_BASE_URL')
            ? APP_BASE_URL
            : '/campus-event-hub/';

        return rtrim($root, '/') . '/' . ltrim($path, '/');
    }
}

// Check whether a user is logged in
if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return isset($_SESSION['user']['id']);
    }
}

// Get current logged-in user
if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        $user = $_SESSION['user'] ?? null;

        return is_array($user) ? $user : null;
    }
}

// Check whether current user is a student
if (!function_exists('is_student')) {
    function is_student(): bool
    {
        return is_logged_in()
            && ($_SESSION['user']['role'] ?? '') === 'student';
    }
}

// Check whether current user is an administrator
if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return is_logged_in()
            && ($_SESSION['user']['role'] ?? '') === 'admin';
    }
}

// Require login
if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!is_logged_in()) {
            set_flash(
                'warning',
                'Please log in to continue.'
            );

            redirect('login.php');
        }
    }
}

// Require student account
if (!function_exists('require_student')) {
    function require_student(): void
    {
        if (!is_logged_in()) {
            set_flash(
                'warning',
                'Please log in with your student account to continue.'
            );

            redirect('login.php');
        }

        if (!is_student()) {
            set_flash(
                'danger',
                'This page is available to student accounts only.'
            );

            if (is_admin()) {
                redirect('admin/dashboard.php');
            }

            redirect('index.php');
        }
    }
}

// Require administrator account
if (!function_exists('require_admin')) {
    function require_admin(): void
    {
        if (!is_logged_in()) {
            set_flash(
                'warning',
                'Please log in to continue.'
            );

            redirect('login.php');
        }

        if (!is_admin()) {
            set_flash(
                'danger',
                'You do not have permission to access that page.'
            );

            redirect('index.php');
        }
    }
}

// Generate CSRF token
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (
            !isset($_SESSION['csrf_token']) ||
            !is_string($_SESSION['csrf_token']) ||
            $_SESSION['csrf_token'] === ''
        ) {
            $_SESSION['csrf_token'] = bin2hex(
                random_bytes(32)
            );
        }

        return $_SESSION['csrf_token'];
    }
}

// Verify CSRF token
if (!function_exists('verify_csrf')) {
    function verify_csrf(): bool
    {
        $submitted = $_POST['csrf_token'] ?? '';
        $stored = $_SESSION['csrf_token'] ?? '';

        return is_string($submitted)
            && is_string($stored)
            && $submitted !== ''
            && $stored !== ''
            && hash_equals($stored, $submitted);
    }
}

// Require valid CSRF token
if (!function_exists('require_valid_csrf')) {
    function require_valid_csrf(): void
    {
        if (!verify_csrf()) {
            http_response_code(400);

            exit(
                'Invalid form token. Please go back, refresh the page, and try again.'
            );
        }
    }
}

// Save flash message
if (!function_exists('set_flash')) {
    function set_flash(
        string $type,
        string $message
    ): void {
        $allowedTypes = [
            'success',
            'danger',
            'warning',
            'info',
            'primary',
            'secondary'
        ];

        if (!in_array($type, $allowedTypes, true)) {
            $type = 'info';
        }

        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}

// Retrieve flash message once
if (!function_exists('get_flash')) {
    function get_flash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;

        unset($_SESSION['flash']);

        return is_array($flash) ? $flash : null;
    }
}

// Redirect to another page
if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . app_url($path));
        exit;
    }
}

// Format date
if (!function_exists('format_date')) {
    function format_date(string $date): string
    {
        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return e($date);
        }

        return date('F j, Y', $timestamp);
    }
}

// Format time
if (!function_exists('format_time')) {
    function format_time(string $time): string
    {
        $timestamp = strtotime($time);

        if ($timestamp === false) {
            return e($time);
        }

        return date('g:i A', $timestamp);
    }
}