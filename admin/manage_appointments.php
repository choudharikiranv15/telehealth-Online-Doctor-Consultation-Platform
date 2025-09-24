<?php
$page_title = "Manage Appointments";
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once '../includes/db_connect.php';

$error = '';
$appointments = [];

// Get all appointments with filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

try {
    $where_conditions = [];
    $params = [];
    
    if ($status_filter) {
        $where_conditions[] = "a.status = ?";
        $params[] = $status_filter;
    }
    
    if ($date_filter) {
        $where_conditions[] = "a.appointment_date = ?";
        $params[] = $date_filter;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $db->prepare("
        SELECT 
            a.*, 
            p_user.first_name as patient_first_name, 
            p_user.last_name as patient_last_name,
            d_user.first_name as doctor_first_name, 
            d_user.last_name as doctor_last_name,
            s.name as specialization
        FROM appointments a
        JOIN users p_user ON a.patient_id = p_user.id
        JOIN users d_user ON a.doctor_id = d_user.id
        LEFT JOIN doctor_profiles dp ON a.doctor_id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        $where_clause
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
    <h2><i class="fas fa-calendar-alt me-2"></i>Manage All Appointments</h2>
    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

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
                <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
            </div>
            <div class="col-md-4 align-self-end">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="manage_appointments.php" class="btn btn-outline-secondary">Clear</a>
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
            <p class="text-muted">No appointments found matching your criteria.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                     <thead class="table-dark">
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th>Fee</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td class="text-nowrap"><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($appointment['specialization'] ?? 'General Medicine'); ?></small>
                                </td>
                                <td class="date-time">
                                    <?php 
                                        $date = $appointment['appointment_date'];
                                        $time = $appointment['appointment_time'];
                                        
                                        if ($date && $date !== '0000-00-00') {
                                            echo date('M d, Y', strtotime($date));
                                        } else {
                                            echo '<span class="text-muted">Invalid Date</span>';
                                        }
                                        
                                        echo '<br>';
                                        
                                        if ($time && $time !== '00:00:00') {
                                            echo '<small class="text-muted">' . date('h:i A', strtotime($time)) . '</small>';
                                        } else {
                                            echo '<small class="text-muted">Invalid Time</small>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo $appointment['status'] === 'completed' ? 'success' :
                                            ($appointment['status'] === 'cancelled' ? 'secondary' :
                                            ($appointment['status'] === 'rejected' ? 'danger' :
                                            ($appointment['status'] === 'confirmed' ? 'primary' : 'warning')));
                                    ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td class="text-nowrap">₹<?php echo number_format($appointment['payment_amount'] ?? 0, 2); ?></td>
                                <td class="actions">
                                    <a href="#" class="btn btn-info btn-sm" title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
