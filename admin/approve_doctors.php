<?php
$page_title = "Doctor Approval";
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once '../includes/db_connect.php';

$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle doctor approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $doctor_id = (int)$_POST['doctor_id'];
    $action = $_POST['action'];

    try {
        if ($action === 'approve') {
            $verification_notes = trim($_POST['verification_notes'] ?? '');

            $db->beginTransaction();

            // Update user status to active
            $stmt = $db->prepare("
                UPDATE users
                SET status = 'active', approved_by = ?, approved_at = NOW()
                WHERE id = ? AND role = 'doctor' AND status = 'pending_approval'
            ");
            $result = $stmt->execute([$admin_id, $doctor_id]);

            // Update doctor profile as verified
            $stmt = $db->prepare("
                UPDATE doctor_profiles
                SET profile_verified = TRUE, verification_notes = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$verification_notes, $doctor_id]);

            if ($result && $stmt->rowCount() >= 0) {
                $db->commit();
                $message = 'Doctor profile approved successfully!';
            } else {
                $db->rollBack();
                $error = 'Unable to approve doctor. Profile may have already been processed.';
            }

        } elseif ($action === 'reject') {
            $rejection_reason = trim($_POST['rejection_reason'] ?? '');

            if (empty($rejection_reason)) {
                $error = 'Please provide a reason for rejecting the doctor profile.';
            } else {
                $stmt = $db->prepare("
                    UPDATE users
                    SET status = 'inactive', rejection_reason = ?, approved_by = ?, approved_at = NOW()
                    WHERE id = ? AND role = 'doctor' AND status = 'pending_approval'
                ");
                $result = $stmt->execute([$rejection_reason, $admin_id, $doctor_id]);

                if ($result && $stmt->rowCount() > 0) {
                    $message = 'Doctor profile rejected successfully.';
                } else {
                    $error = 'Unable to reject doctor. Profile may have already been processed.';
                }
            }
        }
    } catch (PDOException $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Get pending doctor profiles
try {
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.phone, u.created_at,
               dp.specialization_id, dp.license_number, dp.experience_years, dp.qualification,
               dp.bio, dp.consultation_fee,
               s.name as specialization_name
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE u.role = 'doctor' AND u.status = 'pending_approval'
        ORDER BY u.created_at ASC
    ");
    $stmt->execute();
    $pending_doctors = $stmt->fetchAll();

    // Get recently processed doctors for reference
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.first_name, u.last_name, u.status, u.approved_at,
               u.rejection_reason, admin.first_name as approved_by_name
        FROM users u
        LEFT JOIN users admin ON u.approved_by = admin.id
        WHERE u.role = 'doctor' AND u.status IN ('active', 'inactive') AND u.approved_at IS NOT NULL
        ORDER BY u.approved_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $processed_doctors = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Error loading doctor profiles: ' . $e->getMessage();
    $pending_doctors = [];
    $processed_doctors = [];
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user-md me-2"></i>Doctor Profile Approval</h2>
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

<!-- Pending Approvals Section -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>Pending Doctor Approvals
                    <span class="badge bg-dark ms-2"><?php echo count($pending_doctors); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($pending_doctors)): ?>
                    <p class="text-muted text-center">No doctors pending approval.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($pending_doctors as $doctor): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card border-warning h-100">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <i class="fas fa-user-md me-1"></i>
                                            Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            Applied: <?php echo date('M d, Y', strtotime($doctor['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <strong>Contact:</strong><br>
                                            <small>
                                                <?php echo htmlspecialchars($doctor['email']); ?><br>
                                                <?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?>
                                            </small>
                                        </div>

                                        <div class="mb-2">
                                            <strong>Specialization:</strong><br>
                                            <small><?php echo htmlspecialchars($doctor['specialization_name'] ?? 'Not specified'); ?></small>
                                        </div>

                                        <div class="mb-2">
                                            <strong>License:</strong><br>
                                            <small><?php echo htmlspecialchars($doctor['license_number'] ?? 'Not provided'); ?></small>
                                        </div>

                                        <div class="mb-2">
                                            <strong>Experience:</strong><br>
                                            <small><?php echo $doctor['experience_years'] ? $doctor['experience_years'] . ' years' : 'Not specified'; ?></small>
                                        </div>

                                        <div class="mb-3">
                                            <strong>Fee:</strong>
                                            <span class="text-primary">₹<?php echo number_format($doctor['consultation_fee'] ?? 0, 0); ?></span>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-info btn-sm flex-fill"
                                                    onclick="showDetailsModal(<?php echo htmlspecialchars(json_encode($doctor)); ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-success btn-sm flex-fill"
                                                    onclick="showApproveModal(<?php echo $doctor['id']; ?>, '<?php echo addslashes($doctor['first_name'] . ' ' . $doctor['last_name']); ?>')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm flex-fill"
                                                    onclick="showRejectModal(<?php echo $doctor['id']; ?>, '<?php echo addslashes($doctor['first_name'] . ' ' . $doctor['last_name']); ?>')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recently Processed Section -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recently Processed Doctors</h5>
            </div>
            <div class="card-body">
                <?php if (empty($processed_doctors)): ?>
                    <p class="text-muted text-center">No doctors processed yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Doctor</th>
                                    <th>Status</th>
                                    <th>Processed By</th>
                                    <th>Date</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($processed_doctors as $doc): ?>
                                    <tr>
                                        <td>Dr. <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $doc['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo $doc['status'] === 'active' ? 'Approved' : 'Rejected'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['approved_by_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($doc['approved_at'])); ?></td>
                                        <td>
                                            <?php if ($doc['rejection_reason']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($doc['rejection_reason'], 0, 50)); ?>...</small>
                                            <?php else: ?>
                                                <small class="text-success">Approved</small>
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
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Approve Doctor Profile</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="doctor_id" id="approveDoctorId">
                    <input type="hidden" name="action" value="approve">

                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        You are about to approve <strong id="approveDoctorName"></strong>'s profile.
                        This will make them visible to patients for booking appointments.
                    </div>

                    <div class="mb-3">
                        <label for="verification_notes" class="form-label">Verification Notes (Optional)</label>
                        <textarea class="form-control" id="verification_notes" name="verification_notes" rows="3"
                                  placeholder="Add any notes about profile verification..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i>Approve Doctor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Reject Doctor Profile</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="doctor_id" id="rejectDoctorId">
                    <input type="hidden" name="action" value="reject">

                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        You are about to reject <strong id="rejectDoctorName"></strong>'s profile.
                        They will not be able to see patients until they reapply.
                    </div>

                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Reason for Rejection *</label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4"
                                  placeholder="Please provide a clear reason for rejecting this profile..." required></textarea>
                        <div class="form-text">
                            Be specific about what needs to be corrected (e.g., invalid license, incomplete information, etc.)
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-1"></i>Reject Profile
                    </button>
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
                <h5 class="modal-title">Doctor Profile Details</h5>
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
function showApproveModal(doctorId, doctorName) {
    document.getElementById('approveDoctorId').value = doctorId;
    document.getElementById('approveDoctorName').textContent = doctorName;
    document.getElementById('verification_notes').value = '';
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function showRejectModal(doctorId, doctorName) {
    document.getElementById('rejectDoctorId').value = doctorId;
    document.getElementById('rejectDoctorName').textContent = doctorName;
    document.getElementById('rejection_reason').value = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function showDetailsModal(doctor) {
    const modalBody = document.getElementById('detailsModalBody');

    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Personal Information</h6>
                <p><strong>Name:</strong> Dr. ${doctor.first_name} ${doctor.last_name}<br>
                   <strong>Username:</strong> ${doctor.username}<br>
                   <strong>Email:</strong> ${doctor.email}<br>
                   <strong>Phone:</strong> ${doctor.phone || 'Not provided'}</p>
            </div>
            <div class="col-md-6">
                <h6>Application Details</h6>
                <p><strong>Applied:</strong> ${new Date(doctor.created_at).toLocaleDateString()}<br>
                   <strong>Status:</strong> <span class="badge bg-warning">Pending Approval</span></p>
            </div>
        </div>

        <hr>

        <div class="row">
            <div class="col-md-6">
                <h6>Professional Information</h6>
                <p><strong>Specialization:</strong> ${doctor.specialization_name || 'Not specified'}<br>
                   <strong>License Number:</strong> ${doctor.license_number || 'Not provided'}<br>
                   <strong>Experience:</strong> ${doctor.experience_years ? doctor.experience_years + ' years' : 'Not specified'}<br>
                   <strong>Consultation Fee:</strong> ₹${parseFloat(doctor.consultation_fee || 0).toLocaleString()}</p>
            </div>
            <div class="col-md-6">
                <h6>Additional Information</h6>
                <p><strong>Qualification:</strong><br>
                   <span class="text-muted">${doctor.qualification || 'Not provided'}</span></p>
            </div>
        </div>

        ${doctor.bio ? `
            <hr>
            <h6>Bio</h6>
            <p class="text-muted">${doctor.bio}</p>
        ` : ''}
    `;

    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>