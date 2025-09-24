<?php
$page_title = "Manage Doctors";
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once '../includes/db_connect.php';

$message = '';
$error = '';

// Handle doctor status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $doctor_id = $_POST['doctor_id'];
    
    if ($_POST['action'] === 'suspend') {
        try {
            // --- START: CORRECTED UPDATE QUERY ---
            // The 'status' column is in the 'users' table, not 'doctor_profiles'.
            $stmt = $db->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'doctor'");
            // --- END: CORRECTED UPDATE QUERY ---
            $stmt->execute([$doctor_id]);
            $message = 'Doctor suspended successfully.';
        } catch (PDOException $e) {
            $error = 'Error suspending doctor: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'activate') {
        try {
            // --- START: CORRECTED UPDATE QUERY ---
            // The 'status' column is in the 'users' table.
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'doctor'");
            // --- END: CORRECTED UPDATE QUERY ---
            $stmt->execute([$doctor_id]);
            $message = 'Doctor activated successfully.';
        } catch (PDOException $e) {
            $error = 'Error activating doctor: ' . $e->getMessage();
        }
    }
}

// --- Initialize $doctors to prevent fatal error on query failure ---
$doctors = [];
// Get all doctors
try {
    // --- START: CORRECTED SELECT QUERY ---
    // Changed 'dp.status' to 'u.status' as the status is in the users table.
    $stmt = $db->query("
        SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.phone, u.created_at, u.status,
               s.name as specialization, dp.license_number, dp.experience_years
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE u.role = 'doctor'
        ORDER BY u.created_at DESC
    ");
    // --- END: CORRECTED SELECT QUERY ---
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user-md me-2"></i>Manage Doctors</h2>
    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Doctor Accounts (<?php echo count($doctors); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($doctors)): ?>
            <p class="text-muted">No doctors found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Specialization</th>
                            <th>License</th>
                            <th>Experience</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctors as $doctor): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($doctor['username']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($doctor['email']); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($doctor['specialization'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($doctor['license_number'] ?? 'N/A'); ?></td>
                                <td><?php echo $doctor['experience_years'] ? $doctor['experience_years'] . ' years' : 'N/A'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $doctor['status'] === 'active' ? 'success' : ($doctor['status'] === 'suspended' ? 'danger' : 'secondary'); ?>">
                                        <?php echo ucfirst($doctor['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if ($doctor['status'] === 'active'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to suspend this doctor?')">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                                <input type="hidden" name="action" value="suspend">
                                                <button type="submit" class="btn btn-warning btn-sm">
                                                    Suspend
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    Activate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="view_doctor.php?id=<?php echo $doctor['id']; ?>" class="btn btn-info btn-sm">View</a>
                                        <a href="edit_doctor.php?id=<?php echo $doctor['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
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

<?php require_once '../includes/footer.php'; ?>
