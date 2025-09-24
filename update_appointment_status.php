<?php
header('Content-Type: application/json');
require_once 'config.php';

// Debug: Log session info
error_log("=== APPOINTMENT STATUS UPDATE DEBUG ===");
error_log("Session status: " . session_status());
error_log("Session ID: " . session_id());
error_log("User ID in session: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Role in session: " . ($_SESSION['role'] ?? 'NOT SET'));
error_log("All session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("❌ UNAUTHORIZED - No user_id in session");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - No user session']);
    exit();
}

require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

// Debug logging
error_log("Appointment status update attempt - User ID: $user_id, Input: " . json_encode($input));

if (!$input || !isset($input['appointment_id']) || !isset($input['status'])) {
    error_log("Missing parameters - Input: " . json_encode($input));
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$appointment_id = (int)$input['appointment_id'];
$status = $input['status'];

// Validate status
$valid_statuses = ['pending', 'confirmed', 'active', 'completed', 'cancelled'];
if (!in_array($status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
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
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    // First, get the current appointment details for logging
    $stmt = $db->prepare("SELECT status FROM appointments WHERE id = ?");
    $stmt->execute([$appointment_id]);
    $current_appointment = $stmt->fetch();
    $old_status = $current_appointment['status'] ?? 'unknown';

    // Update appointment status with proper logging
    error_log("Updating appointment $appointment_id from '$old_status' to '$status' by user $user_id");

    $stmt = $db->prepare("
        UPDATE appointments
        SET status = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $result = $stmt->execute([$status, $user_id, $appointment_id]);

    error_log("Update result: " . ($result ? 'SUCCESS' : 'FAILED') . ", Rows affected: " . $stmt->rowCount());

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
            "Status updated via main API from $old_status to $status",
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    } catch (PDOException $e) {
        // Ignore if appointment_history table doesn't exist yet
        error_log("Could not log appointment history: " . $e->getMessage());
    }
    
    if ($stmt->rowCount() > 0) {
        // If status is completed, create video call record
        if ($status === 'completed') {
            try {
                $stmt = $db->prepare("
                    INSERT INTO video_calls (appointment_id, room_id, status, end_time) 
                    VALUES (?, ?, 'completed', CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE 
                    status = 'completed', 
                    end_time = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$appointment_id, 'telehealth_' . $appointment_id]);
            } catch (PDOException $e) {
                // Log error but don't fail the main update
                error_log("Error creating video call record: " . $e->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Appointment status updated successfully',
            'status' => $status
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No changes made to appointment'
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
