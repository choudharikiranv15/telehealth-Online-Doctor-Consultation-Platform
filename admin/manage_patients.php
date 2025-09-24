<?php
$page_title = "Manage Patients";
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../includes/db_connect.php';

$message = '';
$error = '';
$patients = []; // Initialize to prevent fatal error on query failure

// Get all patients
try {
    // --- START: CORRECTED QUERY ---
    // Removed 'pp.address' and added correct location fields from the 'users' table (u).
    $stmt = $db->query("
        SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.phone, u.created_at, u.city, u.state, u.country, u.status,
               pp.date_of_birth, pp.gender
        FROM users u
        LEFT JOIN patient_profiles pp ON u.id = pp.user_id
        WHERE u.role = 'patient'
        ORDER BY u.created_at DESC
    ");
    // --- END: CORRECTED QUERY ---
    $patients = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-users me-2"></i>Manage Patients</h2>
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
        <h5 class="mb-0">Patient Accounts (<?php echo count($patients); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($patients)): ?>
            <p class="text-muted">No patients found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Date of Birth</th>
                            <th>Gender</th>
                            <th>Joined</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($patient['username']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($patient['email']); ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    <?php 
                                        $location = array_filter([$patient['city'], $patient['state'], $patient['country']]);
                                        echo !empty($location) ? htmlspecialchars(implode(', ', $location)) : 'N/A';
                                    ?>
                                </td>
                                <td><?php echo $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A'; ?></td>
                                <td><?php echo ucfirst($patient['gender'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($patient['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $patient['status'] === 'active' ? 'success' : ($patient['status'] === 'suspended' ? 'danger' : 'secondary'); ?>">
                                        <?php echo ucfirst($patient['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_patient.php?id=<?php echo $patient['id']; ?>" class="btn btn-info btn-sm">View</a>
                                    <!-- Edit and Suspend buttons can be added here -->
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
