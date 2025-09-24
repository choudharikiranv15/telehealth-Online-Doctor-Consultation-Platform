<?php
$page_title = "Admin Dashboard";
require_once dirname(__FILE__) . '/../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once dirname(__FILE__) . '/../includes/db_connect.php';

// Get statistics
try {
    $stmt = $db->query("SELECT COUNT(*) as total_doctors FROM users WHERE role = 'doctor'");
    $total_doctors = $stmt->fetch()['total_doctors'];

    $stmt = $db->query("SELECT COUNT(*) as total_patients FROM users WHERE role = 'patient'");
    $total_patients = $stmt->fetch()['total_patients'];

    $stmt = $db->query("SELECT COUNT(*) as total_appointments FROM appointments");
    $total_appointments = $stmt->fetch()['total_appointments'];

    $stmt = $db->query("SELECT COUNT(*) as pending_appointments FROM appointments WHERE status = 'pending'");
    $pending_appointments = $stmt->fetch()['pending_appointments'];

    $stmt = $db->query("SELECT COUNT(*) as total_prescriptions FROM prescriptions");
    $total_prescriptions = $stmt->fetch()['total_prescriptions'];

    // Get pending doctor approvals count
    $stmt = $db->query("SELECT COUNT(*) as pending_doctor_approvals FROM users WHERE role = 'doctor' AND status = 'pending_approval'");
    $pending_doctor_approvals = $stmt->fetch()['pending_doctor_approvals'];
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</h2>
    <div>
        <a href="<?php echo getPageUrl('admin/approve_doctors.php'); ?>" class="btn btn-warning me-2">
            <i class="fas fa-user-check me-1"></i>Doctor Approvals
            <?php if ($pending_doctor_approvals > 0): ?>
                <span class="badge bg-danger"><?php echo $pending_doctor_approvals; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo getPageUrl('admin/manage_doctors.php'); ?>" class="btn btn-primary me-2">Manage Doctors</a>
        <a href="<?php echo getPageUrl('admin/manage_patients.php'); ?>" class="btn btn-success">Manage Patients</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card bg-primary text-white dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title"><?php echo $total_doctors; ?></h4>
                        <p class="card-text">Total Doctors</p>
                    </div>
                    <div>
                        <i class="fas fa-user-md fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card bg-success text-white dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title"><?php echo $total_patients; ?></h4>
                        <p class="card-text">Total Patients</p>
                    </div>
                    <div>
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card bg-info text-white dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title"><?php echo $total_appointments; ?></h4>
                        <p class="card-text">Total Appointments</p>
                    </div>
                    <div>
                        <i class="fas fa-calendar-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card bg-warning text-white dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title"><?php echo $pending_appointments; ?></h4>
                        <p class="card-text">Pending Appointments</p>
                    </div>
                    <div>
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-4">
        <a href="<?php echo getPageUrl('admin/approve_doctors.php'); ?>" class="text-decoration-none">
            <div class="card bg-<?php echo $pending_doctor_approvals > 0 ? 'danger' : 'secondary'; ?> text-white dashboard-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title"><?php echo $pending_doctor_approvals; ?></h4>
                            <p class="card-text">Doctor Approvals</p>
                            <?php if ($pending_doctor_approvals > 0): ?>
                                <small class="fw-bold">Click to review →</small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <i class="fas fa-user-md-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-secondary text-white dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title"><?php echo $total_prescriptions; ?></h4>
                        <p class="card-text">Total Prescriptions</p>
                    </div>
                    <div>
                        <i class="fas fa-prescription fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="<?php echo getPageUrl('admin/manage_doctors.php'); ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-user-md me-2"></i>Manage Doctors
                    </a>
                    <a href="<?php echo getPageUrl('admin/manage_patients.php'); ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i>Manage Patients
                    </a>
                    <a href="<?php echo getPageUrl('admin/manage_appointments.php'); ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-alt me-2"></i>View All Appointments
                    </a>
                    <a href="<?php echo getPageUrl('admin/manage_prescriptions.php'); ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-prescription me-2"></i>Manage Prescriptions
                    </a>
                    <a href="<?php echo getPageUrl('admin/view_reports.php'); ?>" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-bar me-2"></i>View Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">No recent activity to display.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
