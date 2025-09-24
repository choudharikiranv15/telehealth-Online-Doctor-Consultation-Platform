<?php
$page_title = "Manage Appointments";
require_once '../config.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once '../includes/db_connect.php';

$doctor_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle appointment approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $action = $_POST['action'];

    try {
        if ($action === 'approve') {
            $stmt = $db->prepare("
                UPDATE appointments
                SET status = 'confirmed', reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ? AND doctor_id = ? AND status = 'pending'
            ");
            $result = $stmt->execute([$doctor_id, $appointment_id, $doctor_id]);

            if ($result && $stmt->rowCount() > 0) {
                $message = 'Appointment approved successfully!';
            } else {
                $error = 'Unable to approve appointment. It may have already been processed.';
            }

        } elseif ($action === 'reject') {
            $rejection_reason = trim($_POST['rejection_reason'] ?? '');

            if (empty($rejection_reason)) {
                $error = 'Please provide a reason for rejecting the appointment.';
            } else {
                $stmt = $db->prepare("
                    UPDATE appointments
                    SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ? AND doctor_id = ? AND status = 'pending'
                ");
                $result = $stmt->execute([$rejection_reason, $doctor_id, $appointment_id, $doctor_id]);

                if ($result && $stmt->rowCount() > 0) {
                    $message = 'Appointment rejected successfully.';
                } else {
                    $error = 'Unable to reject appointment. It may have already been processed.';
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Get all appointments for this doctor
try {
    $stmt = $db->prepare("
        SELECT a.*,
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               p.phone as patient_phone,
               p.email as patient_email
        FROM appointments a
        JOIN users p ON a.patient_id = p.id
        WHERE a.doctor_id = ?
        ORDER BY
            CASE
                WHEN a.status = 'pending' THEN 1
                WHEN a.status = 'confirmed' THEN 2
                WHEN a.status = 'completed' THEN 3
                WHEN a.status = 'cancelled' THEN 4
                WHEN a.status = 'rejected' THEN 5
                ELSE 6
            END,
            a.appointment_date DESC,
            a.appointment_time DESC
    ");
    $stmt->execute([$doctor_id]);
    $appointments = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Error loading appointments: ' . $e->getMessage();
    $appointments = [];
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-calendar-check me-2"></i>Manage Appointments</h2>
    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Pending Appointments Section -->
<?php
$pending_appointments = array_filter($appointments, function($apt) { return $apt['status'] === 'pending'; });
if (!empty($pending_appointments)):
?>
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Approval (<?php echo count($pending_appointments); ?>)</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($pending_appointments as $appointment): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card border-warning h-100">
                        <div class="card-body">
                            <h6 class="card-title text-warning">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($appointment['patient_name']); ?>
                            </h6>
                            <p class="card-text">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?><br>
                                    <i class="fas fa-clock me-1"></i><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?><br>
                                    <i class="fas fa-rupee-sign me-1"></i><?php echo number_format($appointment['consultation_fee'], 0); ?>
                                </small>
                            </p>

                            <?php if (!empty($appointment['symptoms'])): ?>
                                <div class="mb-2">
                                    <strong>Symptoms:</strong>
                                    <p class="text-muted small"><?php echo htmlspecialchars($appointment['symptoms']); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex gap-2">
                                <!-- Approve Button -->
                                <form method="POST" class="flex-fill" onsubmit="return confirm('Approve this appointment?')">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success btn-sm w-100">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </button>
                                </form>

                                <!-- Reject Button -->
                                <button type="button" class="btn btn-danger btn-sm flex-fill"
                                        onclick="showRejectModal(<?php echo $appointment['id']; ?>)">
                                    <i class="fas fa-times me-1"></i>Reject
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- All Appointments Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">All Appointments (<?php echo count($appointments); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($appointments)): ?>
            <p class="text-muted text-center">No appointments found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th>Fee</th>
                            <th>Details</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr class="<?php echo $appointment['status'] === 'pending' ? 'table-warning' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($appointment['patient_email']); ?></small>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?><br>
                                    <small><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo match($appointment['status']) {
                                            'pending' => 'warning',
                                            'confirmed' => 'primary',
                                            'completed' => 'success',
                                            'cancelled' => 'secondary',
                                            'rejected' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td>₹<?php echo number_format($appointment['consultation_fee'], 0); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" onclick="showDetailsModal(<?php echo htmlspecialchars(json_encode($appointment)); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                                <td>
                                    <?php if ($appointment['status'] === 'pending'): ?>
                                        <div class="btn-group" role="group">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this appointment?')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="showRejectModal(<?php echo $appointment['id']; ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    <?php elseif ($appointment['status'] === 'confirmed'): ?>
                                        <a href="../video_call.php?appointment_id=<?php echo $appointment['id']; ?>"
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-video"></i> Join Call
                                        </a>
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

<!-- Rejection Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="rejectAppointmentId">
                    <input type="hidden" name="action" value="reject">

                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Reason for Rejection *</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4"
                                  placeholder="Please provide a clear reason for rejecting this appointment..." required></textarea>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> The patient will be notified of your decision and the reason provided.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsModalBody">
                <!-- Details will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showRejectModal(appointmentId) {
    document.getElementById('rejectAppointmentId').value = appointmentId;
    document.getElementById('rejection_reason').value = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function showDetailsModal(appointment) {
    const modalBody = document.getElementById('detailsModalBody');

    const statusBadge = `<span class="badge bg-${getStatusColor(appointment.status)}">${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}</span>`;

    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Patient Information</h6>
                <p><strong>Name:</strong> ${appointment.patient_name}<br>
                   <strong>Email:</strong> ${appointment.patient_email}<br>
                   <strong>Phone:</strong> ${appointment.patient_phone || 'N/A'}</p>
            </div>
            <div class="col-md-6">
                <h6>Appointment Details</h6>
                <p><strong>Date:</strong> ${new Date(appointment.appointment_date).toLocaleDateString()}<br>
                   <strong>Time:</strong> ${new Date('1970-01-01T' + appointment.appointment_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}<br>
                   <strong>Status:</strong> ${statusBadge}<br>
                   <strong>Fee:</strong> ₹${parseFloat(appointment.consultation_fee).toLocaleString()}</p>
            </div>
        </div>

        ${appointment.symptoms ? `
            <div class="mt-3">
                <h6>Symptoms</h6>
                <p class="text-muted">${appointment.symptoms}</p>
            </div>
        ` : ''}

        ${appointment.notes ? `
            <div class="mt-3">
                <h6>Patient Notes</h6>
                <p class="text-muted">${appointment.notes}</p>
            </div>
        ` : ''}

        ${appointment.rejection_reason ? `
            <div class="mt-3">
                <h6>Rejection Reason</h6>
                <div class="alert alert-danger">${appointment.rejection_reason}</div>
            </div>
        ` : ''}

        <div class="mt-3">
            <small class="text-muted">
                <strong>Booked:</strong> ${new Date(appointment.created_at).toLocaleString()}<br>
                ${appointment.reviewed_at ? `<strong>Reviewed:</strong> ${new Date(appointment.reviewed_at).toLocaleString()}` : ''}
            </small>
        </div>
    `;

    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'confirmed': 'primary',
        'completed': 'success',
        'cancelled': 'secondary',
        'rejected': 'danger'
    };
    return colors[status] || 'secondary';
}
</script>

<?php require_once '../includes/footer.php'; ?>