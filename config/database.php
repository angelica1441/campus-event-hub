<?php
declare(strict_types=1);

$host = 'localhost';
$dbname = 'campus_event_hub';
$dbuser = 'root';
$dbpass = '';

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

    $pdo = new PDO(
        $dsn,
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to connect to the local database. Check XAMPP and database settings.');
}