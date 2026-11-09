<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

function imageNotFound(): never
{
    http_response_code(404);
    exit('Image not found.');
}

$verificationId = filter_input(
    INPUT_GET,
    'verification_id',
    FILTER_VALIDATE_INT
);

$type = $_GET['type'] ?? '';

if (!$verificationId || !in_array($type, ['school_id', 'selfie'], true)) {
    imageNotFound();
}

$column = $type === 'school_id'
    ? 'school_id_path'
    : 'selfie_path';

$stmt = $pdo->prepare(
    "SELECT `$column` FROM student_verifications WHERE id = :id LIMIT 1"
);

$stmt->execute(['id' => $verificationId]);

$storedPath = $stmt->fetchColumn();

if (!$storedPath || !is_string($storedPath)) {
    imageNotFound();
}

// Only allow files inside the private verification folder.
if (!str_starts_with($storedPath, 'private_verifications/')) {
    imageNotFound();
}

$filename = substr($storedPath, strlen('private_verifications/'));

if (
    $filename === '' ||
    str_contains($filename, '/') ||
    str_contains($filename, '\\')
) {
    imageNotFound();
}

$baseDir = realpath(__DIR__ . '/../storage/private_verifications');

if ($baseDir === false) {
    imageNotFound();
}

$filePath = realpath($baseDir . DIRECTORY_SEPARATOR . $filename);

if (
    $filePath === false ||
    !str_starts_with($filePath, $baseDir . DIRECTORY_SEPARATOR) ||
    !is_file($filePath)
) {
    imageNotFound();
}

// Check the actual file type, not just its extension.
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath);

$allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/webp'
];

if (!in_array($mimeType, $allowedTypes, true)) {
    imageNotFound();
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header("Content-Security-Policy: default-src 'none'; sandbox");

readfile($filePath);
exit;