<?php
$page_title = "Reset Password";
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'includes/db_connect.php';

$error = '';
$success = '';
$token_valid = false;
$user_id = null;

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $error = 'Invalid or missing reset token.';
} else {
    $token = $_GET['token'];

    try {
        // Validate token - use NOW() for consistent timezone handling
        $stmt = $db->prepare("
            SELECT prt.*, u.email, u.first_name,
                   prt.expires_at,
                   NOW() as server_time,
                   TIMESTAMPDIFF(MINUTE, NOW(), prt.expires_at) as minutes_remaining
            FROM password_reset_tokens prt
            JOIN users u ON prt.user_id = u.id
            WHERE prt.token = ? AND prt.used = FALSE
        ");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch();

        if ($token_data) {
            // Log for debugging
            error_log("Token validation - Expires: {$token_data['expires_at']}, Current: {$token_data['server_time']}, Minutes remaining: {$token_data['minutes_remaining']}");

            // Check if token is still valid (not expired)
            if ($token_data['minutes_remaining'] > 0) {
                $token_valid = true;
                $user_id = $token_data['user_id'];
            } else {
                $error = 'This reset link has expired (' . abs($token_data['minutes_remaining']) . ' minutes ago). Please request a new one.';
            }
        } else {
            // Check if token exists but is used
            $stmt = $db->prepare("SELECT used FROM password_reset_tokens WHERE token = ?");
            $stmt->execute([$token]);
            $expired_token = $stmt->fetch();

            if ($expired_token && $expired_token['used']) {
                $error = 'This reset link has already been used. Please request a new one.';
            } else {
                $error = 'Invalid reset link.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        error_log("Token validation error: " . $e->getMessage());
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Debug: Log the operation
            error_log("Resetting password for user_id: $user_id");

            // Update user's password
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $result = $stmt->execute([$hashed_password, $user_id]);
            $rows_affected = $stmt->rowCount();

            error_log("Password update - Rows affected: $rows_affected");

            if ($rows_affected > 0) {
                // Mark token as used
                $stmt = $db->prepare("UPDATE password_reset_tokens SET used = TRUE, used_at = NOW() WHERE token = ?");
                $stmt->execute([$token]);

                $success = 'Your password has been reset successfully. You can now login with your new password.';
                $token_valid = false; // Prevent form from showing again
            } else {
                $error = 'Failed to update password. User not found.';
                error_log("Password reset failed - no rows affected for user_id: $user_id");
            }
        } catch (PDOException $e) {
            $error = 'Database error while resetting password: ' . $e->getMessage();
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center">
                <h4 class="mb-0"><i class="fas fa-lock me-2"></i>Reset Password</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?php echo getPageUrl('forgot_password.php'); ?>" class="btn btn-primary">
                            Request New Reset Link
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?php echo getPageUrl('login.php'); ?>" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($token_valid && !$success): ?>
                    <?php if (isset($token_data) && $token_data['minutes_remaining'] < 120): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Time remaining:</strong>
                            <?php
                                $minutes = $token_data['minutes_remaining'];
                                if ($minutes >= 60) {
                                    $hours = floor($minutes / 60);
                                    $mins = $minutes % 60;
                                    echo $hours . ' hour' . ($hours > 1 ? 's' : '') . ' and ' . $mins . ' minute' . ($mins != 1 ? 's' : '');
                                } else {
                                    echo $minutes . ' minute' . ($minutes != 1 ? 's' : '');
                                }
                            ?>
                        </div>
                    <?php endif; ?>

                    <p class="text-muted mb-4">
                        Please enter your new password below.
                    </p>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password"
                                   minlength="8" placeholder="At least 8 characters" required>
                            <small class="text-muted">Must be at least 8 characters long</small>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                   minlength="8" placeholder="Re-enter your password" required>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Password Requirements:</strong>
                            <ul class="mb-0 mt-2">
                                <li>At least 8 characters long</li>
                                <li>Should contain a mix of letters and numbers</li>
                                <li>Avoid using common words or personal information</li>
                            </ul>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Reset Password
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if (!$token_valid && !$success): ?>
                    <div class="text-center mt-3">
                        <a href="<?php echo getPageUrl('login.php'); ?>" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Back to Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Client-side password matching validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please try again.');
                return false;
            }

            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return false;
            }
        });

        // Real-time password match indicator
        const confirmInput = document.getElementById('confirm_password');
        const newPasswordInput = document.getElementById('new_password');

        confirmInput.addEventListener('input', function() {
            if (this.value && newPasswordInput.value) {
                if (this.value === newPasswordInput.value) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
