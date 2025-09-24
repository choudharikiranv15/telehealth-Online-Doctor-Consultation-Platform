<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['appointment_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$appointment_id = (int)$input['appointment_id'];
$status = $input['status'];
$user_id = $_SESSION['user_id'];

// Validate status
$valid_statuses = ['pending', 'confirmed', 'active', 'completed', 'cancelled', 'no_show'];
if (!in_array($status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit;
}

try {
    // Verify user has access to this appointment
    $stmt = $db->prepare("
        SELECT id FROM appointments 
        WHERE id = ? AND (doctor_id = ? OR patient_id = ?)
    ");
    $stmt->execute([$appointment_id, $user_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    
    // First, get the current appointment details for logging
    $stmt = $db->prepare("SELECT status FROM appointments WHERE id = ?");
    $stmt->execute([$appointment_id]);
    $current_appointment = $stmt->fetch();
    $old_status = $current_appointment['status'] ?? 'unknown';

    // Update appointment status with proper logging
    $stmt = $db->prepare("
        UPDATE appointments
        SET status = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$status, $user_id, $appointment_id]);

    // Log the status change in appointment_history if table exists
    try {
        $stmt = $db->prepare("
            INSERT INTO appointment_history (appointment_id, old_status, new_status, changed_by, change_reason, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $appointment_id,
            $old_status,
            $status,
            $user_id,
            "Status updated via API from $old_status to $status",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    } catch (PDOException $e) {
        // Ignore if appointment_history table doesn't exist yet
        error_log("Could not log appointment history: " . $e->getMessage());
    }
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Appointment status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>