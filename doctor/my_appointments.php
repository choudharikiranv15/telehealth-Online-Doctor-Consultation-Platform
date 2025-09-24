<?php
$page_title = "My Appointments";
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

// Handle appointment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appointment_id = $_POST['appointment_id'];

    if ($_POST['action'] === 'approve') {
        $notes = isset($_POST['approval_notes']) ? trim($_POST['approval_notes']) : '';
        try {
            $stmt = $db->prepare("
                UPDATE appointments
                SET status = 'confirmed',
                    doctor_notes = CONCAT(COALESCE(doctor_notes, ''), ?)
                WHERE id = ? AND doctor_id = ? AND status = 'pending'
            ");
            $notes_text = !empty($notes) ? "Approved: " . $notes . "\n" : "Appointment approved by doctor.\n";
            $stmt->execute([$notes_text, $appointment_id, $doctor_id]);

            if ($stmt->rowCount() > 0) {
                $message = 'Appointment approved successfully.';
            } else {
                $error = 'Appointment not found or already processed.';
            }
        } catch (PDOException $e) {
            $error = 'Error approving appointment: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'reject') {
        $reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';
        try {
            $stmt = $db->prepare("
                UPDATE appointments
                SET status = 'cancelled',
                    doctor_notes = CONCAT(COALESCE(doctor_notes, ''), ?)
                WHERE id = ? AND doctor_id = ? AND status = 'pending'
            ");
            $reason_text = !empty($reason) ? "Rejected: " . $reason . "\n" : "Appointment rejected by doctor.\n";
            $stmt->execute([$reason_text, $appointment_id, $doctor_id]);

            if ($stmt->rowCount() > 0) {
                $message = 'Appointment rejected successfully.';
            } else {
                $error = 'Appointment not found or already processed.';
            }
        } catch (PDOException $e) {
            $error = 'Error rejecting appointment: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'complete') {
        try {
            $stmt = $db->prepare("UPDATE appointments SET status = 'completed' WHERE id = ? AND doctor_id = ?");
            $stmt->execute([$appointment_id, $doctor_id]);
            $message = 'Appointment marked as completed.';
        } catch (PDOException $e) {
            $error = 'Error updating appointment.';
        }
    }
}

// Get all appointments with filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

try {
    $where_conditions = ["a.doctor_id = ?"];
    $params = [$doctor_id];
    
    if ($status_filter) {
        $where_conditions[] = "a.status = ?";
        $params[] = $status_filter;
    }
    
    if ($date_filter) {
        $where_conditions[] = "a.appointment_date = ?";
        $params[] = $date_filter;
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    $stmt = $db->prepare("
        SELECT a.*, u.first_name, u.last_name, u.phone, u.email
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE $where_clause
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-calendar-alt me-2"></i>My Appointments</h2>
    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="date" class="form-label">Date</label>
                <input type="date" class="form-control" id="date" name="date" value="<?php echo $date_filter; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="my_appointments.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Appointments (<?php echo count($appointments); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($appointments)): ?>
            <p class="text-muted">No appointments found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Date & Time</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Symptoms</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($appointment['phone']); ?></small>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($appointment['email']); ?></small>
                                </td>
                                <td>
                                    <?php 
                                        $date = $appointment['appointment_date'];
                                        $time = $appointment['appointment_time'];
                                        
                                        if ($date && $date !== '0000-00-00') {
                                            echo '<strong>' . date('M d, Y', strtotime($date)) . '</strong>';
                                        } else {
                                            echo '<span class="text-muted">Invalid Date</span>';
                                        }
                                        
                                        echo '<br>';
                                        
                                        if ($time && $time !== '00:00:00') {
                                            echo date('h:i A', strtotime($time));
                                        } else {
                                            echo '<span class="text-muted">Invalid Time</span>';
                                        }
                                    ?>
                                </td>
                                <td><?php echo $appointment['duration']; ?> minutes</td>
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
                                    <?php if ($appointment['symptoms']): ?>
                                        <small><?php echo htmlspecialchars(substr($appointment['symptoms'], 0, 50)) . (strlen($appointment['symptoms']) > 50 ? '...' : ''); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">No symptoms listed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if ($appointment['status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-success btn-sm" onclick="showApprovalModal(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>')">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="showRejectionModal(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>')">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                        <?php endif; ?>

                                        <?php if (in_array($appointment['status'], ['confirmed'])): ?>
                                            <a href="start_consultation.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-video me-1"></i>Start Consultation
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($appointment['status'] === 'confirmed'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <input type="hidden" name="action" value="complete">
                                                <button type="submit" class="btn btn-info btn-sm">
                                                    <i class="fas fa-check-circle me-1"></i>Mark Complete
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($appointment['status'] === 'completed'): ?>
                                            <a href="create_prescription.php?id=<?php echo $appointment['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-prescription me-1"></i>Prescription
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($appointment['status'] === 'cancelled'): ?>
                                            <span class="badge bg-secondary">Rejected</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="approvalModalLabel">
                    <i class="fas fa-check-circle me-2"></i>Approve Appointment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="approvalForm">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="approvalAppointmentId">
                    <input type="hidden" name="action" value="approve">

                    <div class="mb-3">
                        <p>Are you sure you want to approve the appointment for <strong id="approvalPatientName"></strong>?</p>
                    </div>

                    <div class="mb-3">
                        <label for="approval_notes" class="form-label">Approval Notes (Optional)</label>
                        <textarea class="form-control" id="approval_notes" name="approval_notes" rows="3"
                                  placeholder="Add any notes about the approval..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i>Approve Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectionModal" tabindex="-1" aria-labelledby="rejectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rejectionModalLabel">
                    <i class="fas fa-times-circle me-2"></i>Reject Appointment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="rejectionForm">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="rejectionAppointmentId">
                    <input type="hidden" name="action" value="reject">

                    <div class="mb-3">
                        <p>Are you sure you want to reject the appointment for <strong id="rejectionPatientName"></strong>?</p>
                    </div>

                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Reason for Rejection *</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3"
                                  placeholder="Please provide a reason for rejecting this appointment..." required></textarea>
                        <small class="text-muted">This reason will be visible to the patient.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-1"></i>Reject Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showApprovalModal(appointmentId, patientName) {
    document.getElementById('approvalAppointmentId').value = appointmentId;
    document.getElementById('approvalPatientName').textContent = patientName;
    document.getElementById('approval_notes').value = '';

    var approvalModal = new bootstrap.Modal(document.getElementById('approvalModal'));
    approvalModal.show();
}

function showRejectionModal(appointmentId, patientName) {
    document.getElementById('rejectionAppointmentId').value = appointmentId;
    document.getElementById('rejectionPatientName').textContent = patientName;
    document.getElementById('rejection_reason').value = '';

    var rejectionModal = new bootstrap.Modal(document.getElementById('rejectionModal'));
    rejectionModal.show();
}

// Form validation
document.getElementById('rejectionForm').addEventListener('submit', function(e) {
    const reason = document.getElementById('rejection_reason').value.trim();
    if (reason.length < 10) {
        e.preventDefault();
        alert('Please provide a detailed reason (at least 10 characters) for rejecting the appointment.');
        return false;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
