<?php
$page_title = "Edit Doctor";
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once '../includes/db_connect.php';

$message = '';
$error = '';
$doctor_id = $_GET['id'] ?? null;

if (!$doctor_id) {
    header("Location: manage_doctors.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Update users table
        $stmt = $db->prepare("UPDATE users SET
            first_name = ?, last_name = ?, email = ?, phone = ?,
            address = ?, city = ?, state = ?, country = ?, postal_code = ?
            WHERE id = ? AND role = 'doctor'");
        $stmt->execute([
            $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'],
            $_POST['address'], $_POST['city'], $_POST['state'], $_POST['country'], $_POST['postal_code'],
            $doctor_id
        ]);

        // Update doctor_profiles table
        $stmt = $db->prepare("UPDATE doctor_profiles SET
            specialization_id = ?, specialization = ?, license_number = ?, experience_years = ?,
            qualification = ?, bio = ?, consultation_fee = ?, languages = ?,
            availability_start = ?, availability_end = ?, available_days = ?
            WHERE user_id = ?");
        $stmt->execute([
            $_POST['specialization_id'], $_POST['specialization'], $_POST['license_number'], $_POST['experience_years'],
            $_POST['qualification'], $_POST['bio'], $_POST['consultation_fee'], $_POST['languages'],
            $_POST['availability_start'], $_POST['availability_end'], $_POST['available_days'],
            $doctor_id
        ]);

        $db->commit();
        $message = 'Doctor profile updated successfully.';
    } catch (PDOException $e) {
        $db->rollBack();
        $error = 'Error updating doctor: ' . $e->getMessage();
    }
}

// Get doctor details
try {
    $stmt = $db->prepare("
        SELECT u.*, dp.*, s.name as specialization_name
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE u.id = ? AND u.role = 'doctor'
    ");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        header("Location: manage_doctors.php");
        exit();
    }
} catch (PDOException $e) {
    $error = 'Error fetching doctor details: ' . $e->getMessage();
    $doctor = null;
}

// Get specializations
try {
    $stmt = $db->query("SELECT * FROM specializations ORDER BY name");
    $specializations = $stmt->fetchAll();
} catch (PDOException $e) {
    $specializations = [];
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-edit me-2"></i>Edit Doctor Profile</h2>
    <a href="manage_doctors.php" class="btn btn-secondary">Back to Manage Doctors</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($doctor): ?>
<form method="POST" class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Personal Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label for="first_name" class="form-label">First Name *</label>
                        <input type="text" class="form-control" id="first_name" name="first_name"
                               value="<?php echo htmlspecialchars($doctor['first_name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="last_name" class="form-label">Last Name *</label>
                        <input type="text" class="form-control" id="last_name" name="last_name"
                               value="<?php echo htmlspecialchars($doctor['last_name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone"
                               value="<?php echo htmlspecialchars($doctor['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($doctor['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label for="city" class="form-label">City</label>
                        <input type="text" class="form-control" id="city" name="city"
                               value="<?php echo htmlspecialchars($doctor['city'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="state" class="form-label">State</label>
                        <input type="text" class="form-control" id="state" name="state"
                               value="<?php echo htmlspecialchars($doctor['state'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="country" class="form-label">Country</label>
                        <input type="text" class="form-control" id="country" name="country"
                               value="<?php echo htmlspecialchars($doctor['country'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="postal_code" class="form-label">Postal Code</label>
                        <input type="text" class="form-control" id="postal_code" name="postal_code"
                               value="<?php echo htmlspecialchars($doctor['postal_code'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Professional Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label for="specialization_id" class="form-label">Specialization *</label>
                        <select class="form-select" id="specialization_id" name="specialization_id" required>
                            <option value="">Select Specialization</option>
                            <?php foreach ($specializations as $spec): ?>
                                <option value="<?php echo $spec['id']; ?>"
                                        <?php echo $doctor['specialization_id'] == $spec['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="specialization" class="form-label">Specialization Text</label>
                        <input type="text" class="form-control" id="specialization" name="specialization"
                               value="<?php echo htmlspecialchars($doctor['specialization'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="license_number" class="form-label">License Number *</label>
                        <input type="text" class="form-control" id="license_number" name="license_number"
                               value="<?php echo htmlspecialchars($doctor['license_number'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="experience_years" class="form-label">Experience (Years) *</label>
                        <input type="number" class="form-control" id="experience_years" name="experience_years"
                               value="<?php echo $doctor['experience_years'] ?? 0; ?>" min="0" required>
                    </div>
                    <div class="col-12">
                        <label for="qualification" class="form-label">Qualification</label>
                        <textarea class="form-control" id="qualification" name="qualification" rows="3"><?php echo htmlspecialchars($doctor['qualification'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo htmlspecialchars($doctor['bio'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="consultation_fee" class="form-label">Consultation Fee (₹) *</label>
                        <input type="number" class="form-control" id="consultation_fee" name="consultation_fee"
                               value="<?php echo $doctor['consultation_fee'] ?? 500; ?>" min="0" step="0.01" required>
                    </div>
                    <div class="col-md-6">
                        <label for="languages" class="form-label">Languages</label>
                        <input type="text" class="form-control" id="languages" name="languages"
                               value="<?php echo htmlspecialchars($doctor['languages'] ?? 'English, Hindi'); ?>"
                               placeholder="e.g., English, Hindi, Tamil">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Availability</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label for="availability_start" class="form-label">Start Time</label>
                        <input type="time" class="form-control" id="availability_start" name="availability_start"
                               value="<?php echo $doctor['availability_start'] ?? '09:00'; ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="availability_end" class="form-label">End Time</label>
                        <input type="time" class="form-control" id="availability_end" name="availability_end"
                               value="<?php echo $doctor['availability_end'] ?? '18:00'; ?>">
                    </div>
                    <div class="col-12">
                        <label for="available_days" class="form-label">Available Days</label>
                        <input type="text" class="form-control" id="available_days" name="available_days"
                               value="<?php echo htmlspecialchars($doctor['available_days'] ?? 'monday,tuesday,wednesday,thursday,friday,saturday'); ?>"
                               placeholder="e.g., monday,tuesday,wednesday,thursday,friday">
                        <div class="form-text">Enter days separated by commas (lowercase)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Update Doctor Profile
            </button>
            <a href="manage_doctors.php" class="btn btn-secondary">Cancel</a>
        </div>
    </div>
</form>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>