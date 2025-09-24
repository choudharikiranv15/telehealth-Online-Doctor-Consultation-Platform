<?php
$page_title = "View Doctor";
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once '../includes/db_connect.php';

$error = '';
$doctor = null;
$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$doctor_id) {
    header("Location: manage_doctors.php");
    exit();
}

// Get doctor details
try {
    $stmt = $db->prepare("
        SELECT u.*, dp.* FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE u.id = ? AND u.role = 'doctor'
    ");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        $error = "No doctor found with this ID.";
    }

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user-md me-2"></i>Doctor Details</h2>
    <a href="manage_doctors.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Manage Doctors
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php elseif ($doctor): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                <span class="badge bg-light text-dark ms-2"><?php echo ucfirst($doctor['status']); ?></span>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center">
                        <?php if (!empty($doctor['profile_picture'])): ?>
                            <img src="<?php echo getAssetUrl('images/' . htmlspecialchars($doctor['profile_picture'])); ?>" 
                                 alt="Profile Picture" class="img-thumbnail rounded-circle mb-3" style="width: 150px; height: 150px;">
                        <?php else: ?>
                            <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center mb-3" 
                                 style="width: 150px; height: 150px; background-color: #e9ecef;">
                                <i class="fas fa-user-md fa-4x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-8">
                    <h4>Contact Information</h4>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Username:</strong> <?php echo htmlspecialchars($doctor['username']); ?></li>
                        <li class="list-group-item"><strong>Email:</strong> <?php echo htmlspecialchars($doctor['email']); ?></li>
                        <li class="list-group-item"><strong>Phone:</strong> <?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?></li>
                        <li class="list-group-item"><strong>Location:</strong> 
                            <?php 
                                $location = array_filter([$doctor['city'], $doctor['state'], $doctor['country']]);
                                echo !empty($location) ? htmlspecialchars(implode(', ', $location)) : 'N/A';
                            ?>
                        </li>
                    </ul>
                </div>
            </div>
            <hr>
            <h4>Professional Profile</h4>
            <div class="row">
                <div class="col-md-6">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization'] ?? 'N/A'); ?></li>
                        <li class="list-group-item"><strong>License Number:</strong> <?php echo htmlspecialchars($doctor['license_number'] ?? 'N/A'); ?></li>
                        <li class="list-group-item"><strong>Experience:</strong> <?php echo $doctor['experience_years'] ? htmlspecialchars($doctor['experience_years']) . ' years' : 'N/A'; ?></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Rating:</strong> <?php echo $doctor['rating'] ? number_format($doctor['rating'], 1) . ' / 5.0' : 'N/A'; ?></li>
                        <li class="list-group-item"><strong>Consultation Fee:</strong> ₹<?php echo $doctor['consultation_fee'] ? number_format($doctor['consultation_fee'], 2) : '0.00'; ?></li>
                        <li class="list-group-item"><strong>Member Since:</strong> <?php echo date('M d, Y', strtotime($doctor['created_at'])); ?></li>
                    </ul>
                </div>
            </div>
             <div class="mt-4">
                <h5>Biography</h5>
                <p class="text-muted"><?php echo !empty($doctor['bio']) ? nl2br(htmlspecialchars($doctor['bio'])) : 'No biography provided.'; ?></p>
            </div>
        </div>
        <div class="card-footer text-end">
            <!-- Action buttons can be added here, e.g., to edit the profile -->
            <a href="edit_doctor.php?id=<?php echo $doctor['id']; ?>" class="btn btn-primary">Edit Profile</a>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>