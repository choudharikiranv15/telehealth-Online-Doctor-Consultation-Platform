<?php
$page_title = "Reports";
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once '../includes/db_connect.php';

$error = '';
$stats = [
    'total_patients' => 0,
    'total_doctors' => 0,
    'total_appointments' => 0,
    'total_revenue' => 0,
];

try {
    // Get total patients
    $stats['total_patients'] = $db->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();
    
    // Get total doctors
    $stats['total_doctors'] = $db->query("SELECT COUNT(*) FROM users WHERE role = 'doctor'")->fetchColumn();

    // Get total appointments
    $stats['total_appointments'] = $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();

    // --- START: CORRECTED QUERY ---
    // Changed 'payment_status' to the correct column name 'status' to calculate revenue.
    $stats['total_revenue'] = $db->query("SELECT SUM(payment_amount) FROM appointments WHERE status = 'completed'")->fetchColumn();
    // --- END: CORRECTED QUERY ---

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-chart-line me-2"></i>Platform Reports</h2>
    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card bg-primary text-white h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title"><?php echo $stats['total_patients']; ?></h3>
                    <p class="card-text">Total Patients</p>
                </div>
                <i class="fas fa-users fa-3x"></i>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card bg-info text-white h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title"><?php echo $stats['total_doctors']; ?></h3>
                    <p class="card-text">Total Doctors</p>
                </div>
                <i class="fas fa-user-md fa-3x"></i>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title"><?php echo $stats['total_appointments']; ?></h3>
                    <p class="card-text">Total Appointments</p>
                </div>
                <i class="fas fa-calendar-check fa-3x"></i>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="card-title">₹<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
                    <p class="card-text">Total Revenue</p>
                </div>
                <i class="fas fa-rupee-sign fa-3x"></i>
            </div>
        </div>
    </div>
</div>

<!-- Additional report sections can be added below -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">More Reports</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">More detailed reports and charts will be available here in future updates.</p>
        <!-- Example: A chart could be rendered here using a library like Chart.js -->
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

