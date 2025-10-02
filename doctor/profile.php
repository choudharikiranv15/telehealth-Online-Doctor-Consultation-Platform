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

    // Get doctor's reviews
    $stmt = $db->prepare("
        SELECT r.*, u.first_name, u.last_name, a.appointment_date
        FROM reviews r
        LEFT JOIN users u ON r.patient_id = u.id
        LEFT JOIN appointments a ON r.appointment_id = a.id
        WHERE r.doctor_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$doctor_id]);
    $reviews = $stmt->fetchAll();
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
    $languages = trim($_POST['languages']);

    // Validation
    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $first_name) || !preg_match('/^[a-zA-Z\s]+$/', $last_name)) {
        $error = 'First name and last name can only contain letters and spaces.';
    } elseif (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Phone number must be exactly 10 digits.';
    } elseif (empty($specialization_id)) {
        $error = 'Specialization is required.';
    } elseif (!empty($experience_years) && ($experience_years < 0 || $experience_years > 60)) {
        $error = 'Experience years must be between 0 and 60.';
    } elseif (!empty($consultation_fee) && ($consultation_fee < 0 || $consultation_fee > 100000)) {
        $error = 'Consultation fee must be between 0 and 100000.';
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
                    SET specialization_id = ?, experience_years = ?, qualification = ?, bio = ?, consultation_fee = ?, languages = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$specialization_id, $experience_years, $qualification, $bio, $consultation_fee, $languages, $doctor_id]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO doctor_profiles (user_id, specialization_id, experience_years, qualification, bio, consultation_fee, languages)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$doctor_id, $specialization_id, $experience_years, $qualification, $bio, $consultation_fee, $languages]);
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
                                       value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>"
                                       pattern="[0-9]{10}"
                                       title="Phone number must be exactly 10 digits"
                                       maxlength="10">
                                <small class="text-muted">10 digit mobile number</small>
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
                                       value="<?php echo $profile['experience_years'] ?? ''; ?>" min="0" max="60">
                                <small class="text-muted">0-60 years</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="consultation_fee" class="form-label">Consultation Fee (₹)</label>
                                <input type="number" class="form-control" id="consultation_fee" name="consultation_fee"
                                       value="<?php echo $profile['consultation_fee'] ?? ''; ?>" min="0" max="100000" step="1">
                                <small class="text-muted">₹0 - ₹100,000</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="qualification" class="form-label">Qualification</label>
                        <textarea class="form-control" id="qualification" name="qualification" rows="3"><?php echo htmlspecialchars($profile['qualification'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="languages" class="form-label">Languages</label>
                        <input type="text" class="form-control" id="languages" name="languages"
                               value="<?php echo htmlspecialchars($profile['languages'] ?? ''); ?>"
                               placeholder="e.g., English, Hindi, Spanish">
                        <small class="text-muted">Enter languages separated by commas</small>
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
                    <?php if (!empty($profile['qualification'])): ?>
                    <li><strong>Qualification:</strong> <?php echo nl2br(htmlspecialchars($profile['qualification'])); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($profile['languages'])): ?>
                    <li><strong>Languages:</strong> <?php echo htmlspecialchars($profile['languages']); ?></li>
                    <?php endif; ?>
                </ul>

                <hr>

                <h6>Patient Ratings</h6>
                <div class="mb-3">
                    <?php if ($profile['total_reviews'] > 0): ?>
                        <div class="text-center">
                            <div class="h2 text-warning mb-0">
                                <i class="fas fa-star"></i> <?php echo number_format($profile['rating'], 1); ?>
                            </div>
                            <small class="text-muted"><?php echo $profile['total_reviews']; ?> review<?php echo $profile['total_reviews'] != 1 ? 's' : ''; ?></small>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="far fa-star"></i>
                            <p class="mb-0 small">No reviews yet</p>
                        </div>
                    <?php endif; ?>
                </div>

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

        <!-- Reviews Card -->
        <?php if (!empty($reviews)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Recent Patient Reviews</h5>
            </div>
            <div class="card-body">
                <?php foreach ($reviews as $review): ?>
                    <div class="review-item mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong>
                                    <?php
                                    if ($review['is_anonymous']) {
                                        echo 'Anonymous Patient';
                                    } else {
                                        echo htmlspecialchars($review['first_name'] . ' ' . substr($review['last_name'], 0, 1) . '.');
                                    }
                                    ?>
                                </strong>
                                <div class="text-warning">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <small class="text-muted">
                                <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                            </small>
                        </div>
                        <?php if (!empty($review['review_text'])): ?>
                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($review['review_text']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($profile['total_reviews'] > 10): ?>
                    <div class="text-center">
                        <small class="text-muted">Showing 10 most recent reviews</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
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

// Experience years validation
const experienceInput = document.getElementById('experience_years');
experienceInput.addEventListener('input', function() {
    const value = parseInt(this.value);
    if (this.value.length > 0) {
        if (value >= 0 && value <= 60) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        } else {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        }
    }
});

// Consultation fee validation
const feeInput = document.getElementById('consultation_fee');
feeInput.addEventListener('input', function() {
    const value = parseInt(this.value);
    if (this.value.length > 0) {
        if (value >= 0 && value <= 100000) {
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
    const phone = phoneInput.value;
    if (phone.length > 0 && phone.length !== 10) {
        e.preventDefault();
        alert('Phone number must be exactly 10 digits!');
        phoneInput.focus();
        return false;
    }

    const experience = parseInt(experienceInput.value);
    if (experienceInput.value.length > 0 && (experience < 0 || experience > 60)) {
        e.preventDefault();
        alert('Experience years must be between 0 and 60!');
        experienceInput.focus();
        return false;
    }

    const fee = parseInt(feeInput.value);
    if (feeInput.value.length > 0 && (fee < 0 || fee > 100000)) {
        e.preventDefault();
        alert('Consultation fee must be between ₹0 and ₹100,000!');
        feeInput.focus();
        return false;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
