<?php
header('Content-Type: application/json');
require_once "../config.php";

// Check if user is admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once "../includes/db_connect.php";

$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$prescription_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid prescription ID']);
    exit();
}

try {
    // Get prescription details
    $stmt = $db->prepare("
        SELECT p.*, a.appointment_date, a.appointment_time,
               doctor.first_name as doctor_first_name, doctor.last_name as doctor_last_name,
               patient.first_name as patient_first_name, patient.last_name as patient_last_name,
               s.name as specialization
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN users doctor ON a.doctor_id = doctor.id
        JOIN users patient ON a.patient_id = patient.id
        LEFT JOIN doctor_profiles dp ON doctor.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE p.id = ?
    ");
    $stmt->execute([$prescription_id]);
    $prescription = $stmt->fetch();

    if (!$prescription) {
        echo json_encode(['success' => false, 'message' => 'Prescription not found']);
        exit();
    }

    // Generate HTML content
    $html = '
    <div class="prescription-details">
        <!-- Header -->
        <div class="row mb-3">
            <div class="col-md-6">
                <h6><i class="fas fa-file-medical me-2"></i>Prescription #' . htmlspecialchars($prescription['prescription_number']) . '</h6>
                <small class="text-muted">Created: ' . date('M d, Y - h:i A', strtotime($prescription['created_at'])) . '</small>
            </div>
            <div class="col-md-6 text-end">';

    if ($prescription['prescription_pdf_path']) {
        $html .= '
                <a href="../' . htmlspecialchars($prescription['prescription_pdf_path']) . '"
                   class="btn btn-sm btn-outline-primary" target="_blank">
                    <i class="fas fa-download me-1"></i>Download PDF
                </a>';
    }

    $html .= '
            </div>
        </div>

        <!-- Patient & Doctor Info -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1"><i class="fas fa-user me-2"></i>Patient</h6>
                        <p class="mb-0">' . htmlspecialchars($prescription['patient_first_name'] . ' ' . $prescription['patient_last_name']) . '</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-1"><i class="fas fa-user-md me-2"></i>Doctor</h6>
                        <p class="mb-0">Dr. ' . htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']) . '</p>
                        <small class="text-muted">' . htmlspecialchars($prescription['specialization'] ?? 'General Medicine') . '</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appointment Info -->
        <div class="mb-3">
            <div class="card bg-light">
                <div class="card-body py-2">
                    <h6 class="card-title mb-1"><i class="fas fa-calendar-alt me-2"></i>Appointment</h6>
                    <p class="mb-0">' . date('M d, Y', strtotime($prescription['appointment_date'])) . ' at ' . date('h:i A', strtotime($prescription['appointment_time'])) . '</p>
                </div>
            </div>
        </div>

        <!-- Diagnosis -->
        <div class="mb-3">
            <h6><i class="fas fa-stethoscope me-2"></i>Diagnosis</h6>
            <div class="border rounded p-3 bg-light">
                ' . nl2br(htmlspecialchars($prescription['diagnosis'])) . '
            </div>
        </div>

        <!-- Medications -->
        <div class="mb-3">
            <h6><i class="fas fa-pills me-2"></i>Medications</h6>
            <div class="border rounded p-3 bg-light">
                ' . nl2br(htmlspecialchars($prescription['medications'])) . '
            </div>
        </div>';

    // Dosage Instructions
    if ($prescription['dosage_instructions']) {
        $html .= '
        <div class="mb-3">
            <h6><i class="fas fa-clock me-2"></i>Dosage Instructions</h6>
            <div class="border rounded p-3 bg-light">
                ' . nl2br(htmlspecialchars($prescription['dosage_instructions'])) . '
            </div>
        </div>';
    }

    // Precautions
    if ($prescription['precautions']) {
        $html .= '
        <div class="mb-3">
            <h6><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Precautions</h6>
            <div class="alert alert-warning py-2">
                ' . nl2br(htmlspecialchars($prescription['precautions'])) . '
            </div>
        </div>';
    }

    // Follow-up Information
    if ($prescription['follow_up_date'] || $prescription['follow_up_instructions']) {
        $html .= '<div class="mb-3"><h6><i class="fas fa-calendar-check me-2"></i>Follow-up Information</h6>';

        if ($prescription['follow_up_date']) {
            $html .= '
            <div class="mb-2">
                <strong>Follow-up Date:</strong>
                <span class="badge bg-info ms-2">' . date('M d, Y', strtotime($prescription['follow_up_date'])) . '</span>
            </div>';
        }

        if ($prescription['follow_up_instructions']) {
            $html .= '
            <div>
                <strong>Follow-up Instructions:</strong>
                <div class="border rounded p-2 bg-light mt-1">
                    ' . nl2br(htmlspecialchars($prescription['follow_up_instructions'])) . '
                </div>
            </div>';
        }

        $html .= '</div>';
    }

    // Validity & Status
    $html .= '
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-calendar-times me-2"></i>Validity</h6>';

    if ($prescription['valid_until']) {
        $is_valid = strtotime($prescription['valid_until']) > time();
        $html .= '
                <span class="badge bg-' . ($is_valid ? 'success' : 'danger') . '">
                    Valid until ' . date('M d, Y', strtotime($prescription['valid_until']));
        if (!$is_valid) {
            $html .= ' (Expired)';
        }
        $html .= '</span>';
    } else {
        $html .= '<span class="badge bg-secondary">No expiry date</span>';
    }

    $html .= '
            </div>
            <div class="col-md-6">';

    if ($prescription['is_digital_signature']) {
        $html .= '
                <h6><i class="fas fa-shield-alt me-2"></i>Security</h6>
                <span class="badge bg-success">
                    <i class="fas fa-shield-alt me-1"></i>Digitally Signed
                </span>';
    }

    $html .= '
            </div>
        </div>
    </div>';

    echo json_encode(['success' => true, 'html' => $html]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>