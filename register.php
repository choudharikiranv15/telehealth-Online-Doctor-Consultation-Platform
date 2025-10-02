<?php
$page_title = "Register";
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Get specializations for dropdown (needed if user selects doctor role)
require_once 'includes/db_connect.php';
try {
    $stmt = $db->query("SELECT id, name FROM specializations ORDER BY name");
    $specializations = $stmt->fetchAll();
} catch (PDOException $e) {
    $specializations = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $role = $_POST['role'];
    $phone = trim($_POST['phone']);
    $specialization_id = isset($_POST['specialization_id']) ? (int)$_POST['specialization_id'] : null;

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($role)) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Phone number must be exactly 10 digits.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one letter and one number.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $first_name) || !preg_match('/^[a-zA-Z\s]+$/', $last_name)) {
        $error = 'First name and last name can only contain letters and spaces.';
    } elseif ($role === 'doctor' && empty($specialization_id)) {
        $error = 'Please select a specialization for doctor registration.';
    } else {
        require_once 'includes/db_connect.php';
        
        try {
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                $db->beginTransaction();

                // Hash password and insert user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Set different status based on role - doctors need admin approval
                $status = ($role === 'doctor') ? 'pending_approval' : 'active';
                $stmt = $db->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $role, $first_name, $last_name, $phone, $status]);

                $user_id = $db->lastInsertId();

                // Create profile based on role
                if ($role === 'doctor') {
                    $license_number = isset($_POST['license_number']) ? trim($_POST['license_number']) : '';
                    $stmt = $db->prepare("INSERT INTO doctor_profiles (user_id, specialization_id, license_number, consultation_fee) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $specialization_id, $license_number, 500.00]); // Default fee
                } elseif ($role === 'patient') {
                    // This query now works because the patient_profiles table exists.
                    $stmt = $db->prepare("INSERT INTO patient_profiles (user_id, date_of_birth, gender) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $_POST['date_of_birth'], $_POST['gender']]);
                }

                $db->commit();
                if ($role === 'doctor') {
                    $success = 'Registration successful! Your doctor profile is pending admin approval. You will be notified once approved and can then start accepting patients.';
                } else {
                    $success = 'Registration successful! You can now login.';
                }
            }
        } catch (PDOException $e) {
            $db->rollBack();
            // *** IMPORTANT DEBUGGING CHANGE ***
            $error = 'Database Error: ' . $e->getMessage();
            // In production, you would log the error and use the generic message:
            // $error = 'Database error. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white text-center">
                <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Create Account</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <div class="text-center">
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username"
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                           pattern="[a-zA-Z0-9_]{3,}"
                                           title="Username must be at least 3 characters and contain only letters, numbers, and underscores"
                                           minlength="3" required>
                                    <small class="text-muted">At least 3 characters (letters, numbers, underscore)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                    <small class="text-muted">Enter a valid email address</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password"
                                           minlength="8" required>
                                    <small class="text-muted">At least 8 characters with letters and numbers</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                           minlength="8" required>
                                    <small class="text-muted">Must match password</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                           pattern="[0-9]{10}"
                                           title="Phone number must be exactly 10 digits"
                                           maxlength="10">
                                    <small class="text-muted">10 digit mobile number</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role *</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="patient" <?php echo (isset($_POST['role']) && $_POST['role'] === 'patient') ? 'selected' : ''; ?>>Patient</option>
                                        <option value="doctor" <?php echo (isset($_POST['role']) && $_POST['role'] === 'doctor') ? 'selected' : ''; ?>>Doctor</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Doctor-specific fields -->
                        <div id="doctor-fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="specialization_id" class="form-label">Specialization *</label>
                                        <select class="form-select" id="specialization_id" name="specialization_id" required>
                                            <option value="">Select Specialization</option>
                                            <?php foreach ($specializations as $spec): ?>
                                                <option value="<?php echo $spec['id']; ?>"
                                                        <?php echo (isset($_POST['specialization_id']) && $_POST['specialization_id'] == $spec['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($spec['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="license_number" class="form-label">License Number</label>
                                        <input type="text" class="form-control" id="license_number" name="license_number" value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Patient-specific fields -->
                        <div id="patient-fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Create Account</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-3">
                        <p>Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('role').addEventListener('change', function() {
    const doctorFields = document.getElementById('doctor-fields');
    const patientFields = document.getElementById('patient-fields');
    const specializationSelect = document.getElementById('specialization_id');
    const licenseInput = document.getElementById('license_number');
    const role = this.value;

    if (role === 'doctor') {
        doctorFields.style.display = 'block';
        patientFields.style.display = 'none';
        specializationSelect.required = true;
        licenseInput.required = false; // Optional for now
    } else if (role === 'patient') {
        doctorFields.style.display = 'none';
        patientFields.style.display = 'block';
        specializationSelect.required = false;
        licenseInput.required = false;
    } else {
        doctorFields.style.display = 'none';
        patientFields.style.display = 'none';
        specializationSelect.required = false;
        licenseInput.required = false;
    }
});
// Trigger change on page load in case of form resubmission with errors
document.getElementById('role').dispatchEvent(new Event('change'));

// Real-time password validation
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm_password');

function validatePassword() {
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;

    // Check password strength
    if (password.length > 0) {
        const hasLetter = /[A-Za-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasMinLength = password.length >= 8;

        if (!hasMinLength || !hasLetter || !hasNumber) {
            passwordInput.classList.add('is-invalid');
            passwordInput.classList.remove('is-valid');
        } else {
            passwordInput.classList.remove('is-invalid');
            passwordInput.classList.add('is-valid');
        }
    }

    // Check password match
    if (confirmPassword.length > 0) {
        if (password === confirmPassword && password.length >= 8) {
            confirmPasswordInput.classList.remove('is-invalid');
            confirmPasswordInput.classList.add('is-valid');
        } else {
            confirmPasswordInput.classList.add('is-invalid');
            confirmPasswordInput.classList.remove('is-valid');
        }
    }
}

passwordInput.addEventListener('input', validatePassword);
confirmPasswordInput.addEventListener('input', validatePassword);

// Phone number validation - only allow numbers
const phoneInput = document.getElementById('phone');
phoneInput.addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
    if (this.value.length > 10) {
        this.value = this.value.slice(0, 10);
    }

    if (this.value.length > 0) {
        if (this.value.length === 10) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        }
    } else {
        this.classList.remove('is-invalid', 'is-valid');
    }
});

// Username validation
const usernameInput = document.getElementById('username');
usernameInput.addEventListener('input', function() {
    const pattern = /^[a-zA-Z0-9_]{3,}$/;
    if (this.value.length > 0) {
        if (pattern.test(this.value)) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        }
    }
});

// Form submission validation
document.querySelector('form').addEventListener('submit', function(e) {
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;

    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        confirmPasswordInput.focus();
        return false;
    }

    if (password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long!');
        passwordInput.focus();
        return false;
    }

    if (!/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
        e.preventDefault();
        alert('Password must contain at least one letter and one number!');
        passwordInput.focus();
        return false;
    }

    const phone = phoneInput.value;
    if (phone.length > 0 && phone.length !== 10) {
        e.preventDefault();
        alert('Phone number must be exactly 10 digits!');
        phoneInput.focus();
        return false;
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
