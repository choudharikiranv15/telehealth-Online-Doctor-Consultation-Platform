<?php
$page_title = "Write Prescription";
require_once '../config.php';
require_once '../includes/db_connect.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ' . getPageUrl('login.php'));
    exit();
}

$doctor_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;

if (!$appointment_id) {
    header('Location: ' . getPageUrl('doctor/'));
    exit();
}

// Get appointment details
try {
    $stmt = $db->prepare("
        SELECT
            a.*,
            p.first_name as patient_first_name,
            p.last_name as patient_last_name,
            p.email as patient_email,
            p.phone as patient_phone,
            p.date_of_birth as patient_dob,
            p.gender as patient_gender,
            pp.blood_group,
            pp.medical_history,
            pp.allergies,
            pp.current_medications
        FROM appointments a
        JOIN users p ON a.patient_id = p.id
        LEFT JOIN patient_profiles pp ON p.id = pp.user_id
        WHERE a.id = ? AND a.doctor_id = ?
    ");
    $stmt->execute([$appointment_id, $doctor_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        $_SESSION['error'] = "Appointment not found.";
        header('Location: ' . getPageUrl('doctor/'));
        exit();
    }

    // Check if appointment is missed by patient
    if ($appointment['status'] === 'missed' && $appointment['missed_by'] === 'patient') {
        $_SESSION['error'] = "Cannot write prescription - Patient missed the appointment.";
        header('Location: ' . getPageUrl('doctor/'));
        exit();
    }

    // Check if appointment is completed
    if ($appointment['status'] !== 'completed') {
        $_SESSION['error'] = "Appointment must be completed before writing a prescription.";
        header('Location: ' . getPageUrl('doctor/'));
        exit();
    }

    // Check if prescription already exists
    $check_stmt = $db->prepare("SELECT id FROM prescriptions WHERE appointment_id = ?");
    $check_stmt->execute([$appointment_id]);
    $existing_prescription = $check_stmt->fetch();

    if ($existing_prescription) {
        $_SESSION['error'] = "Prescription already exists for this appointment.";
        header('Location: ' . getPageUrl('doctor/'));
        exit();
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: ' . getPageUrl('doctor/'));
    exit();
}

// Handle prescription submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnosis = trim($_POST['diagnosis']);
    $medications = trim($_POST['medications']);
    $dosage_instructions = trim($_POST['dosage_instructions']);
    $precautions = trim($_POST['precautions']);
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
    $follow_up_instructions = trim($_POST['follow_up_instructions']);

    if (empty($diagnosis) || empty($medications)) {
        $error = "Diagnosis and medications are required.";
    } else {
        try {
            // Generate unique prescription number
            $prescription_number = 'RX_' . date('Ymd') . '_' . str_pad($appointment_id, 4, '0', STR_PAD_LEFT);

            $stmt = $db->prepare("
                INSERT INTO prescriptions
                (appointment_id, prescription_number, diagnosis, medications, dosage_instructions,
                 precautions, follow_up_date, follow_up_instructions, valid_until, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 30 DAY), NOW())
            ");

            $stmt->execute([
                $appointment_id,
                $prescription_number,
                $diagnosis,
                $medications,
                $dosage_instructions,
                $precautions,
                $follow_up_date,
                $follow_up_instructions
            ]);

            $_SESSION['success'] = "Prescription created successfully!";
            header('Location: ' . getPageUrl('doctor/'));
            exit();

        } catch (PDOException $e) {
            $error = "Error creating prescription: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-prescription-bottle-alt me-2"></i>Write Prescription
                </h4>
            </div>
            <div class="card-body">
                <!-- Patient Information -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="text-primary">Patient Information</h5>
                        <div class="patient-info bg-light p-3 rounded">
                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($appointment['patient_email']); ?></p>
                            <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($appointment['patient_phone']); ?></p>
                            <?php if ($appointment['patient_dob']): ?>
                                <p class="mb-1"><strong>Date of Birth:</strong> <?php echo date('M d, Y', strtotime($appointment['patient_dob'])); ?></p>
                            <?php endif; ?>
                            <?php if ($appointment['patient_gender']): ?>
                                <p class="mb-1"><strong>Gender:</strong> <?php echo ucfirst($appointment['patient_gender']); ?></p>
                            <?php endif; ?>
                            <?php if ($appointment['blood_group']): ?>
                                <p class="mb-1"><strong>Blood Group:</strong> <?php echo htmlspecialchars($appointment['blood_group']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-primary">Appointment Details</h5>
                        <div class="appointment-info bg-light p-3 rounded">
                            <p class="mb-1"><strong>Date:</strong> <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></p>
                            <p class="mb-1"><strong>Time:</strong> <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
                            <p class="mb-1"><strong>Symptoms:</strong> <?php echo htmlspecialchars($appointment['symptoms']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Medical History -->
                <?php if ($appointment['medical_history'] || $appointment['allergies'] || $appointment['current_medications']): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="text-primary">Medical History</h5>
                        <div class="medical-history bg-light p-3 rounded">
                            <?php if ($appointment['medical_history']): ?>
                                <p class="mb-2"><strong>Medical History:</strong> <?php echo htmlspecialchars($appointment['medical_history']); ?></p>
                            <?php endif; ?>
                            <?php if ($appointment['allergies']): ?>
                                <p class="mb-2"><strong>Allergies:</strong> <?php echo htmlspecialchars($appointment['allergies']); ?></p>
                            <?php endif; ?>
                            <?php if ($appointment['current_medications']): ?>
                                <p class="mb-0"><strong>Current Medications:</strong> <?php echo htmlspecialchars($appointment['current_medications']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Prescription Form -->
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="diagnosis" class="form-label">
                                <strong>Diagnosis <span class="text-danger">*</span></strong>
                            </label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3"
                                      placeholder="Enter your diagnosis..." required><?php echo isset($_POST['diagnosis']) ? htmlspecialchars($_POST['diagnosis']) : ''; ?></textarea>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="medications" class="form-label">
                                <strong>Medications/Treatment <span class="text-danger">*</span></strong>
                            </label>
                            <textarea class="form-control" id="medications" name="medications" rows="6"
                                      placeholder="List medications with strength (e.g., Amoxicillin 500mg)..." required><?php echo isset($_POST['medications']) ? htmlspecialchars($_POST['medications']) : ''; ?></textarea>
                            <div class="form-text">Enter each medication on a separate line with dosage information.</div>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="dosage_instructions" class="form-label">
                                <strong>Dosage Instructions</strong>
                            </label>
                            <textarea class="form-control" id="dosage_instructions" name="dosage_instructions" rows="4"
                                      placeholder="How to take the medications (e.g., Take 1 tablet twice daily after meals)..."><?php echo isset($_POST['dosage_instructions']) ? htmlspecialchars($_POST['dosage_instructions']) : ''; ?></textarea>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="precautions" class="form-label">
                                <strong>Precautions & Warnings</strong>
                            </label>
                            <textarea class="form-control" id="precautions" name="precautions" rows="3"
                                      placeholder="Any precautions, warnings, or side effects to watch for..."><?php echo isset($_POST['precautions']) ? htmlspecialchars($_POST['precautions']) : ''; ?></textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="follow_up_date" class="form-label">
                                <strong>Follow-up Date</strong>
                            </label>
                            <input type="date" class="form-control" id="follow_up_date" name="follow_up_date"
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                   value="<?php echo isset($_POST['follow_up_date']) ? htmlspecialchars($_POST['follow_up_date']) : ''; ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="follow_up_instructions" class="form-label">
                                <strong>Follow-up Instructions</strong>
                            </label>
                            <textarea class="form-control" id="follow_up_instructions" name="follow_up_instructions" rows="2"
                                      placeholder="Instructions for follow-up visit..."><?php echo isset($_POST['follow_up_instructions']) ? htmlspecialchars($_POST['follow_up_instructions']) : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo getPageUrl('doctor/'); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Create Prescription
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>