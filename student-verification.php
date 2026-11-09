<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_student();

$user = current_user();
$userId = (int) $user['id'];

$uploadDir = __DIR__ . '/storage/private_verifications/';

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create private upload directory.');
    }
}

if (!is_writable($uploadDir)) {
    throw new RuntimeException('Private upload directory is not writable.');
}

$message = '';
$messageType = '';

function verificationEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function saveVerificationImage(
    string $tempPath,
    string $uploadDir,
    array $allowedMimes
): string {
    if (!is_uploaded_file($tempPath)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $fileSize = filesize($tempPath);

    if ($fileSize === false || $fileSize > 5 * 1024 * 1024) {
        throw new RuntimeException('Image must not exceed 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tempPath);

    if (!is_string($mime) || !isset($allowedMimes[$mime])) {
        throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
    }

    // Verify that the uploaded file is actually an image.
    $imageInfo = getimagesize($tempPath);

    if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== $mime) {
        throw new RuntimeException('Invalid image file.');
    }

    $filename = bin2hex(random_bytes(24)) . '.' . $allowedMimes[$mime];
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($tempPath, $destination)) {
        throw new RuntimeException('Unable to save image.');
    }

    return 'private_verifications/' . $filename;
}

// Get existing verification record.
$stmt = $pdo->prepare(
    'SELECT * FROM student_verifications WHERE user_id = ? LIMIT 1'
);
$stmt->execute([$userId]);
$verification = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

// Handle student submission.
if (filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {

    $newFiles = [];

    try {
        // CSRF validation.
        if (!verify_csrf()) {
            http_response_code(403);

            throw new RuntimeException(
                'Invalid security token. Please refresh the page and try again.'
            );
        }

        // Do not allow another submission if already verified.
        if ($verification && $verification['status'] === 'verified') {
            throw new RuntimeException(
                'Your identity has already been verified.'
            );
        }

        // Do not allow another submission while pending.
        if ($verification && $verification['status'] === 'pending') {
            throw new RuntimeException(
                'Your submission is still waiting for admin review.'
            );
        }

        // Validate school ID upload.
        if (
            !isset($_FILES['school_id']) ||
            !is_array($_FILES['school_id']) ||
            ($_FILES['school_id']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        ) {
            throw new RuntimeException('Please upload your school ID.');
        }

        $selfieData = $_POST['selfie'] ?? '';

        // Validate JPEG selfie data URL.
        if (
            !is_string($selfieData) ||
            !preg_match(
                '~^data:image/jpeg;base64,([A-Za-z0-9+/]+={0,2})$~',
                $selfieData,
                $matches
            )
        ) {
            throw new RuntimeException(
                'Please capture your selfie using the camera.'
            );
        }

        $selfieBytes = base64_decode($matches[1], true);

        if (
            $selfieBytes === false ||
            strlen($selfieBytes) === 0 ||
            strlen($selfieBytes) > 5 * 1024 * 1024
        ) {
            throw new RuntimeException('Invalid selfie image or file size.');
        }

        $imageInfo = getimagesizefromstring($selfieBytes);

        if (
            $imageInfo === false ||
            ($imageInfo['mime'] ?? '') !== 'image/jpeg'
        ) {
            throw new RuntimeException('Invalid selfie format.');
        }

        // Save school ID.
        $schoolIdPath = saveVerificationImage(
            $_FILES['school_id']['tmp_name'],
            $uploadDir,
            [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ]
        );

        $newFiles[] = $uploadDir . basename($schoolIdPath);

        // Save selfie.
        $selfieFilename = bin2hex(random_bytes(24)) . '.jpg';
        $selfieFile = $uploadDir . $selfieFilename;

        if (file_put_contents($selfieFile, $selfieBytes, LOCK_EX) === false) {
            throw new RuntimeException('Unable to save selfie.');
        }

        $newFiles[] = $selfieFile;

        $selfiePath = 'private_verifications/' . $selfieFilename;

        if ($verification) {

            // Update previously rejected application.
            $stmt = $pdo->prepare(
                "UPDATE student_verifications
                 SET school_id_path = ?,
                     selfie_path = ?,
                     status = 'pending',
                     admin_notes = NULL,
                     reviewed_at = NULL,
                     reviewed_by = NULL,
                     submitted_at = CURRENT_TIMESTAMP
                 WHERE user_id = ?"
            );

            $stmt->execute([
                $schoolIdPath,
                $selfiePath,
                $userId
            ]);

            // Delete previous files after successful update.
            foreach ([
                $verification['school_id_path'],
                $verification['selfie_path']
            ] as $oldPath) {

                if (
                    is_string($oldPath) &&
                    str_starts_with($oldPath, 'private_verifications/')
                ) {
                    $oldFile = $uploadDir . basename($oldPath);

                    if (is_file($oldFile)) {
                        unlink($oldFile);
                    }
                }
            }

        } else {

            // Insert new verification application.
            $stmt = $pdo->prepare(
                "INSERT INTO student_verifications
                    (user_id, school_id_path, selfie_path, status)
                 VALUES (?, ?, ?, 'pending')"
            );

            $stmt->execute([
                $userId,
                $schoolIdPath,
                $selfiePath
            ]);
        }

        // Redirect after successful submission.
        header('Location: student-verification.php?submitted=1');
        exit;

    } catch (Throwable $e) {

        // Remove newly uploaded files if submission failed.
        foreach ($newFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $message = $e instanceof PDOException
            ? 'A database error occurred. Please try again.'
            : $e->getMessage();

        $messageType = 'error';
    }

    // Refresh verification status.
    $stmt = $pdo->prepare(
        'SELECT * FROM student_verifications WHERE user_id = ? LIMIT 1'
    );
    $stmt->execute([$userId]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Success message after redirect.
if (filter_input(INPUT_GET, 'submitted') === '1') {

    $message = 'Your documents were submitted successfully. Please wait for admin verification.';
    $messageType = 'success';

    $stmt = $pdo->prepare(
        'SELECT * FROM student_verifications WHERE user_id = ? LIMIT 1'
    );
    $stmt->execute([$userId]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Verification | Campus Event Hub</title>

    <link rel="stylesheet" href="assets/css/verification.css">
</head>

<body>

<div class="verification-container">

    <div class="verification-header">
        <h1>Student Identity Verification</h1>
        <p>Verify your student identity to access campus event services.</p>
    </div>

    <?php if ($message): ?>
        <div class="notice <?= verificationEscape($messageType) ?>">
            <?= verificationEscape($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($verification): ?>

        <div class="status-card">

            <h2>Verification Status</h2>

            <?php if ($verification['status'] === 'pending'): ?>

                <div class="status pending">PENDING</div>

                <p>
                    Your documents have been submitted and are awaiting
                    administrator review.
                </p>

            <?php elseif ($verification['status'] === 'verified'): ?>

                <div class="status verified">VERIFIED</div>

                <p>
                    Your student identity has been successfully verified!
                </p>

            <?php else: ?>

                <div class="status rejected">REJECTED</div>

                <p>Your verification was rejected.</p>

                <?php if (!empty($verification['admin_notes'])): ?>

                    <div class="admin-notes">
                        <strong>Admin remarks:</strong>

                        <p>
                            <?= verificationEscape($verification['admin_notes']) ?>
                        </p>
                    </div>

                <?php endif; ?>

                <p>You may submit a new application below.</p>

            <?php endif; ?>

        </div>

    <?php endif; ?>

    <?php if (
        !$verification ||
        $verification['status'] === 'rejected'
    ): ?>

        <form
            method="POST"
            enctype="multipart/form-data"
            class="verification-form"
            id="verificationForm"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= verificationEscape(csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="selfie"
                id="selfie"
            >

            <div class="form-section">

                <h2>1. Upload School ID</h2>

                <p>Upload a clear picture of your valid school ID.</p>

                <input
                    type="file"
                    name="school_id"
                    id="school_id"
                    accept="image/jpeg,image/png,image/webp"
                    required
                >

                <img
                    id="idPreview"
                    class="preview-image"
                    alt="School ID preview"
                    hidden
                >

            </div>

            <div class="form-section">

                <h2>2. Live Selfie Capture</h2>

                <p>Allow camera access and take a clear selfie.</p>

                <video
                    id="camera"
                    autoplay
                    playsinline
                    muted
                ></video>

                <canvas id="canvas" hidden></canvas>

                <div class="camera-buttons">

                    <button type="button" id="startCamera">
                        Start Camera
                    </button>

                    <button type="button" id="captureSelfie">
                        Capture Selfie
                    </button>

                </div>

                <img
                    id="selfiePreview"
                    class="preview-image"
                    alt="Selfie preview"
                    hidden
                >

                <button
                    type="button"
                    id="retakeSelfie"
                    hidden
                >
                    Retake Selfie
                </button>

            </div>

            <button type="submit" class="submit-button">
                Submit for Verification
            </button>

        </form>

    <?php endif; ?>

    <a href="index.php" class="back-link">
        Back to Home
    </a>

</div>

<script src="assets/js/verification.js"></script>

</body>
</html>