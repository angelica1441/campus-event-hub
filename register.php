<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $user = current_user();

    if (strtolower(trim((string) ($user['role'] ?? ''))) === 'admin') {
        redirect('admin/dashboard.php');
    }

    redirect('index.php');
}

$pageTitle = 'Create Account';
$basePath = rtrim(app_url(), '/') . '/';

$errors = [];
$fullName = '';
$username = '';
$email = '';

$password = '';
$confirmPassword = '';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    require_valid_csrf();

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $nameLength = function_exists('mb_strlen')
        ? mb_strlen($fullName, 'UTF-8')
        : strlen($fullName);

    $emailLength = function_exists('mb_strlen')
        ? mb_strlen($email, 'UTF-8')
        : strlen($email);

    if ($fullName === '' || $nameLength > 100) {
        $errors[] = 'Enter your full name. It must not exceed 100 characters.';
    }

    if (
        $username === '' ||
        !preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)
    ) {
        $errors[] = 'Username must contain 3–50 letters, numbers, or underscores.';
    }

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $emailLength > 100
    ) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    /*
     * Check whether the username or email already exists.
     */
    if (empty($errors)) {
        try {
            $checkStmt = $pdo->prepare(
                'SELECT id
                 FROM users
                 WHERE username = ? OR email = ?
                 LIMIT 1'
            );

            $checkStmt->execute([$username, $email]);

            if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = 'Username or email is already in use. Please choose another.';
            }
        } catch (PDOException $e) {
            error_log('Account duplicate check failed: ' . $e->getMessage());

            $errors[] = 'Unable to verify your account details right now. Please check your database configuration.';
        }
    }

    /*
     * Create the account only when validation succeeds.
     */
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            if ($hashedPassword === false) {
                throw new RuntimeException('Password hashing failed.');
            }

            $insertStmt = $pdo->prepare(
                'INSERT INTO users
                    (full_name, username, email, password, role)
                 VALUES
                    (?, ?, ?, ?, ?)'
            );

            $insertStmt->execute([
                $fullName,
                $username,
                $email,
                $hashedPassword,
                'student'
            ]);

            /*
             * The role is assigned by the server.
             * Users cannot register themselves as administrators.
             */
            set_flash(
                'success',
                'Account created successfully. You can now log in.'
            );

            redirect('login.php');
        } catch (PDOException $e) {
            error_log('Account registration failed: ' . $e->getMessage());

            if ($e->getCode() === '23000') {
                $errors[] = 'Username or email is already in use. Please choose another.';
            } else {
                $errors[] = 'Unable to create your account. Please verify that your users table has the required columns.';
            }
        } catch (RuntimeException $e) {
            error_log('Account registration error: ' . $e->getMessage());

            $errors[] = 'Unable to secure your password right now. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.register-section {
    --green: #35e69a;
    --lime: #b9e879;
    --muted: #b5cfc1;

    position: relative;
    isolation: isolate;
    min-height: calc(100vh - 70px);
    padding: 45px 20px 55px;
    overflow: hidden;
    color: #f4fff8;

    background:
        linear-gradient(
            120deg,
            rgba(2, 20, 14, .88),
            rgba(3, 36, 24, .79),
            rgba(2, 18, 15, .91)
        ),
        url("https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQaDr4BHrsVP2iwadYYAwEqLEwLCqcL2LYQzUk41_Qxeg&s=10")
        center / cover no-repeat fixed;
}

.register-section *,
.register-section *::before,
.register-section *::after {
    box-sizing: border-box;
}

.register-section .auth-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(350px, 1fr);
    align-items: center;
    gap: clamp(30px, 5vw, 65px);
    max-width: 1120px;
    margin: 0 auto;
}

.register-section .auth-intro {
    animation: registerIn .7s ease both;
}

.register-section .eyebrow {
    color: var(--green);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 2.3px;
}

.register-section .auth-intro h1 {
    margin: 20px 0;
    color: #fff;
    font-size: clamp(40px, 5vw, 62px);
    font-weight: 900;
    letter-spacing: -2px;
    line-height: 1.08;
}

.register-section .auth-intro h1 em {
    color: var(--lime);
    font-style: normal;
}

.register-section .auth-intro > p {
    max-width: 475px;
    color: var(--muted);
    font-size: 14px;
    line-height: 1.9;
}

.register-section .auth-benefit {
    display: flex;
    align-items: flex-start;
    gap: 13px;
    margin-top: 20px;
}

.register-section .auth-benefit > span {
    display: grid;
    place-items: center;
    flex: 0 0 42px;
    height: 42px;
    color: var(--green);
    background: #35e69a18;
    border: 1px solid #35e69a55;
    border-radius: 13px;
    font-size: 19px;
}

.register-section .auth-benefit strong {
    display: block;
    margin: 2px 0 5px;
    color: #fff;
    font-size: 14px;
}

.register-section .auth-benefit small {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.7;
}

.register-section .auth-card {
    position: relative;
    width: 100%;
    padding: clamp(25px, 3.5vw, 38px);
    overflow: hidden;
    background: linear-gradient(
        145deg,
        rgba(15, 54, 38, .79),
        rgba(3, 22, 16, .89)
    );
    border: 1px solid #b9ffda40;
    border-radius: 25px;
    box-shadow: 0 25px 80px #0007, inset 0 1px #ffffff18;
    backdrop-filter: blur(22px);
    -webkit-backdrop-filter: blur(22px);
    animation: registerIn .8s .1s ease both;
}

.register-section .auth-card::before {
    position: absolute;
    top: 0;
    left: 10%;
    right: 10%;
    height: 2px;
    content: "";
    background: linear-gradient(
        90deg,
        transparent,
        var(--green),
        var(--lime),
        transparent
    );
}

.register-section .ptc-center-logo {
    display: flex;
    justify-content: center;
    margin: 0 auto 20px;
}

.register-section .ptc-center-logo img {
    display: block;
    width: 110px;
    height: 110px;
    padding: 5px;
    object-fit: contain;
    background: #fff;
    border: 2px solid var(--green);
    border-radius: 50%;
    box-shadow: 0 0 0 6px #35e69a12, 0 0 32px #35e69a35;
    transition: transform .3s ease;
}

.register-section .ptc-center-logo img:hover {
    transform: scale(1.05);
}

.register-section .auth-card-heading {
    text-align: center;
}

.register-section .auth-card-heading h2 {
    margin: 11px 0;
    color: #fff;
    font-size: clamp(27px, 3vw, 34px);
    font-weight: 900;
    letter-spacing: -1px;
}

.register-section .auth-card-heading p {
    margin: 0 0 22px;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.8;
}

.register-section .form-label {
    display: block;
    margin-bottom: 8px;
    color: #e9fff1;
    font-size: 13px;
    font-weight: 800;
}

.register-section .form-control {
    width: 100%;
    min-height: 48px;
    padding: 11px 14px;
    color: #f4fff8 !important;
    background: #02160fbd !important;
    border: 1px solid #b4e7c83b !important;
    border-radius: 12px !important;
    box-shadow: none !important;
    font-size: 14px;
    transition: .25s ease;
}

.register-section .form-control::placeholder {
    color: #91b3a0 !important;
}

.register-section .form-control:focus {
    color: #fff !important;
    background: #031b12ed !important;
    border-color: var(--green) !important;
    outline: none !important;
    box-shadow: 0 0 0 3px #35e69a25 !important;
}

.register-section .form-text {
    color: #a5c5b2 !important;
    font-size: 11px;
}

.register-section .password-wrap {
    position: relative;
}

.register-section .password-wrap .form-control {
    padding-right: 65px;
}

.register-section .password-toggle {
    position: absolute;
    top: 50%;
    right: 8px;
    z-index: 2;
    padding: 6px 8px;
    color: var(--green);
    background: transparent;
    border: 0;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 800;
    transform: translateY(-50%);
    cursor: pointer;
}

.register-section .password-toggle:hover {
    color: #fff;
    background: #35e69a18;
}

.register-section .register-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    min-height: 52px;
    margin-top: 8px;
    padding: 13px 18px;
    color: #062117 !important;
    background: linear-gradient(110deg, #35e69a, #b9e879) !important;
    border: 0 !important;
    border-radius: 13px !important;
    font-weight: 900;
    box-shadow: 0 10px 28px #35e69a2b;
    cursor: pointer;
    transition: .25s ease;
}

.register-section .register-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 32px #35e69a45;
}

.register-section .auth-switch {
    margin: 21px 0 0;
    padding-top: 18px;
    color: var(--muted);
    border-top: 1px solid #b4ffd526;
    text-align: center;
    font-size: 13px;
}

.register-section .auth-switch a {
    color: var(--green);
    font-weight: 900;
    text-decoration: none;
}

.register-section .auth-switch a:hover {
    text-decoration: underline;
}

.register-section .alert-danger {
    padding: 14px 16px;
    color: #ffe4e8;
    background: #96182f50;
    border: 1px solid #ff82965c;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.7;
}

.register-section .alert-danger ul {
    padding-left: 20px;
}

.register-section input:-webkit-autofill,
.register-section input:-webkit-autofill:focus {
    -webkit-text-fill-color: #f4fff8 !important;
    -webkit-box-shadow: 0 0 0 1000px #10271c inset !important;
}

@keyframes registerIn {
    from {
        opacity: 0;
        transform: translateY(18px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 767px) {
    .register-section {
        padding: 35px 14px;
        background-attachment: scroll;
    }

    .register-section .auth-grid {
        grid-template-columns: 1fr;
        gap: 25px;
    }

    .register-section .auth-intro h1 {
        font-size: clamp(39px, 9vw, 51px);
    }

    .register-section .auth-card {
        padding: 25px 20px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .register-section *,
    .register-section *::before,
    .register-section *::after {
        animation: none !important;
        transition: none !important;
    }
}
</style>

<section class="auth-section register-section">
    <div class="container auth-grid">

        <div class="auth-intro">
            <span class="eyebrow">WELCOME TO CAMPUS LIFE</span>

            <h1>
                Be part of<br>
                <em>something good.</em>
            </h1>

            <p>
                Your campus journey starts here. Create your account to
                discover exciting events, join activities, and keep your
                registrations organized in one place.
            </p>

            <div class="auth-benefit">
                <span aria-hidden="true">✓</span>
                <div>
                    <strong>Everything in one place</strong>
                    <small>Keep track of your event registrations with ease.</small>
                </div>
            </div>

            <div class="auth-benefit">
                <span aria-hidden="true">✦</span>
                <div>
                    <strong>Make campus memories</strong>
                    <small>Explore workshops, seminars, and student activities.</small>
                </div>
            </div>

            <div class="auth-benefit">
                <span aria-hidden="true">↗</span>
                <div>
                    <strong>Stay connected</strong>
                    <small>Take part in activities happening around campus.</small>
                </div>
            </div>
        </div>

        <div class="auth-card">

            <div class="ptc-center-logo">
                <img
                    src="https://paterostechnologicalcollege.edu.ph/PTC%20Life/CSJS/GROOVERS.jpg"
                    alt="Pateros Technological College logo"
                    width="110"
                    height="110"
                    onerror="this.onerror=null;this.src='https://placehold.co/110x110/ffffff/087f5b?text=PTC';"
                >
            </div>

            <div class="auth-card-heading">
                <span class="eyebrow">YOUR NEXT CHAPTER STARTS HERE</span>
                <h2>Create your account</h2>
                <p>Enter your information below to get started.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert" aria-live="polite">
                    <strong>Please check the following:</strong>

                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form
                method="POST"
                action="<?= e($basePath) ?>register.php"
                accept-charset="UTF-8"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <div class="mb-3">
                    <label for="full_name" class="form-label">Full name</label>

                    <input
                        type="text"
                        class="form-control"
                        id="full_name"
                        name="full_name"
                        value="<?= e($fullName) ?>"
                        maxlength="100"
                        autocomplete="name"
                        placeholder="Enter your full name"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>

                    <input
                        type="text"
                        class="form-control"
                        id="username"
                        name="username"
                        value="<?= e($username) ?>"
                        minlength="3"
                        maxlength="50"
                        pattern="[A-Za-z0-9_]{3,50}"
                        autocomplete="username"
                        placeholder="Choose a username"
                        required
                    >

                    <div class="form-text">
                        Use 3–50 letters, numbers, or underscores.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>

                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        value="<?= e($email) ?>"
                        maxlength="100"
                        autocomplete="email"
                        placeholder="you@example.com"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>

                    <div class="password-wrap">
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Create a password"
                            required
                        >

                        <button
                            class="password-toggle"
                            type="button"
                            data-toggle-password="password"
                            aria-label="Show password"
                        >
                            Show
                        </button>
                    </div>

                    <div class="form-text">
                        Use at least 8 characters.
                    </div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label">
                        Confirm password
                    </label>

                    <div class="password-wrap">
                        <input
                            type="password"
                            class="form-control"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Re-enter your password"
                            required
                        >

                        <button
                            class="password-toggle"
                            type="button"
                            data-toggle-password="confirm_password"
                            aria-label="Show password"
                        >
                            Show
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn register-submit">
                    Create my account <span aria-hidden="true">→</span>
                </button>
            </form>

            <p class="auth-switch">
                Already have an account?
                <a href="<?= e($basePath) ?>login.php">Log in</a>
            </p>

        </div>
    </div>
</section>

<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
    button.addEventListener('click', function () {
        const inputId = button.getAttribute('data-toggle-password');
        const input = document.getElementById(inputId);

        if (!input) {
            return;
        }

        const showPassword = input.type === 'password';

        input.type = showPassword ? 'text' : 'password';
        button.textContent = showPassword ? 'Hide' : 'Show';

        button.setAttribute(
            'aria-label',
            showPassword ? 'Hide password' : 'Show password'
        );
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>