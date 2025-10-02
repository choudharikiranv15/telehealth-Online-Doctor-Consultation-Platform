<?php
$page_title = "My Profile";
require_once '../config.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

require_once '../includes/db_connect.php';

$patient_id = $_SESSION['user_id'];
$error = '';
$message = '';

// Get patient profile
try {
    $stmt = $db->prepare("
        SELECT u.*, pp.*
        FROM users u
        LEFT JOIN patient_profiles pp ON u.id = pp.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$patient_id]);
    $profile = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $address = trim($_POST['address']);
    $emergency_contact = trim($_POST['emergency_contact']);
    $emergency_phone = trim($_POST['emergency_phone']);
    $medical_history = trim($_POST['medical_history']);
    $allergies = trim($_POST['allergies']);

    // Validation
    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $first_name) || !preg_match('/^[a-zA-Z\s]+$/', $last_name)) {
        $error = 'First name and last name can only contain letters and spaces.';
    } elseif (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Phone number must be exactly 10 digits.';
    } elseif (!empty($emergency_phone) && !preg_match('/^[0-9]{10}$/', $emergency_phone)) {
        $error = 'Emergency phone number must be exactly 10 digits.';
    } elseif (!empty($date_of_birth)) {
        $dob = new DateTime($date_of_birth);
        $today = new DateTime();
        $age = $today->diff($dob)->y;
        if ($age < 0 || $age > 150) {
            $error = 'Please enter a valid date of birth.';
        }
    } else {
        try {
            $db->beginTransaction();

            // Update user table
            $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $phone, $patient_id]);

            // Update or insert patient profile
            if ($profile && isset($profile['user_id']) && $profile['user_id']) {
                $stmt = $db->prepare("
                    UPDATE patient_profiles
                    SET date_of_birth = ?, gender = ?, address = ?, emergency_contact = ?,
                        emergency_phone = ?, medical_history = ?, allergies = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$date_of_birth, $gender, $address, $emergency_contact,
                               $emergency_phone, $medical_history, $allergies, $patient_id]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO patient_profiles (user_id, date_of_birth, gender, address, emergency_contact,
                                                 emergency_phone, medical_history, allergies)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$patient_id, $date_of_birth, $gender, $address, $emergency_contact,
                               $emergency_phone, $medical_history, $allergies]);
            }

            $db->commit();
            $message = 'Profile updated successfully!';

            // Refresh profile data
            $stmt = $db->prepare("
                SELECT u.*, pp.*
                FROM users u
                LEFT JOIN patient_profiles pp ON u.id = pp.user_id
                WHERE u.id = ?
            ");
            $stmt->execute([$patient_id]);
            $profile = $stmt->fetch();

        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user-edit me-2"></i>My Profile</h2>
    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                       value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                       value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username"
                                       value="<?php echo htmlspecialchars($profile['username'] ?? ''); ?>" readonly>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email"
                                       value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" readonly>
                                <small class="text-muted">Email cannot be changed</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>"
                                       pattern="[0-9]{10}"
                                       title="Phone number must be exactly 10 digits"
                                       maxlength="10">
                                <small class="text-muted">10 digit mobile number</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                       value="<?php echo $profile['date_of_birth'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-control" id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo ($profile['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($profile['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($profile['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="emergency_contact" class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" id="emergency_contact" name="emergency_contact"
                                       value="<?php echo htmlspecialchars($profile['emergency_contact'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="emergency_phone" class="form-label">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" id="emergency_phone" name="emergency_phone"
                                       value="<?php echo htmlspecialchars($profile['emergency_phone'] ?? ''); ?>"
                                       pattern="[0-9]{10}"
                                       title="Phone number must be exactly 10 digits"
                                       maxlength="10">
                                <small class="text-muted">10 digit mobile number</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address"
                                       value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="medical_history" class="form-label">Medical History</label>
                        <textarea class="form-control" id="medical_history" name="medical_history" rows="3"><?php echo htmlspecialchars($profile['medical_history'] ?? ''); ?></textarea>
                        <small class="text-muted">List any significant medical conditions, surgeries, or treatments</small>
                    </div>

                    <div class="mb-3">
                        <label for="allergies" class="form-label">Allergies</label>
                        <textarea class="form-control" id="allergies" name="allergies" rows="2"><?php echo htmlspecialchars($profile['allergies'] ?? ''); ?></textarea>
                        <small class="text-muted">List any known allergies to medications, foods, or other substances</small>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Profile Information</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <i class="fas fa-user-injured fa-4x text-primary"></i>
                </div>

                <h6>Account Details</h6>
                <ul class="list-unstyled">
                    <li><strong>Role:</strong> Patient</li>
                    <li><strong>Member Since:</strong> <?php echo date('M Y', strtotime($profile['created_at'] ?? 'now')); ?></li>
                    <li><strong>Status:</strong>
                        <span class="badge bg-success">Active</span>
                    </li>
                </ul>

                <hr>

                <h6>Quick Actions</h6>
                <div class="d-grid gap-2">
                    <a href="my_appointments.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-calendar me-2"></i>My Appointments
                    </a>
                    <a href="my_prescriptions.php" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-pills me-2"></i>My Prescriptions
                    </a>
                    <a href="book_appointment.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-2"></i>Book New Appointment
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Phone number validation - only allow numbers
const phoneInput = document.getElementById('phone');
const emergencyPhoneInput = document.getElementById('emergency_phone');

function validatePhoneInput(input) {
    input.addEventListener('input', function(e) {
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
}

validatePhoneInput(phoneInput);
validatePhoneInput(emergencyPhoneInput);

// Date of birth validation
const dobInput = document.getElementById('date_of_birth');
dobInput.addEventListener('change', function() {
    if (this.value) {
        const dob = new Date(this.value);
        const today = new Date();
        const age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }

        if (age < 0 || age > 150) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
            alert('Please enter a valid date of birth');
        } else {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        }
    }
});

// Form submission validation
document.querySelector('form').addEventListener('submit', function(e) {
    const phone = phoneInput.value;
    if (phone.length > 0 && phone.length !== 10) {
        e.preventDefault();
        alert('Phone number must be exactly 10 digits!');
        phoneInput.focus();
        return false;
    }

    const emergencyPhone = emergencyPhoneInput.value;
    if (emergencyPhone.length > 0 && emergencyPhone.length !== 10) {
        e.preventDefault();
        alert('Emergency phone number must be exactly 10 digits!');
        emergencyPhoneInput.focus();
        return false;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>