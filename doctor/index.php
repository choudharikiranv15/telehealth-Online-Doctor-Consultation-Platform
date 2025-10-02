<?php
$page_title = "Doctor Dashboard";
require_once dirname(__FILE__) . '/../config.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once dirname(__FILE__) . '/../includes/db_connect.php';

$doctor_id = $_SESSION['user_id'];
$error = '';

// Get doctor profile info
try {
    $stmt = $db->prepare("
        SELECT u.first_name, u.last_name, u.profile_picture, dp.qualification, dp.languages, dp.specialization_id, s.name as specialization_name
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE u.id = ?
    ");
    $stmt->execute([$doctor_id]);
    $doctor_profile = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching doctor profile: " . $e->getMessage());
}

// Get doctor's appointments
try {
    $stmt = $db->prepare("
        SELECT a.*, u.first_name, u.last_name, u.phone
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10
    ");
    $stmt->execute([$doctor_id]);
    $appointments = $stmt->fetchAll();
    
    // Get today's appointments
    $stmt = $db->prepare("
        SELECT COUNT(*) as today_count
        FROM appointments
        WHERE doctor_id = ? AND appointment_date = CURDATE()
    ");
    $stmt->execute([$doctor_id]);
    $today_appointments = $stmt->fetch()['today_count'];
    
    // Get pending appointments
    $stmt = $db->prepare("
        SELECT COUNT(*) as pending_count
        FROM appointments
        WHERE doctor_id = ? AND status = 'pending'
    ");
    $stmt->execute([$doctor_id]);
    $pending_appointments = $stmt->fetch()['pending_count'];

    // Get prescription count
    $stmt = $db->prepare("
        SELECT COUNT(*) as prescription_count
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        WHERE a.doctor_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $prescription_count = $stmt->fetch()['prescription_count'];

    // Get appointments needing prescriptions
    $stmt = $db->prepare("
        SELECT COUNT(*) as needs_prescription
        FROM appointments a
        LEFT JOIN prescriptions p ON a.id = p.appointment_id
        WHERE a.doctor_id = ? AND a.status = 'completed' AND p.id IS NULL
    ");
    $stmt->execute([$doctor_id]);
    $needs_prescription = $stmt->fetch()['needs_prescription'];
    
    // Debug logging
    error_log("Doctor ID: $doctor_id, Appointments found: " . count($appointments) . ", Today: $today_appointments, Pending: $pending_appointments");
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    error_log("Doctor dashboard error: " . $e->getMessage());
}

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user-md me-2"></i>Doctor Dashboard</h2>
    <div>
        <a href="<?php echo getPageUrl('doctor/availability.php'); ?>" class="btn btn-success me-2">
            <i class="fas fa-calendar-alt me-1"></i>Manage Availability
        </a>
        <a href="<?php echo getPageUrl('doctor/my_appointments.php'); ?>" class="btn btn-primary me-2">View All Appointments</a>
        <a href="<?php echo getPageUrl('doctor/profile.php'); ?>" class="btn btn-outline-primary">My Profile</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title"><?php echo $today_appointments; ?></h4>
                        <p class="card-text">Today's Appointments</p>
                    </div>
                    <div>
                        <i class="fas fa-calendar-day fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <a href="<?php echo getPageUrl('doctor/manage_appointments.php'); ?>" class="text-decoration-none">
            <div class="card bg-warning text-white dashboard-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title"><?php echo $pending_appointments; ?></h4>
                            <p class="card-text">Pending Approvals</p>
                            <?php if ($pending_appointments > 0): ?>
                                <small class="fw-bold">Click to review →</small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <i class="fas fa-user-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <div class="card bg-success text-white dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title"><?php echo $prescription_count; ?></h4>
                        <p class="card-text">Prescriptions Written</p>
                    </div>
                    <div>
                        <i class="fas fa-file-medical fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card bg-danger text-white dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title"><?php echo $needs_prescription; ?></h4>
                        <p class="card-text">Need Prescriptions</p>
                    </div>
                    <div>
                        <i class="fas fa-prescription-bottle-alt fa-2x"></i>
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
                    <p class="text-muted">No appointments found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($appointment['phone']); ?></small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo $appointment['status'] === 'completed' ? 'success' :
                                                    ($appointment['status'] === 'cancelled' ? 'secondary' :
                                                    ($appointment['status'] === 'rejected' ? 'danger' :
                                                    ($appointment['status'] === 'missed' ? 'warning' :
                                                    ($appointment['status'] === 'confirmed' ? 'primary' : 'warning'))));
                                            ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                                <?php if ($appointment['status'] === 'missed' && !empty($appointment['missed_by'])): ?>
                                                    <br><small>by <?php echo ucfirst($appointment['missed_by']); ?></small>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($appointment['status'] === 'pending'): ?>
                                                <div class="d-flex gap-1">
                                                    <a href="<?php echo getPageUrl('doctor/my_appointments.php?status=pending'); ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check me-1"></i>Review
                                                    </a>
                                                    <span class="badge bg-warning text-dark">Needs Approval</span>
                                                </div>
                                            <?php elseif ($appointment['status'] === 'confirmed'): ?>
                                                <a href="<?php echo getPageUrl('video_call_webrtc.php'); ?>?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-video me-1"></i>Start Video Call
                                                </a>
                                            <?php elseif ($appointment['status'] === 'active'): ?>
                                                <a href="<?php echo getPageUrl('video_call_webrtc.php'); ?>?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-video me-1"></i>Join Active Call
                                                </a>
                                            <?php elseif ($appointment['status'] === 'completed'): ?>
                                                <?php
                                                // Check if prescription exists
                                                $prescription_check = $db->prepare("SELECT id FROM prescriptions WHERE appointment_id = ?");
                                                $prescription_check->execute([$appointment['id']]);
                                                $prescription_exists = $prescription_check->fetch();
                                                ?>
                                                <?php if ($prescription_exists): ?>
                                                    <a href="<?php echo getPageUrl('prescription_pdf.php?id=' . $prescription_exists['id']); ?>" class="btn btn-info btn-sm" target="_blank">
                                                        <i class="fas fa-file-medical me-1"></i>View Prescription
                                                    </a>
                                                <?php else: ?>
                                                    <a href="write_prescription.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-prescription-bottle-alt me-1"></i>Write Prescription
                                                    </a>
                                                <?php endif; ?>
                                            <?php elseif ($appointment['status'] === 'missed'): ?>
                                                <span class="badge bg-secondary">No Action Available</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Profile Info Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Profile Information</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <?php if (!empty($doctor_profile['profile_picture'])): ?>
                        <img src="<?php echo getPageUrl($doctor_profile['profile_picture']); ?>"
                             alt="Profile Picture"
                             class="rounded-circle"
                             style="width: 80px; height: 80px; object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-user-md text-white fa-2x"></i>
                        </div>
                    <?php endif; ?>
                    <h6 class="mt-2 mb-0"><?php echo htmlspecialchars($doctor_profile['first_name'] . ' ' . $doctor_profile['last_name']); ?></h6>
                    <?php if (!empty($doctor_profile['specialization_name'])): ?>
                        <small class="text-muted"><?php echo htmlspecialchars($doctor_profile['specialization_name']); ?></small>
                    <?php endif; ?>
                </div>

                <?php if (!empty($doctor_profile['qualification'])): ?>
                <div class="mb-2">
                    <strong><i class="fas fa-graduation-cap me-2 text-primary"></i>Qualification:</strong>
                    <div class="ms-4 text-muted small"><?php echo nl2br(htmlspecialchars($doctor_profile['qualification'])); ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($doctor_profile['languages'])): ?>
                <div class="mb-2">
                    <strong><i class="fas fa-language me-2 text-primary"></i>Languages:</strong>
                    <div class="ms-4 text-muted small"><?php echo htmlspecialchars($doctor_profile['languages']); ?></div>
                </div>
                <?php endif; ?>

                <a href="<?php echo getPageUrl('doctor/profile.php'); ?>" class="btn btn-outline-primary btn-sm w-100 mt-2">
                    <i class="fas fa-edit me-1"></i>Edit Profile
                </a>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="<?php echo getPageUrl('doctor/my_appointments.php'); ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-alt me-2"></i>View All Appointments
                    </a>
                    <a href="<?php echo getPageUrl('doctor/profile.php'); ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-user-edit me-2"></i>Edit Profile
                    </a>
                    <a href="<?php echo getPageUrl('doctor/availability.php'); ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-alt me-2"></i>Manage Availability
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function approveAppointment(appointmentId) {
    if (confirm('Are you sure you want to approve this appointment?')) {
        fetch('<?php echo getPageUrl('approve_appointment.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                appointment_id: appointmentId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Appointment approved successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while approving the appointment.');
        });
    }
}
</script>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
