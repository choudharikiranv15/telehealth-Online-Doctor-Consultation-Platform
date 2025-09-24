<?php
$page_title = "Manage Prescriptions";
require_once "../config.php";

// Check if user is admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit();
}

require_once "../includes/db_connect.php";

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$doctor_filter = isset($_GET['doctor']) ? (int)$_GET['doctor'] : 0;
$patient_filter = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

try {
    // Build the base query
    $where_conditions = [];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(p.prescription_number LIKE ? OR p.diagnosis LIKE ? OR p.medications LIKE ? OR
                              CONCAT(patient.first_name, ' ', patient.last_name) LIKE ? OR
                              CONCAT(doctor.first_name, ' ', doctor.last_name) LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    }

    if ($doctor_filter > 0) {
        $where_conditions[] = "a.doctor_id = ?";
        $params[] = $doctor_filter;
    }

    if ($patient_filter > 0) {
        $where_conditions[] = "a.patient_id = ?";
        $params[] = $patient_filter;
    }

    if (!empty($date_from)) {
        $where_conditions[] = "DATE(p.created_at) >= ?";
        $params[] = $date_from;
    }

    if (!empty($date_to)) {
        $where_conditions[] = "DATE(p.created_at) <= ?";
        $params[] = $date_to;
    }

    if (!empty($status_filter)) {
        if ($status_filter === 'expired') {
            $where_conditions[] = "p.valid_until < CURDATE()";
        } elseif ($status_filter === 'valid') {
            $where_conditions[] = "(p.valid_until IS NULL OR p.valid_until >= CURDATE())";
        }
    }

    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    // Get total count for pagination
    $count_query = "
        SELECT COUNT(*) as total
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN users doctor ON a.doctor_id = doctor.id
        JOIN users patient ON a.patient_id = patient.id
        LEFT JOIN doctor_profiles dp ON doctor.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        $where_clause
    ";

    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);

    // Get prescriptions with pagination
    $prescriptions_query = "
        SELECT p.*, a.appointment_date, a.appointment_time, a.patient_id, a.doctor_id,
               doctor.first_name as doctor_first_name, doctor.last_name as doctor_last_name,
               patient.first_name as patient_first_name, patient.last_name as patient_last_name,
               s.name as specialization
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN users doctor ON a.doctor_id = doctor.id
        JOIN users patient ON a.patient_id = patient.id
        LEFT JOIN doctor_profiles dp ON doctor.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        $where_clause
        ORDER BY p.created_at DESC
        LIMIT $records_per_page OFFSET $offset
    ";

    $stmt = $db->prepare($prescriptions_query);
    $stmt->execute($params);
    $prescriptions = $stmt->fetchAll();

    // Get doctors for filter dropdown
    $doctors_stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, s.name as specialization
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE u.role = 'doctor'
        ORDER BY u.first_name, u.last_name
    ");
    $doctors_stmt->execute();
    $doctors = $doctors_stmt->fetchAll();

    // Get patients for filter dropdown
    $patients_stmt = $db->prepare("
        SELECT id, first_name, last_name
        FROM users
        WHERE role = 'patient'
        ORDER BY first_name, last_name
    ");
    $patients_stmt->execute();
    $patients = $patients_stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

require_once "../includes/header.php";
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-prescription me-2"></i>Manage Prescriptions</h2>
        <div class="d-flex gap-2">
            <span class="badge bg-info fs-6"><?php echo $total_records; ?> Total Prescriptions</span>
            <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-filter me-2"></i>Search & Filter Prescriptions</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Prescription #, diagnosis, medication, doctor, patient...">
                </div>
                <div class="col-md-2">
                    <label for="doctor" class="form-label">Doctor</label>
                    <select class="form-select" id="doctor" name="doctor">
                        <option value="">All Doctors</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>"
                                    <?php echo $doctor_filter == $doctor['id'] ? 'selected' : ''; ?>>
                                Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                <?php if ($doctor['specialization']): ?>
                                    (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="patient" class="form-label">Patient</label>
                    <select class="form-select" id="patient" name="patient">
                        <option value="">All Patients</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>"
                                    <?php echo $patient_filter == $patient['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from"
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to"
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-1">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All</option>
                        <option value="valid" <?php echo $status_filter === 'valid' ? 'selected' : ''; ?>>Valid</option>
                        <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <a href="manage_prescriptions.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Prescriptions Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Prescription Records</h5>
            <?php if ($total_pages > 1): ?>
                <small class="text-muted">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    (<?php echo $offset + 1; ?>-<?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?>)
                </small>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($prescriptions)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-prescription fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No prescriptions found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Prescription #</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th>Diagnosis</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prescriptions as $prescription): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($prescription['prescription_number']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y H:i', strtotime($prescription['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="view_patient.php?id=<?php echo $prescription['patient_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($prescription['patient_first_name'] . ' ' . $prescription['patient_last_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        Dr. <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($prescription['specialization'] ?? 'General Medicine'); ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($prescription['appointment_date'])); ?>
                                        <br>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($prescription['appointment_time'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="d-inline-block text-truncate" style="max-width: 200px;">
                                            <?php echo htmlspecialchars($prescription['diagnosis']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($prescription['valid_until']): ?>
                                            <span class="badge bg-<?php echo strtotime($prescription['valid_until']) > time() ? 'success' : 'danger'; ?>">
                                                <?php if (strtotime($prescription['valid_until']) > time()): ?>
                                                    Valid until <?php echo date('M d, Y', strtotime($prescription['valid_until'])); ?>
                                                <?php else: ?>
                                                    Expired
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No expiry</span>
                                        <?php endif; ?>

                                        <?php if ($prescription['is_digital_signature']): ?>
                                            <br><span class="badge bg-info mt-1">
                                                <i class="fas fa-shield-alt me-1"></i>Digitally Signed
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="viewPrescription(<?php echo $prescription['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($prescription['prescription_pdf_path']): ?>
                                                <a href="<?php echo '../' . htmlspecialchars($prescription['prescription_pdf_path']); ?>"
                                                   class="btn btn-sm btn-outline-success" target="_blank">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Prescription pagination" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Prescription Details Modal -->
<div class="modal fade" id="prescriptionModal" tabindex="-1" aria-labelledby="prescriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="prescriptionModalLabel">Prescription Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="prescriptionModalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function viewPrescription(prescriptionId) {
    // Show loading
    document.getElementById('prescriptionModalBody').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading prescription details...</p>
        </div>
    `;

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('prescriptionModal'));
    modal.show();

    // Fetch prescription details
    fetch(`get_prescription_details.php?id=${prescriptionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('prescriptionModalBody').innerHTML = data.html;
            } else {
                document.getElementById('prescriptionModalBody').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading prescription details: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('prescriptionModalBody').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading prescription details. Please try again.
                </div>
            `;
        });
}
</script>

<?php require_once "../includes/footer.php"; ?>