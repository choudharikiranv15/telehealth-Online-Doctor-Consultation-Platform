<?php
header('Content-Type: application/json');
require_once dirname(__FILE__) . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once dirname(__FILE__) . '/../includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$appointment_id = (int)($input['appointment_id'] ?? 0);
$missed_by = $input['missed_by'] ?? null; // 'doctor' or 'patient'

if (!$appointment_id || !in_array($missed_by, ['doctor', 'patient'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    // Verify user has access to this appointment
    $stmt = $db->prepare("
        SELECT id, doctor_id, patient_id, status
        FROM appointments
        WHERE id = ? AND (doctor_id = ? OR patient_id = ?)
    ");
    $stmt->execute([$appointment_id, $user_id, $user_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }

    // Update appointment to mark who missed it
    $stmt = $db->prepare("
        UPDATE appointments
        SET status = 'missed',
            missed_by = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$missed_by, $appointment_id]);

    // Log this in appointment history if table exists
    try {
        $stmt = $db->prepare("
            INSERT INTO appointment_history (appointment_id, old_status, new_status, changed_by, change_reason)
            VALUES (?, ?, 'missed', ?, ?)
        ");
        $stmt->execute([
            $appointment_id,
            $appointment['status'],
            $user_id,
            "Appointment missed by $missed_by - no show within 15 minutes"
        ]);
    } catch (PDOException $e) {
        // Ignore if table doesn't exist
        error_log("Could not log appointment history: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Appointment marked as missed',
        'missed_by' => $missed_by
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
