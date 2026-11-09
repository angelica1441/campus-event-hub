<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_student();

$userId = (int) current_user()['id'];
$errors = [];
$success = '';

$stmt = $pdo->prepare(
    "SELECT id, full_name, username, email, profile_picture, created_at
     FROM users WHERE id = ? LIMIT 1"
);
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    exit('Student account not found.');
}
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if ($fullName === '' || mb_strlen($fullName) > 100) {
            $errors[] = 'Enter a valid full name (maximum 100 characters).';
        }

        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
            $errors[] = 'Username must contain 3–50 letters, numbers, or underscores.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = 'Enter a valid email address.';
        }

        if (!$errors) {
            $check = $pdo->prepare(
                "SELECT id FROM users
                 WHERE (username = ? OR email = ?) AND id != ?
                 LIMIT 1"
            );
            $check->execute([$username, $email, $userId]);

            if ($check->fetch()) {
                $errors[] = 'Username or email is already being used by another account.';
            } else {
                $update = $pdo->prepare(
                    "UPDATE users
                     SET full_name = ?, username = ?, email = ?
                     WHERE id = ?"
                );
                $update->execute([$fullName, $username, $email, $userId]);

                $_SESSION['user']['full_name'] = $fullName;
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['email'] = $email;

                set_flash('success', 'Your profile information has been updated.');
                redirect('student/profile.php');
            }
        }
    }

    if ($action === 'upload_picture') {
        if (
            !isset($_FILES['profile_picture']) ||
            $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK
        ) {
            $errors[] = 'Please select a valid image to upload.';
        } else {
            $file = $_FILES['profile_picture'];

            if ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Image must not exceed 2 MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);

                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($allowed[$mime])) {
                    $errors[] = 'Only JPG, PNG, and WEBP images are allowed.';
                } elseif (@getimagesize($file['tmp_name']) === false) {
                    $errors[] = 'The uploaded file is not a valid image.';
                } else {
                    $uploadDir = __DIR__ . '/../uploads/profile-pictures/';

                    if (
                        !is_dir($uploadDir) &&
                        !mkdir($uploadDir, 0755, true) &&
                        !is_dir($uploadDir)
                    ) {
                        $errors[] = 'Unable to create the profile picture folder.';
                    } else {
                        $filename = bin2hex(random_bytes(16))
                            . '.' . $allowed[$mime];

                        if (move_uploaded_file(
                            $file['tmp_name'],
                            $uploadDir . $filename
                        )) {
                            $update = $pdo->prepare(
                                "UPDATE users SET profile_picture = ? WHERE id = ?"
                            );
                            $update->execute([$filename, $userId]);

                            if (!empty($user['profile_picture'])) {
                                $oldPath = $uploadDir . basename(
                                    $user['profile_picture']
                                );

                                if (is_file($oldPath)) {
                                    unlink($oldPath);
                                }
                            }

                            set_flash('success', 'Profile picture updated successfully.');
                            redirect('student/profile.php');
                        } else {
                            $errors[] = 'Unable to save your uploaded image.';
                        }
                    }
                }
            }
        }
    }
}

// Refresh user details after processing
$stmt = $pdo->prepare(
    "SELECT id, full_name, username, email, profile_picture, created_at
     FROM users WHERE id = ? LIMIT 1"
);
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$picture = !empty($user['profile_picture'])
    ? app_url('uploads/profile-pictures/' . rawurlencode($user['profile_picture']))
    : '';

$pageTitle = 'My Student Profile';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.student-profile {
    max-width: 1100px;
    margin: auto;
    padding: 32px 20px 60px;
    color: #edfff3;
}
.sp-hero {
    padding: 30px;
    border: 1px solid #286044;
    border-radius: 22px;
    background: linear-gradient(120deg,#073b27,#116744);
}
.sp-hero h1 { font-size: clamp(28px,4vw,40px); margin: 8px 0; }
.sp-hero p { color:#c3e8d1; margin:0; }
.sp-grid {
    display:grid;
    grid-template-columns:300px minmax(0,1fr);
    gap:22px;
    margin-top:24px;
}
.sp-card {
    padding:25px;
    background:linear-gradient(145deg,#103b2a,#071f17);
    border:1px solid #285641;
    border-radius:18px;
    box-shadow:0 12px 30px #00150d44;
}
.sp-avatar {
    width:145px;
    height:145px;
    margin:8px auto 18px;
    display:grid;
    place-items:center;
    overflow:hidden;
    border:4px solid #69e9a0;
    border-radius:50%;
    background:#174c34;
    color:#c4ffd7;
    font-size:52px;
    font-weight:900;
}
.sp-avatar img { width:100%;height:100%;object-fit:cover; }
.sp-center { text-align:center; }
.sp-muted { color:#b8d9c5; font-size:13px; }
.sp-label {
    display:block;
    margin:16px 0 7px;
    color:#dfffea;
    font-size:13px;
    font-weight:800;
}
.sp-input {
    width:100%;
    padding:13px 14px;
    color:#fff;
    background:#061d14;
    border:1px solid #416b53;
    border-radius:10px;
    outline:none;
}
.sp-input:focus { border-color:#65ed9c; }
.sp-btn {
    display:inline-flex;
    justify-content:center;
    align-items:center;
    padding:12px 17px;
    border:1px solid #477456;
    border-radius:10px;
    background:#17432e;
    color:#eafff1 !important;
    font-weight:800;
    text-decoration:none;
    cursor:pointer;
}
.sp-btn.primary {
    background:linear-gradient(120deg,#baffd0,#5ce99c);
    color:#082719 !important;
    border-color:#9bffc0;
}
.sp-btn.full { width:100%; margin-top:16px; }
.sp-links {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
    margin-top:20px;
}
.sp-link {
    padding:17px;
    border:1px solid #315d44;
    border-radius:13px;
    background:#0d3021;
    color:#eafff1 !important;
    text-decoration:none;
    font-weight:800;
}
.sp-link small {
    display:block;
    margin-top:6px;
    color:#b4d6c0;
    font-weight:400;
}
.sp-alert {
    margin-top:15px;
    padding:13px 16px;
    border-radius:10px;
    background:#164d32;
    color:#c8ffda;
}
.sp-alert.error { background:#59272d;color:#ffe0e4; }
.sp-alert ul { margin-bottom:0; }
@media(max-width:760px) {
    .sp-grid { grid-template-columns:1fr; }
    .sp-links { grid-template-columns:1fr; }
    .student-profile { padding:20px 13px 40px; }
}
</style>

<main class="student-profile">

    <section class="sp-hero">
        <div style="color:#aaffc6;font-weight:900;letter-spacing:2px">
            STUDENT ACCOUNT
        </div>
        <h1>My Profile</h1>
        <p>Manage your personal information and campus event activities.</p>
    </section>

    <?php if ($errors): ?>
        <div class="sp-alert error">
            <strong>Please check the following:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="sp-grid">

        <section class="sp-card sp-center">

            <div class="sp-avatar">
                <?php if ($picture): ?>
                    <img src="<?= e($picture) ?>" alt="Student profile picture">
                <?php else: ?>
                    <?= e(strtoupper(substr($user['full_name'], 0, 1))) ?>
                <?php endif; ?>
            </div>

            <h2><?= e($user['full_name']) ?></h2>
            <p class="sp-muted">@<?= e($user['username']) ?></p>

            <p class="sp-muted">
                Student Member
            </p>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token"
                       value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload_picture">

                <label class="sp-label" for="profile_picture">
                    Upload Profile Picture
                </label>

                <input
                    class="sp-input"
                    type="file"
                    id="profile_picture"
                    name="profile_picture"
                    accept="image/jpeg,image/png,image/webp"
                    required
                >

                <small class="sp-muted">
                    JPG, PNG, or WEBP. Maximum 2 MB.
                </small>

                <button class="sp-btn primary full" type="submit">
                    Upload Picture
                </button>
            </form>

        </section>

        <section class="sp-card">

            <h2>Personal Information</h2>
            <p class="sp-muted">Update the details associated with your account.</p>

            <form method="POST">
                <input type="hidden" name="csrf_token"
                       value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_profile">

                <label class="sp-label" for="full_name">Full Name</label>
                <input class="sp-input" type="text" id="full_name"
                       name="full_name" maxlength="100"
                       value="<?= e($user['full_name']) ?>" required>

                <label class="sp-label" for="username">Username</label>
                <input class="sp-input" type="text" id="username"
                       name="username" minlength="3" maxlength="50"
                       pattern="[A-Za-z0-9_]{3,50}"
                       value="<?= e($user['username']) ?>" required>

                <label class="sp-label" for="email">Email Address</label>
                <input class="sp-input" type="email" id="email"
                       name="email" maxlength="100"
                       value="<?= e($user['email']) ?>" required>

                <label class="sp-label">Member Since</label>
                <input class="sp-input" type="text"
                       value="<?= e(date('F j, Y', strtotime($user['created_at']))) ?>"
                       disabled>

                <button class="sp-btn primary full" type="submit">
                    Save Changes
                </button>
            </form>

            <div class="sp-links">
                <a class="sp-link" href="<?= e(app_url('student/change-password.php')) ?>">
                    🔒 Change Password
                    <small>Secure your student account</small>
                </a>

                <a class="sp-link" href="<?= e(app_url('student/registration-history.php')) ?>">
                    🎟️ Registration History
                    <small>View your registered events</small>
                </a>

                <a class="sp-link" href="<?= e(app_url('student/attendance-history.php')) ?>">
                    ✓ Attendance Records
                    <small>View events you attended</small>
                </a>

                <a class="sp-link" href="<?= e(app_url('my-registrations.php')) ?>">
                    📅 My Registrations
                    <small>Manage your event tickets</small>
                </a>
            </div>

        </section>

    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>