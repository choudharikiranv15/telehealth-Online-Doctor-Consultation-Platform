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
    } elseif ($role === 'doctor' && empty($specialization_id)) {
        $error = 'Please select a specialization for doctor registration.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
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
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
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
</script>

<?php require_once 'includes/footer.php'; ?>
