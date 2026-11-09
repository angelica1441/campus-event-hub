<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CAMPUS EVENT HUB
| Student Verification Helper Functions
|--------------------------------------------------------------------------
| Uses the existing student_verifications table.
| Requires config/database.php and includes/auth.php
|--------------------------------------------------------------------------
*/

if (!function_exists('getStudentVerification')) {

    function getStudentVerification(
        PDO $pdo,
        int $userId
    ): ?array {

        $stmt = $pdo->prepare(
            "SELECT
                id,
                user_id,
                school_id_path,
                selfie_path,
                status,
                admin_notes,
                submitted_at,
                reviewed_at,
                reviewed_by
             FROM student_verifications
             WHERE user_id = ?
             LIMIT 1"
        );

        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}

if (!function_exists('isStudentVerified')) {

    function isStudentVerified(
        PDO $pdo,
        int $userId
    ): bool {

        $stmt = $pdo->prepare(
            "SELECT status
             FROM student_verifications
             WHERE user_id = ?
             LIMIT 1"
        );

        $stmt->execute([$userId]);

        return $stmt->fetchColumn() === 'verified';
    }
}

if (!function_exists('getVerificationStatus')) {

    function getVerificationStatus(
        ?string $status
    ): array {

        $statuses = [

            'pending' => [
                'label' => 'Pending Review',
                'class' => 'pending',
                'icon' => 'bi-clock-history',
                'description' =>
                    'Your documents are waiting for administrator review.'
            ],

            'verified' => [
                'label' => 'Verified',
                'class' => 'verified',
                'icon' => 'bi-patch-check-fill',
                'description' =>
                    'Your student identity has been successfully verified.'
            ],

            'rejected' => [
                'label' => 'Rejected',
                'class' => 'rejected',
                'icon' => 'bi-x-circle-fill',
                'description' =>
                    'Your submission was rejected. Please review the admin remarks.'
            ]
        ];

        return $statuses[$status ?? ''] ?? [
            'label' => 'Not Verified',
            'class' => 'unverified',
            'icon' => 'bi-shield-exclamation',
            'description' =>
                'Please submit your school ID and live selfie.'
        ];
    }
}

if (!function_exists('requireStudentVerification')) {

    function requireStudentVerification(
        PDO $pdo
    ): void {

        require_student();

        $user = current_user();
        $userId = (int) $user['id'];

        if (!isStudentVerified($pdo, $userId)) {

            set_flash(
                'warning',
                'Please complete your student identity verification before registering for events.'
            );

            redirect('student-verification.php');
        }
    }
}