<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'campus_event_hub';
$dbuser = getenv('DB_USER') ?: 'root';
$dbpass = getenv('DB_PASS') ?: '';

if ($host === '' || $dbname === '' || $dbuser === '') {
    error_log('Campus Event Hub: Missing database configuration.');

    http_response_code(500);
    exit('Database configuration is incomplete. Check config/database.php.');
}

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

    $pdo = new PDO(
        $dsn,
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]
    );

} catch (PDOException $exception) {
    error_log(
        'Campus Event Hub DB connection failed: ' .
        $exception->getMessage()
    );

    http_response_code(500);

    exit(
        'Unable to connect to the database. ' .
        'Make sure MySQL is running in XAMPP and check ' .
        'the database name and credentials in config/database.php.'
    );
}