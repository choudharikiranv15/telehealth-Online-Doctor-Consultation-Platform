<?php
$page_title = "My Profile";
require_once 'config.php';
require_once 'includes/db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Basic Info ---
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $country = trim($_POST['country']);

    // --- Role-Specific Info ---
    // Doctor
    $specialization_id = isset($_POST['specialization_id']) ? (int)$_POST['specialization_id'] : null;
    $experience_years = isset($_POST['experience_years']) ? (int)$_POST['experience_years'] : null;
    $consultation_fee = isset($_POST['consultation_fee']) ? (float)$_POST['consultation_fee'] : null;
    // Patient
    $date_of_birth = isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $gender = isset($_POST['gender']) ? $_POST['gender'] : null;

    if (empty($first_name) || empty($last_name)) {
        $error = "First and last name are required.";
    } else {
        $db->beginTransaction();
        try {
            // Update the `users` table
            $stmt = $db->prepare(
                "UPDATE users SET first_name = ?, last_name = ?, phone = ?, city = ?, state = ?, country = ? WHERE id = ?"
            );
            $stmt->execute([$first_name, $last_name, $phone, $city, $state, $country, $user_id]);

            // Update role-specific profile table
            if ($_SESSION['role'] === 'doctor') {
                // Handle empty specialization_id - convert to NULL if empty
                $final_specialization_id = (empty($specialization_id) || $specialization_id === '') ? null : $specialization_id;
                
                // Debug logging
                error_log("Profile Update Debug - Original specialization_id: " . var_export($specialization_id, true) . 
                         ", Final specialization_id: " . var_export($final_specialization_id, true));
                
                $stmt = $db->prepare(
                    "UPDATE doctor_profiles SET specialization_id = ?, experience_years = ?, consultation_fee = ? WHERE user_id = ?"
                );
                $stmt->execute([$final_specialization_id, $experience_years, $consultation_fee, $user_id]);
            } elseif ($_SESSION['role'] === 'patient') {
                $stmt = $db->prepare(
                    "UPDATE patient_profiles SET date_of_birth = ?, gender = ? WHERE user_id = ?"
                );
                $stmt->execute([$date_of_birth, $gender, $user_id]);
            }

            $db->commit();
            $success = "Profile updated successfully!";
            
            // Update session variables
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;

        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Database error: Could not update profile. " . $e->getMessage();
        }
    }
}


// Fetch current user data for display
try {
    $sql = "SELECT u.*, dp.*, pp.*, s.name as specialization, s.id as specialization_id
            FROM users u
            LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
            LEFT JOIN patient_profiles pp ON u.id = pp.user_id
            LEFT JOIN specializations s ON dp.specialization_id = s.id
            WHERE u.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // This case should not happen if user is logged in, but it's good practice
        die("Error: Could not find user data.");
    }
} catch (PDOException $e) {
    die("Database error: Could not fetch user data. " . $e->getMessage());
}


require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit Your Profile</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <h5 class="mb-3">Basic Information</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <small class="text-muted">Email address cannot be changed.</small>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>

                    <h5 class="mt-4 mb-3">Location</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="state" class="form-label">State</label>
                            <input type="text" class="form-control" id="state" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="country" class="form-label">Country</label>
                            <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <hr class="my-4">

                    <!-- Patient-specific fields -->
                    <?php if ($user['role'] === 'patient'): ?>
                        <h5 class="mb-3">Patient Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Select...</option>
                                    <option value="male" <?php echo ($user['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($user['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($user['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Doctor-specific fields -->
                    <?php if ($user['role'] === 'doctor'): ?>
                        <h5 class="mb-3">Doctor Information</h5>
                        <div class="mb-3">
                            <label for="specialization_id" class="form-label">Specialization</label>
                            <select class="form-select" id="specialization_id" name="specialization_id">
                                <option value="">Select Specialization...</option>
                                <?php
                                try {
                                    $spec_stmt = $db->query("SELECT id, name FROM specializations ORDER BY name");
                                    $specializations = $spec_stmt->fetchAll();
                                    foreach ($specializations as $spec): 
                                ?>
                                    <option value="<?php echo $spec['id']; ?>" <?php echo ($user['specialization_id'] == $spec['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($spec['name']); ?>
                                    </option>
                                <?php 
                                    endforeach;
                                } catch (PDOException $e) {
                                    echo '<option value="">Error loading specializations</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="experience_years" class="form-label">Years of Experience</label>
                                <input type="number" class="form-control" id="experience_years" name="experience_years" value="<?php echo htmlspecialchars($user['experience_years'] ?? '0'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="consultation_fee" class="form-label">Consultation Fee (₹)</label>
                                <input type="number" step="0.01" class="form-control" id="consultation_fee" name="consultation_fee" value="<?php echo htmlspecialchars($user['consultation_fee'] ?? '0.00'); ?>">
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
