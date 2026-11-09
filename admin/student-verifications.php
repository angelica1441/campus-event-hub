<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Student Verifications';

// Process admin review and deletion.
if (filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST') {
    require_valid_csrf();

    $verificationId = filter_input(
        INPUT_POST,
        'verification_id',
        FILTER_VALIDATE_INT
    );

    $decision = $_POST['decision'] ?? '';
    $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));

    if (!$verificationId) {
        set_flash('danger', 'Invalid verification request.');
        redirect('admin/student-verifications.php');
    }

    // DELETE REJECTED SUBMISSION
    if ($decision === 'delete') {

        $check = $pdo->prepare(
            "SELECT id, status, school_id_path, selfie_path
             FROM student_verifications
             WHERE id = ?
             LIMIT 1"
        );

        $check->execute([$verificationId]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            set_flash('danger', 'Verification record not found.');
            redirect('admin/student-verifications.php');
        }

        // Only rejected submissions can be deleted.
        if ($existing['status'] !== 'rejected') {
            set_flash(
                'danger',
                'Only rejected verification submissions can be deleted.'
            );
            redirect('admin/student-verifications.php');
        }

        // Resolve the private upload directory.
        $privateDirectory = realpath(
            dirname(__DIR__) . '/storage/private_verifications'
        );

        // Delete the database record.
        $delete = $pdo->prepare(
            "DELETE FROM student_verifications WHERE id = ?"
        );

        $delete->execute([$verificationId]);

        // Delete uploaded files only if they are inside
        // storage/private_verifications.
        if ($privateDirectory !== false) {

            $deletePrivateFile = static function (
                ?string $storedPath
            ) use ($privateDirectory): void {

                if (empty($storedPath)) {
                    return;
                }

                $normalizedPath = str_replace(
                    '\\',
                    '/',
                    $storedPath
                );

                // Accept only the expected private storage path.
                if (
                    !str_starts_with(
                        $normalizedPath,
                        'storage/private_verifications/'
                    )
                ) {
                    return;
                }

                $projectRoot = dirname(__DIR__);

                $fullPath = realpath(
                    $projectRoot . DIRECTORY_SEPARATOR .
                    str_replace(
                        '/',
                        DIRECTORY_SEPARATOR,
                        $normalizedPath
                    )
                );

                if ($fullPath === false || !is_file($fullPath)) {
                    return;
                }

                $allowedPrefix = rtrim(
                    $privateDirectory,
                    DIRECTORY_SEPARATOR
                ) . DIRECTORY_SEPARATOR;

                // Prevent deleting files outside the private directory.
                if (
                    str_starts_with(
                        strtolower($fullPath),
                        strtolower($allowedPrefix)
                    )
                ) {
                    unlink($fullPath);
                }
            };

            $deletePrivateFile($existing['school_id_path']);
            $deletePrivateFile($existing['selfie_path']);
        }

        set_flash(
            'success',
            'Rejected verification submission has been deleted.'
        );

        redirect('admin/student-verifications.php');
    }

    // APPROVE OR REJECT SUBMISSION
    $notesLength = function_exists('mb_strlen')
        ? mb_strlen($adminNotes, 'UTF-8')
        : strlen($adminNotes);

    if (
        !in_array($decision, ['verified', 'rejected'], true) ||
        $notesLength > 1000
    ) {
        set_flash('danger', 'Invalid verification request.');
        redirect('admin/student-verifications.php');
    }

    $check = $pdo->prepare(
        "SELECT id, status
         FROM student_verifications
         WHERE id = ?
         LIMIT 1"
    );

    $check->execute([$verificationId]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        set_flash('danger', 'Verification record not found.');
        redirect('admin/student-verifications.php');
    }

    // Prevent changing an already verified record.
    if ($existing['status'] === 'verified') {
        set_flash('danger', 'This student has already been verified.');
        redirect('admin/student-verifications.php');
    }

    $admin = current_user();

    $update = $pdo->prepare(
        "UPDATE student_verifications
         SET status = ?,
             admin_notes = ?,
             reviewed_at = CURRENT_TIMESTAMP,
             reviewed_by = ?
         WHERE id = ?"
    );

    $update->execute([
        $decision,
        $adminNotes !== '' ? $adminNotes : null,
        (int) $admin['id'],
        $verificationId
    ]);

    set_flash(
        'success',
        'Student verification has been updated successfully.'
    );

    redirect('admin/student-verifications.php');
}

// Get all student verification submissions.
$stmt = $pdo->query(
    "SELECT
        sv.id,
        sv.user_id,
        sv.school_id_path,
        sv.selfie_path,
        sv.status,
        sv.admin_notes,
        sv.submitted_at,
        sv.reviewed_at,
        u.full_name,
        u.email
     FROM student_verifications sv
     INNER JOIN users u ON u.id = sv.user_id
     ORDER BY
        CASE WHEN sv.status = 'pending' THEN 0 ELSE 1 END,
        sv.submitted_at DESC"
);

$verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.verification-page {
    max-width: 1150px;
    padding: 35px 20px 60px;
    margin: auto;
}

.verification-heading {
    color: #12372a;
    font-weight: 800;
    margin-bottom: 8px;
}

.verification-subtitle {
    color: #64756b;
    margin-bottom: 28px;
}

.verification-card {
    background: #fff;
    border: 1px solid #dce9e0;
    border-radius: 18px;
    padding: 24px;
    margin-bottom: 22px;
    box-shadow: 0 7px 22px rgba(18, 55, 42, .07);
}

.verification-photo {
    width: 100%;
    height: 210px;
    object-fit: contain;
    background: #f2f7f3;
    border: 1px solid #dce8df;
    border-radius: 12px;
}

.verification-label {
    color: #12372a;
    font-weight: 700;
    margin-bottom: 10px;
}

.verification-status {
    display: inline-block;
    padding: 7px 14px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.status-pending {
    color: #8a5a00;
    background: #fff2cc;
}

.status-verified {
    color: #126b3a;
    background: #d9f7e5;
}

.status-rejected {
    color: #a12626;
    background: #ffe0e0;
}

.review-notes {
    min-height: 95px;
    resize: vertical;
    border-radius: 10px;
}

.review-notes:focus {
    border-color: #198754;
    box-shadow: 0 0 0 3px rgba(25, 135, 84, .12);
}

.verification-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.delete-verification-form {
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid #f0dada;
}

.empty-verifications {
    padding: 55px 20px;
    text-align: center;
    background: #fff;
    border: 1px dashed #b8d2c0;
    border-radius: 18px;
}

@media (max-width: 576px) {
    .verification-card {
        padding: 16px;
    }

    .verification-photo {
        height: 180px;
    }
}
</style>

<main class="verification-page">

    <div class="mb-4">
        <h2 class="verification-heading">
            <i class="bi bi-person-vcard-fill"></i>
            Student Verifications
        </h2>

        <p class="verification-subtitle">
            Review student School IDs and selfies before approving
            their identity verification.
        </p>
    </div>

    <?php if (empty($verifications)): ?>

        <div class="empty-verifications">
            <i class="bi bi-inbox fs-1 text-success"></i>

            <h5 class="mt-3 fw-bold">
                No Verification Submissions
            </h5>

            <p class="text-muted mb-0">
                Student verification requests will appear here.
            </p>
        </div>

    <?php else: ?>

        <?php foreach ($verifications as $verification): ?>

            <?php
            $status = (string) ($verification['status'] ?? 'pending');

            if (!in_array($status, ['pending', 'verified', 'rejected'], true)) {
                $status = 'pending';
            }
            ?>

            <section class="verification-card">

                <div class="d-flex justify-content-between
                            align-items-start flex-wrap gap-3 mb-4">

                    <div>
                        <h5 class="fw-bold mb-1">
                            <?= e($verification['full_name']) ?>
                        </h5>

                        <p class="text-muted mb-1">
                            <?= e($verification['email']) ?>
                        </p>

                        <small class="text-muted">
                            Submitted:
                            <?= e($verification['submitted_at']) ?>
                        </small>
                    </div>

                    <span class="verification-status status-<?= e($status) ?>">
                        <?= e($status) ?>
                    </span>

                </div>

                <div class="row g-3 mb-4">

                    <div class="col-md-6">
                        <p class="verification-label">School ID</p>

                        <a
                            href="verification-image.php?verification_id=<?= (int) $verification['id'] ?>&type=school_id"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <img
                                src="verification-image.php?verification_id=<?= (int) $verification['id'] ?>&type=school_id"
                                class="verification-photo"
                                alt="Student School ID"
                                loading="lazy"
                            >
                        </a>
                    </div>

                    <div class="col-md-6">
                        <p class="verification-label">Student Selfie</p>

                        <a
                            href="verification-image.php?verification_id=<?= (int) $verification['id'] ?>&type=selfie"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <img
                                src="verification-image.php?verification_id=<?= (int) $verification['id'] ?>&type=selfie"
                                class="verification-photo"
                                alt="Student Selfie"
                                loading="lazy"
                            >
                        </a>
                    </div>

                </div>

                <?php if ($status !== 'verified'): ?>

                    <form
                        method="POST"
                        action="student-verifications.php"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <input
                            type="hidden"
                            name="verification_id"
                            value="<?= (int) $verification['id'] ?>"
                        >

                        <div class="mb-3">

                            <label class="form-label fw-bold">
                                Admin Remarks
                            </label>

                            <textarea
                                name="admin_notes"
                                class="form-control review-notes"
                                maxlength="1000"
                                placeholder="Write remarks or reason for rejection..."
                            ><?= e($verification['admin_notes'] ?? '') ?></textarea>

                        </div>

                        <div class="verification-actions">

                            <button
                                type="submit"
                                name="decision"
                                value="verified"
                                class="btn btn-success"
                                onclick="return confirm('Approve this student verification?')"
                            >
                                <i class="bi bi-check-circle-fill"></i>
                                Approve Student
                            </button>

                            <button
                                type="submit"
                                name="decision"
                                value="rejected"
                                class="btn btn-danger"
                                onclick="return confirm('Reject this student verification?')"
                            >
                                <i class="bi bi-x-circle-fill"></i>
                                Reject
                            </button>

                        </div>

                    </form>

                <?php else: ?>

                    <div class="alert alert-success mb-0">
                        <i class="bi bi-patch-check-fill"></i>
                        This student has been verified.
                    </div>

                    <?php if (!empty($verification['admin_notes'])): ?>
                        <p class="mt-3 mb-0">
                            <strong>Admin Remarks:</strong>
                            <?= e($verification['admin_notes']) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($verification['reviewed_at'])): ?>
                        <small class="text-muted d-block mt-2">
                            Reviewed:
                            <?= e($verification['reviewed_at']) ?>
                        </small>
                    <?php endif; ?>

                <?php endif; ?>

                <?php if ($status === 'rejected'): ?>

                    <form
                        method="POST"
                        action="student-verifications.php"
                        class="delete-verification-form"
                        onsubmit="return confirm('Permanently delete this rejected submission and its uploaded pictures? This action cannot be undone.');"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrf_token()) ?>"
                        >

                        <input
                            type="hidden"
                            name="verification_id"
                            value="<?= (int) $verification['id'] ?>"
                        >

                        <button
                            type="submit"
                            name="decision"
                            value="delete"
                            class="btn btn-outline-danger"
                        >
                            <i class="bi bi-trash3-fill"></i>
                            Delete Rejected Submission
                        </button>

                    </form>

                <?php endif; ?>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>