<?php
$page_title = "Forgot Password";
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        require_once 'includes/db_connect.php';

        try {
            // Check if user exists
            $stmt = $db->prepare("SELECT id, email, first_name, last_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Invalidate any existing tokens for this user (prevent multiple active tokens)
                $stmt = $db->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE user_id = ? AND used = FALSE");
                $stmt->execute([$user['id']]);

                // Generate unique token
                $token = bin2hex(random_bytes(32));

                // Use DATE_ADD with NOW() to ensure consistent timezone handling
                // Set expiration to 24 hours from now for better user experience
                $stmt = $db->prepare("
                    INSERT INTO password_reset_tokens (user_id, token, expires_at)
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                ");
                $stmt->execute([$user['id'], $token]);

                // Generate reset link
                $reset_link = getPageUrl('reset_password.php') . '?token=' . $token;

                // In production, send email here
                // For now, we'll show the link on screen
                // mail($user['email'], "Password Reset Request", "Click here to reset your password: $reset_link");

                $success = '<div class="alert alert-warning border-warning">
                               <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>DEVELOPMENT MODE</h5>
                               <p class="mb-2">In production, an email would be sent to: <strong>' . htmlspecialchars($user['email']) . '</strong></p>
                               <hr>
                               <p class="mb-2">For testing, use this link to reset your password:</p>
                               <p class="mb-2"><small class="text-muted"><i class="fas fa-clock me-1"></i>Link valid for 24 hours</small></p>
                               <div class="d-grid">
                                   <a href="' . htmlspecialchars($reset_link) . '" class="btn btn-primary" target="_blank">
                                       <i class="fas fa-key me-2"></i>Reset Password Now
                                   </a>
                               </div>
                           </div>';
            } else {
                // Email not found - show error in development, generic message in production
                $success = '<div class="alert alert-warning">
                               <h6><i class="fas fa-info-circle me-2"></i>Development Mode - Email Not Found</h6>
                               <p class="mb-0">The email <strong>' . htmlspecialchars($email) . '</strong> is not registered in the system.</p>
                               <small class="text-muted">Note: In production, this would show a generic message for security.</small>
                           </div>';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center">
                <h4 class="mb-0"><i class="fas fa-key me-2"></i>Forgot Password</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-4">
                        Enter your email address and we'll send you instructions to reset your password.
                    </p>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   placeholder="your.email@example.com" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <a href="<?php echo getPageUrl('login.php'); ?>" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
