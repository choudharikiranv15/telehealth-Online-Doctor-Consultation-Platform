<?php
$page_title = "View Patient Details";
require_once "../config.php";

// Check if user is admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit();
}

require_once "../includes/db_connect.php";

$patient_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if (!$patient_id) {
    header("Location: manage_patients.php");
    exit();
}

// Get patient details
try {
    $stmt = $db->prepare("
        SELECT u.*, pp.date_of_birth, pp.gender, pp.address
        FROM users u
        LEFT JOIN patient_profiles pp ON u.id = pp.user_id
        WHERE u.id = ? AND u.role = \"patient\"
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();

    if (!$patient) {
        header("Location: manage_patients.php");
        exit();
    }

    // Get patient appointments
    $stmt = $db->prepare("
        SELECT a.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name, s.name as specialization
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll();

    // Get patient prescriptions
    $stmt = $db->prepare("
        SELECT p.*, a.appointment_date, a.appointment_time,
               u.first_name as doctor_first_name, u.last_name as doctor_last_name,
               s.name as specialization
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE a.patient_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

require_once "../includes/header.php";
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user me-2"></i>Patient Details</h2>
        <a href="manage_patients.php" class="btn btn-secondary">Back to Patients</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Patient Information</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-user-circle fa-4x text-primary"></i>
                    </div>
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td><?php echo htmlspecialchars($patient["first_name"] . " " . $patient["last_name"]); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td><?php echo htmlspecialchars($patient["email"]); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Phone:</strong></td>
                            <td><?php echo htmlspecialchars($patient["phone"] ?? "Not provided"); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Gender:</strong></td>
                            <td><?php echo htmlspecialchars($patient["gender"] ?? "Not specified"); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Date of Birth:</strong></td>
                            <td><?php echo $patient["date_of_birth"] ? date("M d, Y", strtotime($patient["date_of_birth"])) : "Not provided"; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td><span class="badge bg-success">Active</span></td>
                        </tr>
                        <tr>
                            <td><strong>Joined:</strong></td>
                            <td><?php echo date("M d, Y", strtotime($patient["created_at"])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Appointment History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($appointments)): ?>
                        <p class="text-muted">No appointments found for this patient.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Doctor</th>
                                        <th>Specialization</th>
                                        <th>Status</th>
                                        <th>Symptoms</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <?php echo date("M d, Y", strtotime($appointment["appointment_date"])); ?><br>
                                                <small class="text-muted"><?php echo date("h:i A", strtotime($appointment["appointment_time"])); ?></small>
                                            </td>
                                            <td>
                                                Dr. <?php echo htmlspecialchars($appointment["doctor_first_name"] . " " . $appointment["doctor_last_name"]); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment["specialization"] ?? "General"); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $appointment["status"] === "completed" ? "success" : 
                                                        ($appointment["status"] === "cancelled" ? "danger" : 
                                                        ($appointment["status"] === "confirmed" ? "primary" : "warning")); 
                                                ?>">
                                                    <?php echo ucfirst($appointment["status"]); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(substr($appointment["symptoms"] ?? "No symptoms listed", 0, 50)); ?></td>
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

    <!-- Prescriptions Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-prescription me-2"></i>Prescription History</h5>
                    <span class="badge bg-primary"><?php echo count($prescriptions); ?> Prescriptions</span>
                </div>
                <div class="card-body">
                    <?php if (empty($prescriptions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-prescription fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No prescriptions found for this patient.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($prescriptions as $prescription): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card border">
                                        <div class="card-header bg-light">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <i class="fas fa-file-medical me-2"></i>
                                                        Prescription #<?php echo htmlspecialchars($prescription['prescription_number']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y - h:i A', strtotime($prescription['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <?php if ($prescription['prescription_pdf_path']): ?>
                                                        <a href="<?php echo '../' . htmlspecialchars($prescription['prescription_pdf_path']); ?>"
                                                           class="btn btn-sm btn-outline-primary" target="_blank">
                                                            <i class="fas fa-download me-1"></i>PDF
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <strong>Doctor:</strong>
                                                Dr. <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($prescription['specialization'] ?? 'General Medicine'); ?></small>
                                            </div>

                                            <div class="mb-3">
                                                <strong>Appointment:</strong>
                                                <?php echo date('M d, Y', strtotime($prescription['appointment_date'])); ?> at
                                                <?php echo date('h:i A', strtotime($prescription['appointment_time'])); ?>
                                            </div>

                                            <div class="mb-3">
                                                <strong>Diagnosis:</strong>
                                                <p class="mb-2"><?php echo nl2br(htmlspecialchars($prescription['diagnosis'])); ?></p>
                                            </div>

                                            <div class="mb-3">
                                                <strong>Medications:</strong>
                                                <div class="border rounded p-2 bg-light">
                                                    <?php echo nl2br(htmlspecialchars($prescription['medications'])); ?>
                                                </div>
                                            </div>

                                            <?php if ($prescription['dosage_instructions']): ?>
                                                <div class="mb-3">
                                                    <strong>Dosage Instructions:</strong>
                                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($prescription['dosage_instructions'])); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($prescription['precautions']): ?>
                                                <div class="mb-3">
                                                    <strong>Precautions:</strong>
                                                    <div class="alert alert-warning py-2">
                                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                                        <?php echo nl2br(htmlspecialchars($prescription['precautions'])); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($prescription['follow_up_date']): ?>
                                                <div class="mb-3">
                                                    <strong>Follow-up Date:</strong>
                                                    <span class="badge bg-info">
                                                        <?php echo date('M d, Y', strtotime($prescription['follow_up_date'])); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($prescription['follow_up_instructions']): ?>
                                                <div class="mb-3">
                                                    <strong>Follow-up Instructions:</strong>
                                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($prescription['follow_up_instructions'])); ?></p>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($prescription['valid_until']): ?>
                                                <div class="mb-3">
                                                    <strong>Valid Until:</strong>
                                                    <span class="badge bg-<?php echo strtotime($prescription['valid_until']) > time() ? 'success' : 'danger'; ?>">
                                                        <?php echo date('M d, Y', strtotime($prescription['valid_until'])); ?>
                                                        <?php if (strtotime($prescription['valid_until']) <= time()): ?>
                                                            (Expired)
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Created: <?php echo date('M d, Y H:i', strtotime($prescription['created_at'])); ?>
                                                </small>
                                                <?php if ($prescription['is_digital_signature']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-shield-alt me-1"></i>Digitally Signed
                                                    </span>
                                                <?php endif; ?>
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
</div>

<?php require_once "../includes/footer.php"; ?>
