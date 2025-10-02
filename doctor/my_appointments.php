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
                SET status = 'rejected',
                    rejection_reason = ?,
                    reviewed_by = ?,
                    reviewed_at = NOW()
                WHERE id = ? AND doctor_id = ? AND status = 'pending'
            ");
            $stmt->execute([$reason, $doctor_id, $appointment_id, $doctor_id]);

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
    } elseif ($_POST['action'] === 'approve_reschedule') {
        $response_notes = isset($_POST['reschedule_response']) ? trim($_POST['reschedule_response']) : '';
        try {
            // Check if new time slot is available
            $stmt = $db->prepare("SELECT requested_date, requested_time FROM appointments WHERE id = ? AND doctor_id = ?");
            $stmt->execute([$appointment_id, $doctor_id]);
            $reschedule_data = $stmt->fetch();

            if ($reschedule_data) {
                // Check for conflicts
                $stmt = $db->prepare("
                    SELECT id FROM appointments
                    WHERE doctor_id = ?
                    AND appointment_date = ?
                    AND appointment_time = ?
                    AND status IN ('pending', 'confirmed')
                    AND id != ?
                ");
                $stmt->execute([
                    $doctor_id,
                    $reschedule_data['requested_date'],
                    $reschedule_data['requested_time'],
                    $appointment_id
                ]);

                if ($stmt->fetch()) {
                    $error = 'Cannot approve: The requested time slot is no longer available.';
                } else {
                    // Approve reschedule
                    $stmt = $db->prepare("
                        UPDATE appointments
                        SET appointment_date = requested_date,
                            appointment_time = requested_time,
                            status = 'confirmed',
                            reschedule_response = ?,
                            requested_date = NULL,
                            requested_time = NULL
                        WHERE id = ? AND doctor_id = ? AND status = 'reschedule_requested'
                    ");
                    $response_text = !empty($response_notes) ? $response_notes : 'Reschedule request approved';
                    $stmt->execute([$response_text, $appointment_id, $doctor_id]);

                    if ($stmt->rowCount() > 0) {
                        $message = 'Reschedule request approved successfully.';
                    } else {
                        $error = 'Reschedule request not found or already processed.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Error approving reschedule: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'reject_reschedule') {
        $rejection_reason = isset($_POST['reschedule_rejection_reason']) ? trim($_POST['reschedule_rejection_reason']) : '';
        try {
            $stmt = $db->prepare("
                UPDATE appointments
                SET status = 'confirmed',
                    reschedule_response = ?,
                    requested_date = NULL,
                    requested_time = NULL
                WHERE id = ? AND doctor_id = ? AND status = 'reschedule_requested'
            ");
            $response_text = !empty($rejection_reason) ? 'Rejected: ' . $rejection_reason : 'Reschedule request rejected';
            $stmt->execute([$response_text, $appointment_id, $doctor_id]);

            if ($stmt->rowCount() > 0) {
                $message = 'Reschedule request rejected. Original appointment time maintained.';
            } else {
                $error = 'Reschedule request not found or already processed.';
            }
        } catch (PDOException $e) {
            $error = 'Error rejecting reschedule: ' . $e->getMessage();
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
                    <option value="reschedule_requested" <?php echo $status_filter === 'reschedule_requested' ? 'selected' : ''; ?>>Reschedule Requested</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="missed" <?php echo $status_filter === 'missed' ? 'selected' : ''; ?>>Missed</option>
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

                                        // Show requested reschedule date/time if exists
                                        if ($appointment['status'] === 'reschedule_requested' && !empty($appointment['requested_date'])) {
                                            echo '<br><span class="badge bg-info mt-1">Requested: ' .
                                                 date('M d, Y', strtotime($appointment['requested_date'])) . ' ' .
                                                 date('h:i A', strtotime($appointment['requested_time'])) . '</span>';
                                        }
                                    ?>
                                </td>
                                <td><?php echo $appointment['duration']; ?> minutes</td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo $appointment['status'] === 'completed' ? 'success' :
                                            ($appointment['status'] === 'cancelled' ? 'secondary' :
                                            ($appointment['status'] === 'rejected' ? 'danger' :
                                            ($appointment['status'] === 'missed' ? 'warning' :
                                            ($appointment['status'] === 'confirmed' ? 'primary' :
                                            ($appointment['status'] === 'reschedule_requested' ? 'info' : 'warning')))));
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                    </span>
                                    <?php if ($appointment['status'] === 'rejected' && !empty($appointment['rejection_reason'])): ?>
                                        <br><small class="text-muted" title="<?php echo htmlspecialchars($appointment['rejection_reason']); ?>">
                                            <i class="fas fa-info-circle"></i>
                                            <?php echo htmlspecialchars(substr($appointment['rejection_reason'], 0, 30)) . (strlen($appointment['rejection_reason']) > 30 ? '...' : ''); ?>
                                        </small>
                                    <?php elseif ($appointment['status'] === 'missed' && !empty($appointment['missed_by'])): ?>
                                        <br><small class="text-muted">
                                            <i class="fas fa-user-times"></i>
                                            Missed by: <?php echo ucfirst($appointment['missed_by']); ?>
                                        </small>
                                    <?php endif; ?>
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

                                        <?php if ($appointment['status'] === 'reschedule_requested'): ?>
                                            <button type="button" class="btn btn-success btn-sm" onclick="showRescheduleApprovalModal(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>', '<?php echo date('M d, Y h:i A', strtotime($appointment['requested_date'] . ' ' . $appointment['requested_time'])); ?>', '<?php echo htmlspecialchars($appointment['reschedule_reason']); ?>')">
                                                <i class="fas fa-check me-1"></i>Approve Reschedule
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="showRescheduleRejectionModal(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>')">
                                                <i class="fas fa-times me-1"></i>Reject Reschedule
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
                                            <?php
                                            // Check if prescription exists
                                            $prescription_check = $db->prepare("SELECT id FROM prescriptions WHERE appointment_id = ?");
                                            $prescription_check->execute([$appointment['id']]);
                                            $prescription_exists = $prescription_check->fetch();
                                            ?>
                                            <?php if ($prescription_exists): ?>
                                                <a href="view_prescription.php?id=<?php echo $prescription_exists['id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-file-medical me-1"></i>View Prescription
                                                </a>
                                            <?php else: ?>
                                                <a href="write_prescription.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-prescription me-1"></i>Write Prescription
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($appointment['status'] === 'missed'): ?>
                                            <?php if ($appointment['missed_by'] === 'patient'): ?>
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-ban me-1"></i>Patient No-Show
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>You Missed Call
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($appointment['status'] === 'rejected'): ?>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="showRejectionDetailsModal('<?php echo htmlspecialchars($appointment['rejection_reason'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-info-circle me-1"></i>View Rejection
                                            </button>
                                        <?php elseif ($appointment['status'] === 'cancelled'): ?>
                                            <span class="badge bg-secondary">Cancelled</span>
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

<!-- Reschedule Approval Modal -->
<div class="modal fade" id="rescheduleApprovalModal" tabindex="-1" aria-labelledby="rescheduleApprovalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="rescheduleApprovalModalLabel">
                    <i class="fas fa-calendar-check me-2"></i>Approve Reschedule Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="rescheduleApprovalForm">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="rescheduleApprovalAppointmentId">
                    <input type="hidden" name="action" value="approve_reschedule">

                    <div class="mb-3">
                        <p>Reschedule request from <strong id="rescheduleApprovalPatientName"></strong></p>
                        <div class="alert alert-info">
                            <strong>Requested New Date/Time:</strong><br>
                            <span id="rescheduleNewDateTime"></span>
                        </div>
                        <div class="alert alert-warning">
                            <strong>Patient's Reason:</strong><br>
                            <span id="rescheduleReason"></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="reschedule_response" class="form-label">Response Message (Optional)</label>
                        <textarea class="form-control" id="reschedule_response" name="reschedule_response" rows="2"
                                  placeholder="Add a message for the patient..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i>Approve Reschedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reschedule Rejection Modal -->
<div class="modal fade" id="rescheduleRejectionModal" tabindex="-1" aria-labelledby="rescheduleRejectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rescheduleRejectionModalLabel">
                    <i class="fas fa-times-circle me-2"></i>Reject Reschedule Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="rescheduleRejectionForm">
                <div class="modal-body">
                    <input type="hidden" name="appointment_id" id="rescheduleRejectionAppointmentId">
                    <input type="hidden" name="action" value="reject_reschedule">

                    <div class="mb-3">
                        <p>Reject reschedule request from <strong id="rescheduleRejectionPatientName"></strong>?</p>
                        <div class="alert alert-warning">
                            The original appointment time will be maintained.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="reschedule_rejection_reason" class="form-label">Reason for Rejection *</label>
                        <textarea class="form-control" id="reschedule_rejection_reason" name="reschedule_rejection_reason" rows="3"
                                  placeholder="Please provide a reason for rejecting this reschedule request..." required></textarea>
                        <small class="text-muted">This reason will be visible to the patient.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-1"></i>Reject Reschedule
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

function showRescheduleApprovalModal(appointmentId, patientName, newDateTime, reason) {
    document.getElementById('rescheduleApprovalAppointmentId').value = appointmentId;
    document.getElementById('rescheduleApprovalPatientName').textContent = patientName;
    document.getElementById('rescheduleNewDateTime').textContent = newDateTime;
    document.getElementById('rescheduleReason').textContent = reason;
    document.getElementById('reschedule_response').value = '';

    var rescheduleApprovalModal = new bootstrap.Modal(document.getElementById('rescheduleApprovalModal'));
    rescheduleApprovalModal.show();
}

function showRescheduleRejectionModal(appointmentId, patientName) {
    document.getElementById('rescheduleRejectionAppointmentId').value = appointmentId;
    document.getElementById('rescheduleRejectionPatientName').textContent = patientName;
    document.getElementById('reschedule_rejection_reason').value = '';

    var rescheduleRejectionModal = new bootstrap.Modal(document.getElementById('rescheduleRejectionModal'));
    rescheduleRejectionModal.show();
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

document.getElementById('rescheduleRejectionForm').addEventListener('submit', function(e) {
    const reason = document.getElementById('reschedule_rejection_reason').value.trim();
    if (reason.length < 10) {
        e.preventDefault();
        alert('Please provide a detailed reason (at least 10 characters) for rejecting the reschedule request.');
        return false;
    }
});

function showRejectionDetailsModal(reason) {
    const modalHtml = `
        <div class="modal fade" id="rejectionDetailsModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Rejection Reason</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger mb-0">
                            ${reason}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existingModal = document.getElementById('rejectionDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('rejectionDetailsModal'));
    modal.show();

    // Clean up after modal is hidden
    document.getElementById('rejectionDetailsModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
