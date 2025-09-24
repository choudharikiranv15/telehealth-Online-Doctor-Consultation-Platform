<?php
$page_title = "My Profile";
require_once '../config.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

require_once '../includes/db_connect.php';

$doctor_id = $_SESSION['user_id'];
$error = '';
$message = '';

// Get doctor profile
try {
    $stmt = $db->prepare("
        SELECT u.*, dp.*, s.name as specialization_name
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE u.id = ?
    ");
    $stmt->execute([$doctor_id]);
    $profile = $stmt->fetch();

    // Get all specializations for dropdown
    $stmt = $db->query("SELECT id, name FROM specializations ORDER BY name");
    $specializations = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $specialization_id = isset($_POST['specialization_id']) ? (int)$_POST['specialization_id'] : null;
    $experience_years = $_POST['experience_years'];
    $qualification = trim($_POST['qualification']);
    $bio = trim($_POST['bio']);
    $consultation_fee = $_POST['consultation_fee'];
    
    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } else {
        try {
            $db->beginTransaction();

            // Handle profile picture upload
            $profile_picture_path = $profile['profile_picture']; // Keep existing if no new upload
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../assets/images/profiles/';

                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Validate file
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_info = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($file_info, $_FILES['profile_picture']['tmp_name']);
                finfo_close($file_info);

                if (!in_array($mime_type, $allowed_types)) {
                    throw new Exception('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
                }

                if ($_FILES['profile_picture']['size'] > 2 * 1024 * 1024) { // 2MB limit
                    throw new Exception('File size too large. Maximum size is 2MB.');
                }

                // Generate unique filename
                $extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $filename = 'doctor_' . $doctor_id . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;

                // Move uploaded file
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filepath)) {
                    // Delete old profile picture if exists
                    if (!empty($profile['profile_picture']) && file_exists('../' . $profile['profile_picture'])) {
                        unlink('../' . $profile['profile_picture']);
                    }
                    $profile_picture_path = 'assets/images/profiles/' . $filename;
                } else {
                    throw new Exception('Failed to upload profile picture.');
                }
            }

            // Update user table
            $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, profile_picture = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $phone, $profile_picture_path, $doctor_id]);
            
            // Update or insert doctor profile
            if ($profile['specialization_id']) {
                $stmt = $db->prepare("
                    UPDATE doctor_profiles
                    SET specialization_id = ?, experience_years = ?, qualification = ?, bio = ?, consultation_fee = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$specialization_id, $experience_years, $qualification, $bio, $consultation_fee, $doctor_id]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO doctor_profiles (user_id, specialization_id, experience_years, qualification, bio, consultation_fee)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$doctor_id, $specialization_id, $experience_years, $qualification, $bio, $consultation_fee]);
            }
            
            $db->commit();
            $message = 'Profile updated successfully!';
            
            // Refresh profile data
            $stmt = $db->prepare("
                SELECT u.*, dp.*, s.name as specialization_name
                FROM users u
                LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
                LEFT JOIN specializations s ON dp.specialization_id = s.id
                WHERE u.id = ?
            ");
            $stmt->execute([$doctor_id]);
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
                <form method="POST" action="" enctype="multipart/form-data">
                    <!-- Profile Photo Section -->
                    <div class="mb-4">
                        <label class="form-label">Profile Photo</label>
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <?php if (!empty($profile['profile_picture'])): ?>
                                    <img src="<?php echo getPageUrl($profile['profile_picture']); ?>"
                                         alt="Profile Picture"
                                         class="rounded-circle"
                                         style="width: 80px; height: 80px; object-fit: cover;"
                                         id="profile-preview">
                                <?php else: ?>
                                    <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center"
                                         style="width: 80px; height: 80px;" id="profile-preview">
                                        <i class="fas fa-user-md text-white fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <input type="file" class="form-control" id="profile_picture" name="profile_picture"
                                       accept="image/*" onchange="previewImage(this)">
                                <small class="text-muted">Upload JPG, PNG, or GIF. Max size: 2MB</small>
                            </div>
                        </div>
                    </div>

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
                                       value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="specialization_id" class="form-label">Specialization *</label>
                                <select class="form-select" id="specialization_id" name="specialization_id" required>
                                    <option value="">Select Specialization</option>
                                    <?php foreach ($specializations as $spec): ?>
                                        <option value="<?php echo $spec['id']; ?>"
                                                <?php echo ($profile['specialization_id'] == $spec['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($spec['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="experience_years" class="form-label">Years of Experience</label>
                                <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                       value="<?php echo $profile['experience_years'] ?? ''; ?>" min="0" max="50">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="consultation_fee" class="form-label">Consultation Fee (₹)</label>
                                <input type="number" class="form-control" id="consultation_fee" name="consultation_fee" 
                                       value="<?php echo $profile['consultation_fee'] ?? ''; ?>" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="qualification" class="form-label">Qualification</label>
                        <textarea class="form-control" id="qualification" name="qualification" rows="3"><?php echo htmlspecialchars($profile['qualification'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                        <small class="text-muted">Tell patients about your expertise and approach</small>
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
                    <i class="fas fa-user-md fa-4x text-primary"></i>
                </div>
                
                <h6>Account Details</h6>
                <ul class="list-unstyled">
                    <li><strong>Role:</strong> Doctor</li>
                    <li><strong>Member Since:</strong> <?php echo date('M Y', strtotime($profile['created_at'] ?? 'now')); ?></li>
                    <li><strong>Status:</strong>
                        <span class="badge bg-<?php echo $profile['status'] === 'active' ? 'success' : ($profile['status'] === 'suspended' ? 'danger' : 'secondary'); ?>">
                            <?php echo ucfirst($profile['status']); ?>
                        </span>
                    </li>
                </ul>
                
                <hr>
                
                <h6>Professional Info</h6>
                <ul class="list-unstyled">
                    <li><strong>Specialization:</strong> <?php echo htmlspecialchars($profile['specialization_name'] ?? 'Not specified'); ?></li>
                    <li><strong>Experience:</strong> <?php echo $profile['experience_years'] ? $profile['experience_years'] . ' years' : 'Not specified'; ?></li>
                    <li><strong>License:</strong> <?php echo htmlspecialchars($profile['license_number'] ?? 'Not specified'); ?></li>
                </ul>

                <hr>

                <h6>Quick Actions</h6>
                <div class="d-grid gap-2">
                    <a href="availability.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-calendar-alt me-2"></i>Manage Availability
                    </a>
                    <a href="my_appointments.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-calendar-check me-2"></i>My Appointments
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        const preview = document.getElementById('profile-preview');

        reader.onload = function(e) {
            // Replace the existing content with the new image
            preview.innerHTML = '';
            preview.style.backgroundImage = 'none';
            preview.className = 'rounded-circle';
            preview.style.width = '80px';
            preview.style.height = '80px';
            preview.style.objectFit = 'cover';
            preview.style.backgroundImage = 'url(' + e.target.result + ')';
            preview.style.backgroundSize = 'cover';
            preview.style.backgroundPosition = 'center';
        };

        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
