<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_student();

$userId = (int)current_user()['id'];
$errors = [];

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();

    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $account = $stmt->fetch();

    if (!$account || !password_verify($current, $account['password'])) {
        $errors[] = 'Your current password is incorrect.';
    }

    if (strlen($new) < 8) {
        $errors[] = 'New password must contain at least 8 characters.';
    }

    if ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    }

    if ($new !== '' && $current === $new) {
        $errors[] = 'Your new password must be different from your current password.';
    }

    if (!$errors) {
        $hashed = password_hash($new, PASSWORD_DEFAULT);

        $update = $pdo->prepare(
            "UPDATE users SET password = ? WHERE id = ?"
        );
        $update->execute([$hashed, $userId]);

        set_flash('success', 'Password changed successfully.');
        redirect('student/profile.php');
    }
}

$pageTitle = 'Change Password';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.password-page {
    max-width:650px;
    margin:45px auto;
    padding:25px 18px;
    color:#effff4;
}
.password-card {
    padding:30px;
    border:1px solid #285b43;
    border-radius:20px;
    background:linear-gradient(145deg,#103b2a,#071f17);
}
.password-card h1 { font-size:30px; }
.password-card p { color:#b9d8c5; }
.password-card label {
    display:block;
    margin:18px 0 7px;
    font-weight:800;
    font-size:13px;
}
.password-card input {
    width:100%;
    padding:13px;
    color:#fff;
    background:#061d14;
    border:1px solid #416b53;
    border-radius:10px;
}
.password-card input:focus {
    outline:none;
    border-color:#65ed9c;
}
.password-card button,
.password-card a {
    display:inline-block;
    margin-top:18px;
    padding:12px 18px;
    border:0;
    border-radius:10px;
    background:linear-gradient(120deg,#baffd0,#5ce99c);
    color:#082719 !important;
    font-weight:900;
    text-decoration:none;
    cursor:pointer;
}
.password-error {
    padding:12px;
    margin:12px 0;
    color:#ffe0e4;
    background:#59272d;
    border-radius:10px;
}
</style>

<main class="password-page">
    <section class="password-card">
        <h1>Change Password</h1>
        <p>Keep your Campus Events Hub account secure.</p>

        <?php if ($errors): ?>
            <div class="password-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= e(csrf_token()) ?>">

            <label for="current_password">Current Password</label>
            <input type="password" id="current_password"
                   name="current_password" required autocomplete="current-password">

            <label for="new_password">New Password</label>
            <input type="password" id="new_password"
                   name="new_password" minlength="8"
                   required autocomplete="new-password">

            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password"
                   name="confirm_password" minlength="8"
                   required autocomplete="new-password">

            <button type="submit">Update Password</button>
            <a href="<?= e(app_url('student/profile.php')) ?>"
               style="background:#17432e;color:#fff!important">
                Cancel
            </a>
        </form>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>