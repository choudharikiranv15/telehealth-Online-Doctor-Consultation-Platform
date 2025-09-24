<?php
$page_title = "My Appointments";
require_once dirname(__FILE__) . '/../config.php';
// Note: paths.php is included via config.php now, so this line is not needed here.
require_once dirname(__FILE__) . '/../includes/db_connect.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

$patient_id = $_SESSION['user_id'];
$error = '';
$appointments = [];

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
        AND a.status IN ('pending', 'confirmed', 'active', 'completed', 'cancelled', 'rejected')
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    // --- END: CORRECTED QUERY ---
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-alt me-2"></i>My Appointments</h2>
        <a href="<?php echo getPageUrl('patient/book_appointment.php'); ?>" class="btn btn-primary">Book New Appointment</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (empty($appointments)): ?>
        <div class="alert alert-info">No appointments found.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['specialization'] ?? 'General Medicine'); ?></td>
                            <td>
                                <?php 
                                    $date = $appointment['appointment_date'];
                                    if ($date && $date !== '0000-00-00') {
                                        echo date('M d, Y', strtotime($date));
                                    } else {
                                        echo '<span class="text-muted">Invalid Date</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $time = $appointment['appointment_time'];
                                    if ($time && $time !== '00:00:00') {
                                        echo date('h:i A', strtotime($time));
                                    } else {
                                        echo '<span class="text-muted">Invalid Time</span>';
                                    }
                                ?>
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
                                <?php if ($appointment['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Awaiting Doctor Approval</span>
                                <?php elseif ($appointment['status'] === 'confirmed' || $appointment['status'] === 'active'): ?>
                                    <a href="<?php echo getPageUrl('video_call_webrtc.php'); ?>?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-video me-1"></i>Start Call
                                    </a>
                                <?php elseif ($appointment['status'] === 'completed'): ?>
                                    <a href="my_prescriptions.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-info btn-sm">View Details</a>
                                <?php elseif ($appointment['status'] === 'rejected'): ?>
                                    <div>
                                        <span class="badge bg-danger">Rejected by Doctor</span>
                                        <?php if (!empty($appointment['rejection_reason'])): ?>
                                            <br><small class="text-muted mt-1">
                                                <i class="fas fa-info-circle"></i>
                                                <strong>Reason:</strong> <?php echo htmlspecialchars($appointment['rejection_reason']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($appointment['status'] === 'cancelled'): ?>
                                    <div>
                                        <span class="badge bg-secondary">Cancelled</span>
                                        <?php if (!empty($appointment['cancellation_reason'])): ?>
                                            <br><small class="text-muted mt-1">
                                                <i class="fas fa-info-circle"></i>
                                                <?php echo htmlspecialchars($appointment['cancellation_reason']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo ucfirst($appointment['status']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
