<?php
$page_title = "Login";
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        require_once 'includes/db_connect.php';
        
        try {
            // This query now works because the `username` column exists.
            $stmt = $db->prepare("SELECT id, username, email, password, role, first_name, last_name FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            // Debug logging
            if ($user) {
                error_log("Login attempt - User found: ID={$user['id']}, Username={$user['username']}, Email={$user['email']}");
                error_log("Login attempt - Password hash starts with: " . substr($user['password'], 0, 20));
                error_log("Login attempt - Password verify result: " . (password_verify($password, $user['password']) ? 'SUCCESS' : 'FAILED'));
            } else {
                error_log("Login attempt - User NOT found for: $username");
            }

            // This now works because the admin password in the DB is properly hashed.
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];

                header("Location: dashboard.php");
                exit();
            } else {
                if ($user) {
                    $error = 'Invalid username/email or password. (Password mismatch)';
                } else {
                    $error = 'Invalid username/email or password. (User not found)';
                }
            }
        } catch (PDOException $e) {
            // *** IMPORTANT DEBUGGING CHANGE ***
            // This will now show the REAL database error instead of a generic message.
            $error = 'Database Error: ' . $e->getMessage();
            // In production, you would log the error and use the generic message:
            // $error = 'Database error. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center">
                <h4 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>Login</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username or Email</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="text-end mt-1">
                            <a href="<?php echo getPageUrl('forgot_password.php'); ?>" class="text-decoration-none small">
                                <i class="fas fa-key me-1"></i>Forgot Password?
                            </a>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
