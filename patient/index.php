<?php
$page_title = "Patient Dashboard";
require_once dirname(__FILE__) . '/../config.php';
// Note: paths.php is included via config.php now, so this line is not needed here.

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once dirname(__FILE__) . '/../includes/db_connect.php';

$patient_id = $_SESSION['user_id'];
$error = '';
$appointments = [];
$upcoming_appointments = 0;
$completed_appointments = 0;

// Get patient's appointments
try {
    // --- START: CORRECTED QUERY WITH SPECIALIZATIONS ---
    // Updated to use the new database structure with specializations table
    $stmt = $db->prepare("
        SELECT a.*, u.first_name, u.last_name, s.name as specialization
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE a.patient_id = ? 
        AND a.status IN ('pending', 'confirmed', 'active', 'completed', 'cancelled')
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5
    ");
    // --- END: CORRECTED QUERY ---
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll();
    
    // Get upcoming appointments count - INCLUDING ACTIVE APPOINTMENTS
    $stmt = $db->prepare("
        SELECT COUNT(*) as upcoming_count
        FROM appointments
        WHERE patient_id = ? AND appointment_date >= CURDATE() AND status IN ('pending', 'confirmed', 'active')
    ");
    $stmt->execute([$patient_id]);
    $upcoming_appointments = $stmt->fetch()['upcoming_count'];
    
    // Get completed appointments count (this query was already correct)
    $stmt = $db->prepare("
        SELECT COUNT(*) as completed_count
        FROM appointments
        WHERE patient_id = ? AND status = 'completed'
    ");
    $stmt->execute([$patient_id]);
    $completed_appointments = $stmt->fetch()['completed_count'];

} catch (PDOException $e) {
    $error = 'Database error. Please try again.';
}

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user me-2"></i>Patient Dashboard</h2>
    <div>
        <a href="<?php echo getPageUrl('patient/book_appointment.php'); ?>" class="btn btn-primary me-2">Book Appointment</a>
        <a href="<?php echo getPageUrl('patient/my_prescriptions.php'); ?>" class="btn btn-success">My Prescriptions</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title"><?php echo $upcoming_appointments; ?></h4>
                        <p class="card-text">Upcoming Appointments</p>
                    </div>
                    <div>
                        <i class="fas fa-calendar-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card bg-success text-white dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title"><?php echo $completed_appointments; ?></h4>
                        <p class="card-text">Completed Consultations</p>
                    </div>
                    <div>
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card bg-info text-white dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title"><?php echo count($appointments); ?></h4>
                        <p class="card-text">Recent Appointments</p>
                    </div>
                    <div>
                        <i class="fas fa-history fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Appointments</h5>
            </div>
            <div class="card-body">
                <?php if (empty($appointments)): ?>
                    <p class="text-muted">No appointments found. <a href="<?php echo getPageUrl('patient/book_appointment.php'); ?>">Book your first appointment</a></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Doctor</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($appointment['specialization'] ?? 'General Medicine'); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></strong>
                                            <br><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $appointment['status'] === 'completed' ? 'success' : 
                                                    ($appointment['status'] === 'cancelled' ? 'danger' : 
                                                    ($appointment['status'] === 'confirmed' ? 'primary' : 'warning')); 
                                            ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($appointment['status'] === 'confirmed' || $appointment['status'] === 'active'): ?>
                                                <a href="<?php echo getPageUrl('video_call_jitsi.php'); ?>?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-video me-1"></i>Start Video Call
                                                </a>
                                            <?php elseif ($appointment['status'] === 'completed'): ?>
                                                <a href="my_prescriptions.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-info btn-sm">View Details</a>
                                            <?php elseif ($appointment['status'] === 'pending'): ?>
                                                <span class="text-muted">Waiting for confirmation</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="my_appointments.php" class="btn btn-outline-primary">View All Appointments</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="<?php echo getPageUrl('patient/book_appointment.php'); ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-plus me-2"></i>Book New Appointment
                    </a>
                    <a href="my_appointments.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-alt me-2"></i>View All Appointments
                    </a>
                    <a href="my_prescriptions.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-pills me-2"></i>My Prescriptions
                    </a>
                    <a href="<?php echo getPageUrl('profile.php'); ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-user-edit me-2"></i>Edit Profile
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Health Tips</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Stay Hydrated!</strong> Drink at least 8 glasses of water daily for better health.
                </div>
                <div class="alert alert-success">
                    <i class="fas fa-heart me-2"></i>
                    <strong>Exercise Regularly!</strong> 30 minutes of daily exercise can improve your overall health.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
