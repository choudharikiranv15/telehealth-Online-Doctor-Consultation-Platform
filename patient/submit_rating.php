<?php
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../includes/db_connect.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$patient_id = $_SESSION['user_id'];
$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';
$is_anonymous = isset($_POST['is_anonymous']) ? (int)$_POST['is_anonymous'] : 0;

// Validate input
if ($appointment_id <= 0 || $doctor_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

try {
    // Verify appointment exists and belongs to patient
    $stmt = $db->prepare("
        SELECT id, status, patient_id, doctor_id
        FROM appointments
        WHERE id = ? AND patient_id = ?
    ");
    $stmt->execute([$appointment_id, $patient_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }

    // Check if appointment is completed
    if ($appointment['status'] !== 'completed') {
        echo json_encode(['success' => false, 'message' => 'You can only rate completed consultations']);
        exit();
    }

    // Verify doctor_id matches
    if ($appointment['doctor_id'] != $doctor_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid doctor ID']);
        exit();
    }

    // Check if review already exists
    $stmt = $db->prepare("SELECT id FROM reviews WHERE appointment_id = ?");
    $stmt->execute([$appointment_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already rated this consultation']);
        exit();
    }

    // Insert review
    $stmt = $db->prepare("
        INSERT INTO reviews (appointment_id, patient_id, doctor_id, rating, review_text, is_anonymous, is_verified)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$appointment_id, $patient_id, $doctor_id, $rating, $review_text, $is_anonymous]);

    // Manually update doctor_profiles rating (failsafe in case triggers don't work)
    $stmt = $db->prepare("
        UPDATE doctor_profiles
        SET
            rating = (SELECT AVG(rating) FROM reviews WHERE doctor_id = ?),
            total_reviews = (SELECT COUNT(*) FROM reviews WHERE doctor_id = ?)
        WHERE user_id = ?
    ");
    $stmt->execute([$doctor_id, $doctor_id, $doctor_id]);

    // Log for debugging
    error_log("Rating submitted successfully for doctor_id: $doctor_id, rating: $rating");

    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your feedback!'
    ]);

} catch (PDOException $e) {
    error_log("Rating submission error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while submitting your rating. Please try again.'
    ]);
}
