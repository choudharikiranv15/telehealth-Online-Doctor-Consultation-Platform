<?php
require_once 'config.php';
require_once 'includes/db_connect.php';
require_once 'includes/pdf_generator.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . getPageUrl('login.php'));
    exit();
}

$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$prescription_id) {
    $_SESSION['error'] = "Invalid prescription ID.";
    header('Location: ' . getPageUrl('index.php'));
    exit();
}

try {
    // Check if user has access to this prescription
    $access_check = $db->prepare("
        SELECT p.id
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        WHERE p.id = ? AND (a.patient_id = ? OR a.doctor_id = ?)
    ");
    $access_check->execute([$prescription_id, $_SESSION['user_id'], $_SESSION['user_id']]);

    if (!$access_check->fetch()) {
        $_SESSION['error'] = "You don't have access to this prescription.";
        header('Location: ' . getPageUrl('index.php'));
        exit();
    }

    // Generate PDF
    $pdf_generator = new PrescriptionPDF($db);
    $html_content = $pdf_generator->generatePrescription($prescription_id);

    // Output the HTML (which can be printed to PDF by browser)
    echo $html_content;

} catch (Exception $e) {
    $_SESSION['error'] = "Error generating prescription: " . $e->getMessage();
    header('Location: ' . getPageUrl('index.php'));
    exit();
}
?>