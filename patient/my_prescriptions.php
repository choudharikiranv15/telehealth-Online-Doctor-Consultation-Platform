<?php
$page_title = "My Prescriptions";
require_once '../config.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    // Redirect to the login page using the full URL for robustness
    header("Location: " . SITE_URL . "/login.php");
    exit();
}

require_once '../includes/db_connect.php';

$patient_id = $_SESSION['user_id'];
$error = '';
// Initialize prescriptions as an empty array to prevent count() errors on failure
$prescriptions = []; 

// Get patient's prescriptions
try {
    // --- START: CORRECTED SQL QUERY ---
    // The query now correctly selects `dp.specialization` from the doctor_profiles table.
    $stmt = $db->prepare("
        SELECT
            p.*,
            a.appointment_date,
            u.first_name,
            u.last_name,
            s.name as specialization
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE a.patient_id = ?
        ORDER BY p.created_at DESC
    ");
    // --- END: CORRECTED SQL QUERY ---

    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll();

} catch (PDOException $e) {
    // Display a detailed error for debugging purposes.
    // In a live environment, you would log this error instead.
    $error = 'Database error: ' . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-pills me-2"></i>My Prescriptions</h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">Prescriptions Found (<?php echo count($prescriptions); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($prescriptions)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file-prescription fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No prescriptions found</h5>
                <p class="text-muted">You don't have any prescriptions yet. Complete a consultation to receive one.</p>
                <a href="book_appointment.php" class="btn btn-primary mt-2">
                    <i class="fas fa-calendar-plus me-2"></i>Book an Appointment
                </a>
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($prescriptions as $prescription): ?>
                    <div class="list-group-item list-group-item-action flex-column align-items-start mb-3 border rounded">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1 text-primary">
                                Prescription from Dr. <?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?>
                            </h5>
                            <small class="text-muted"><?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></small>
                        </div>
                        <p class="mb-1">
                            <strong>Specialization:</strong> <?php echo htmlspecialchars($prescription['specialization'] ?? 'General Medicine'); ?> <br>
                            <strong>Appointment Date:</strong> <?php echo date('M d, Y', strtotime($prescription['appointment_date'])); ?>
                        </p>
                        <hr>
                        <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars($prescription['diagnosis'] ?? 'Not specified'); ?></p>
                        <p><strong>Medications:</strong> <?php echo nl2br(htmlspecialchars($prescription['medications'] ?? 'Not specified')); ?></p>
                        <p><strong>Dosage Instructions:</strong> <?php echo nl2br(htmlspecialchars($prescription['dosage_instructions'] ?? 'Not specified')); ?></p>
                        <?php if(!empty($prescription['precautions'])): ?>
                            <p><strong>Precautions:</strong> <?php echo nl2br(htmlspecialchars($prescription['precautions'])); ?></p>
                        <?php endif; ?>
                        <?php if(!empty($prescription['follow_up_instructions'])): ?>
                            <p><strong>Follow-up Instructions:</strong> <?php echo nl2br(htmlspecialchars($prescription['follow_up_instructions'])); ?></p>
                        <?php endif; ?>
                        <?php if(!empty($prescription['follow_up_date'])): ?>
                            <p><strong>Next Follow-up:</strong> <?php echo date('M d, Y', strtotime($prescription['follow_up_date'])); ?></p>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="<?php echo getPageUrl('prescription_pdf.php?id=' . $prescription['id']); ?>"
                               class="btn btn-primary btn-sm" target="_blank" title="View Prescription PDF">
                                <i class="fas fa-eye me-1"></i>View PDF
                            </a>
                            <button class="btn btn-outline-primary btn-sm" onclick="printPrescription(<?php echo $prescription['id']; ?>)" title="Print Prescription">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function printPrescription(prescriptionId) {
    // Open the PDF in a new window and trigger print
    const printWindow = window.open('<?php echo getPageUrl('prescription_pdf.php?id='); ?>' + prescriptionId, '_blank');
    printWindow.onload = function() {
        setTimeout(function() {
            printWindow.print();
        }, 500);
    };
}
</script>

<?php require_once '../includes/footer.php'; ?>
