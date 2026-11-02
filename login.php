<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $role = strtolower(trim((string) (current_user()['role'] ?? '')));
    redirect($role === 'admin' ? 'admin/dashboard.php' : 'index.php');
}

$pageTitle = 'Log in';
$basePath = app_url();
$errors = [];
$identity = trim((string) ($_POST['identity'] ?? ''));

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    require_valid_csrf();
    $password = (string) ($_POST['password'] ?? '');

    if ($identity === '' || $password === '') {
        $errors[] = 'Enter your username or email and password.';
    } elseif (mb_strlen($identity) > 254) {
        $errors[] = 'Username or email is too long.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT id, full_name, username, email, password, role
                FROM users
                WHERE username = :username OR email = :email
                LIMIT 1
            ");

            $stmt->execute([
                'username' => $identity,
                'email' => $identity
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $role = strtolower(trim((string) $user['role']));

                if (!in_array($role, ['admin', 'student'], true)) {
                    $errors[] = 'Invalid account role. Please contact the administrator.';
                } else {
                    session_regenerate_id(true);

                    $_SESSION['user'] = [
                        'id' => (int) $user['id'],
                        'full_name' => $user['full_name'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $role
                    ];

                    set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                    redirect($role === 'admin' ? 'admin/dashboard.php' : 'index.php');
                }
            } else {
                $errors[] = 'Invalid login details. Please check and try again.';
            }
        } catch (PDOException $ex) {
            error_log('Login Error: ' . $ex->getMessage());
            $errors[] = 'Unable to log in right now. Please try again later.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ==========================================
   CAMPUS EVENT HUB - DARK GLASS LOGIN
   Fixed white inputs and browser autofill
========================================== */

.login-section {
    --green: #35e69a;
    --lime: #b9e879;
    --muted: #b5cfc1;

    position: relative;
    isolation: isolate;
    min-height: calc(100vh - 70px);
    padding: 55px 20px;
    overflow: hidden;
    color: #f4fff8 !important;

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

.login-section *,
.login-section *::before,
.login-section *::after {
    box-sizing: border-box;
}

.login-section .auth-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(330px, .85fr);
    align-items: center;
    gap: clamp(30px, 5vw, 75px);
    max-width: 1080px;
    margin: 0 auto;
}

.login-section .auth-intro {
    animation: loginIn .7s ease both;
}

.login-section .eyebrow {
    color: var(--green) !important;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 2.5px;
}

.login-section .auth-intro h1 {
    margin: 20px 0;
    color: #fff !important;
    font-size: clamp(40px, 5vw, 64px);
    font-weight: 900;
    letter-spacing: -2px;
    line-height: 1.08;
}

.login-section .auth-intro h1 em {
    color: var(--lime) !important;
    font-style: normal;
}

.login-section .auth-intro > p {
    max-width: 470px;
    color: var(--muted) !important;
    font-size: 14px;
    line-height: 1.9;
}

.login-section .auth-benefit {
    display: flex;
    align-items: flex-start;
    gap: 13px;
    margin-top: 22px;
}

.login-section .auth-benefit > span {
    display: grid;
    place-items: center;
    flex: 0 0 42px;
    height: 42px;
    color: var(--green) !important;
    background: rgba(53, 230, 154, .09) !important;
    border: 1px solid rgba(53, 230, 154, .33) !important;
    border-radius: 13px;
    font-size: 19px;
}

.login-section .auth-benefit strong {
    display: block;
    margin: 2px 0 5px;
    color: #fff !important;
    font-size: 14px;
}

.login-section .auth-benefit small {
    color: var(--muted) !important;
    font-size: 12px;
    line-height: 1.7;
}

/* LOGIN CARD */

.login-section .login-card {
    position: relative;
    width: 100%;
    padding: clamp(25px, 4vw, 40px);
    overflow: hidden;

    color: #f4fff8 !important;
    background: linear-gradient(
        145deg,
        rgba(15, 54, 38, .88),
        rgba(3, 22, 16, .94)
    ) !important;

    border: 1px solid rgba(185, 255, 218, .25) !important;
    border-radius: 25px;
    box-shadow:
        0 25px 80px rgba(0, 0, 0, .45),
        inset 0 1px rgba(255, 255, 255, .08);

    backdrop-filter: blur(22px);
    -webkit-backdrop-filter: blur(22px);
    animation: loginIn .8s .1s ease both;
}

.login-section .login-card::before {
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

/* LOGO */

.login-section .ptc-center-logo {
    display: flex;
    justify-content: center;
    margin: 0 auto 22px;
}

.login-section .ptc-center-logo img {
    display: block;
    width: 118px;
    height: 118px;
    padding: 5px;
    object-fit: contain;
    background: #fff !important;
    border: 2px solid var(--green);
    border-radius: 50%;
    box-shadow:
        0 0 0 6px rgba(53, 230, 154, .07),
        0 0 32px rgba(53, 230, 154, .20);
    transition: transform .3s ease;
}

.login-section .ptc-center-logo img:hover {
    transform: scale(1.05);
}

/* CARD HEADING */

.login-section .auth-card-heading {
    text-align: center;
}

.login-section .auth-card-heading h2 {
    margin: 12px 0;
    color: #fff !important;
    font-size: clamp(26px, 3vw, 34px);
    font-weight: 900;
    letter-spacing: -1px;
}

.login-section .auth-card-heading p {
    margin: 0 0 25px;
    color: var(--muted) !important;
    font-size: 13px;
    line-height: 1.8;
}

/* LABELS */

.login-section .form-label {
    display: block;
    margin-bottom: 8px;
    color: #e9fff1 !important;
    font-size: 13px;
    font-weight: 800;
}

/* INPUT WRAPPER */

.login-section .login-input-wrap {
    position: relative;
    width: 100%;
}

.login-section .login-input-icon {
    position: absolute;
    top: 50%;
    left: 15px;
    z-index: 2;
    width: 19px;
    height: 19px;
    color: #8fcaab !important;
    transform: translateY(-50%);
    pointer-events: none;
}

/*
   IMPORTANT:
   Force dark background on inputs.
   !important overrides Bootstrap and other CSS.
*/

.login-section .login-input-wrap input.form-control,
.login-section .login-input-wrap input.form-control.form-control-lg,
.login-section .login-input-wrap input[type="text"],
.login-section .login-input-wrap input[type="password"],
.login-section .login-input-wrap input[type="email"] {
    display: block;
    width: 100%;
    min-height: 53px;
    padding: 12px 45px;

    color: #f4fff8 !important;
    -webkit-text-fill-color: #f4fff8 !important;

    background-color: #071d14 !important;
    background-image: none !important;

    border: 1px solid rgba(180, 231, 200, .25) !important;
    border-radius: 12px !important;

    outline: none !important;
    box-shadow: none !important;

    font-family: inherit;
    font-size: 14px;
    line-height: 1.5;

    transition:
        border-color .25s ease,
        background-color .25s ease,
        box-shadow .25s ease;
}

.login-section .login-input-wrap input::placeholder {
    color: #91b3a0 !important;
    -webkit-text-fill-color: #91b3a0 !important;
    opacity: 1 !important;
}

.login-section .login-input-wrap input:hover {
    background-color: #092319 !important;
    border-color: rgba(53, 230, 154, .45) !important;
}

.login-section .login-input-wrap input:focus,
.login-section .login-input-wrap input.form-control:focus,
.login-section .login-input-wrap input.form-control:focus-visible {
    color: #fff !important;
    -webkit-text-fill-color: #fff !important;

    background-color: #061b12 !important;
    background-image: none !important;

    border-color: var(--green) !important;
    outline: none !important;
    box-shadow: 0 0 0 3px rgba(53, 230, 154, .15) !important;
}

/* FIX CHROME / EDGE AUTOFILL WHITE OR BLUE FIELDS */

.login-section input:-webkit-autofill,
.login-section input:-webkit-autofill:hover,
.login-section input:-webkit-autofill:focus,
.login-section input:-webkit-autofill:active {
    -webkit-text-fill-color: #f4fff8 !important;
    caret-color: #f4fff8 !important;

    -webkit-box-shadow: 0 0 0 1000px #071d14 inset !important;
    box-shadow: 0 0 0 1000px #071d14 inset !important;

    background-color: #071d14 !important;
    background-image: none !important;

    border: 1px solid rgba(180, 231, 200, .25) !important;

    transition: background-color 9999s ease-out 0s !important;
}

/* PASSWORD INPUT */

.login-section .login-input-wrap input.password-input {
    padding-right: 53px !important;
}

.login-section .password-toggle {
    position: absolute;
    top: 50%;
    right: 7px;
    z-index: 3;

    display: grid;
    place-items: center;

    width: 38px;
    height: 38px;

    color: #a5cdb5 !important;
    background: transparent !important;
    border: 0 !important;
    border-radius: 9px;

    outline: none !important;
    box-shadow: none !important;

    transform: translateY(-50%);
    cursor: pointer;
    transition: .2s ease;
}

.login-section .password-toggle:hover,
.login-section .password-toggle:focus {
    color: var(--green) !important;
    background: rgba(53, 230, 154, .09) !important;
    outline: none !important;
    box-shadow: none !important;
}

.login-section .password-toggle svg {
    display: block;
    width: 19px;
    height: 19px;
}

/* SUBMIT BUTTON */

.login-section .login-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;

    width: 100%;
    min-height: 53px;
    margin-top: 8px;
    padding: 13px 18px;

    color: #062117 !important;
    background: linear-gradient(
        110deg,
        #35e69a,
        #b9e879
    ) !important;

    border: 0 !important;
    border-radius: 13px !important;

    font-size: 15px;
    font-weight: 900;

    box-shadow: 0 10px 28px rgba(53, 230, 154, .17);
    cursor: pointer;
    transition: transform .25s ease, box-shadow .25s ease;
}

.login-section .login-submit:hover {
    color: #062117 !important;
    background: linear-gradient(
        110deg,
        #54efad,
        #c8f394
    ) !important;

    transform: translateY(-2px);
    box-shadow: 0 14px 32px rgba(53, 230, 154, .28);
}

.login-section .login-submit:active {
    transform: translateY(0);
}

/* REGISTER LINK */

.login-section .auth-switch {
    margin: 22px 0 0;
    padding-top: 19px;

    color: var(--muted) !important;
    border-top: 1px solid rgba(180, 255, 213, .15);

    text-align: center;
    font-size: 13px;
}

.login-section .auth-switch a {
    color: var(--green) !important;
    font-weight: 900;
    text-decoration: none;
}

.login-section .auth-switch a:hover {
    color: var(--lime) !important;
    text-decoration: underline;
}

/* ERROR MESSAGE */

.login-section .alert-danger {
    margin-bottom: 22px;
    padding: 13px 16px;

    color: #ffe4e8 !important;
    background: rgba(150, 24, 47, .25) !important;
    border: 1px solid rgba(255, 130, 150, .35) !important;
    border-radius: 12px;
}

.login-section .alert-danger ul {
    padding-left: 20px;
}

.login-section .alert-danger li {
    margin: 4px 0;
}

/* ANIMATIONS */

@keyframes loginIn {
    from {
        opacity: 0;
        transform: translateY(18px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* RESPONSIVE */

@media (max-width: 767px) {
    .login-section {
        padding: 35px 14px;
        background-attachment: scroll;
    }

    .login-section .auth-grid {
        grid-template-columns: 1fr;
        gap: 25px;
    }

    .login-section .auth-intro h1 {
        font-size: clamp(39px, 9vw, 51px);
    }

    .login-section .login-card {
        padding: 27px 21px;
    }
}

/* ACCESSIBILITY */

@media (prefers-reduced-motion: reduce) {
    .login-section *,
    .login-section *::before,
    .login-section *::after {
        animation: none !important;
        transition: none !important;
    }
}
</style>

<section class="auth-section login-section">
    <div class="container auth-grid">

        <!-- LEFT INTRO -->
        <div class="auth-intro">
            <span class="eyebrow">YOUR CAMPUS, ONE HUB</span>

            <h1>
                Good things<br>
                happen when<br>
                <em>you show up.</em>
            </h1>

            <p>
                Welcome to Campus Event Hub. Log in to manage your event
                registrations and discover what's happening around
                Pateros Technological College.
            </p>

            <div class="auth-benefit">
                <span aria-hidden="true">✓</span>
                <div>
                    <strong>Simple and secure</strong>
                    <small>
                        Keep your account and event registrations organized
                        in one place.
                    </small>
                </div>
            </div>

            <div class="auth-benefit">
                <span aria-hidden="true">✦</span>
                <div>
                    <strong>Stay involved</strong>
                    <small>
                        Discover workshops, seminars, and activities
                        around campus.
                    </small>
                </div>
            </div>
        </div>

        <!-- LOGIN CARD -->
        <div class="auth-card login-card">

            <div class="ptc-center-logo">
                <img
                    src="https://paterostechnologicalcollege.edu.ph/PTC%20Life/CSJS/GROOVERS.jpg"
                    alt="Pateros Technological College United Groovers logo"
                    width="118"
                    height="118"
                >
            </div>

            <div class="auth-card-heading">
                <span class="eyebrow">WELCOME BACK</span>
                <h2>Log in to your account</h2>
                <p>
                    Enter your details below to continue your campus journey.
                </p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert" aria-live="polite">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= e($basePath) ?>login.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <!-- USERNAME / EMAIL -->
                <div class="mb-4">
                    <label class="form-label" for="identity">
                        Username or email
                    </label>

                    <div class="login-input-wrap">
                        <svg
                            class="login-input-icon"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="8" r="4"></circle>
                            <path d="M5 21v-2a7 7 0 0 1 14 0v2"></path>
                        </svg>

                        <input
                            class="form-control form-control-lg"
                            type="text"
                            id="identity"
                            name="identity"
                            value="<?= e($identity) ?>"
                            placeholder="Enter your username or email"
                            autocomplete="username"
                            maxlength="254"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <!-- PASSWORD -->
                <div class="mb-4">
                    <label class="form-label" for="password">
                        Password
                    </label>

                    <div class="login-input-wrap">
                        <svg
                            class="login-input-icon"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>

                        <input
                            class="form-control form-control-lg password-input"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            class="password-toggle"
                            type="button"
                            id="togglePassword"
                            aria-label="Show password"
                            aria-controls="password"
                            aria-pressed="false"
                        >
                            <svg
                                id="passwordEyeIcon"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- LOGIN BUTTON -->
                <button
                    class="btn btn-green btn-lg w-100 login-submit"
                    type="submit"
                >
                    <span>Log in to your account</span>
                    <span aria-hidden="true">→</span>
                </button>
            </form>

            <p class="auth-switch">
                Don't have an account?
                <a href="<?= e($basePath) ?>register.php">Create one</a>
            </p>

        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const password = document.getElementById('password');
    const button = document.getElementById('togglePassword');
    const icon = document.getElementById('passwordEyeIcon');

    if (!password || !button || !icon) {
        return;
    }

    const eyeOpen =
        '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"></path>' +
        '<circle cx="12" cy="12" r="3"></circle>';

    const eyeClosed =
        '<path d="M3 3l18 18"></path>' +
        '<path d="M10.6 5.1A10.9 10.9 0 0 1 12 5c6.4 0 10 7 10 7a17.8 17.8 0 0 1-4 4.8"></path>' +
        '<path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.5 0 2.8-.4 4-1"></path>' +
        '<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>';

    button.addEventListener('click', function () {
        const show = password.type === 'password';

        password.type = show ? 'text' : 'password';

        button.setAttribute(
            'aria-label',
            show ? 'Hide password' : 'Show password'
        );

        button.setAttribute(
            'aria-pressed',
            show ? 'true' : 'false'
        );

        icon.innerHTML = show ? eyeClosed : eyeOpen;
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
