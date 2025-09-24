<?php
header('Content-Type: application/json');
require_once dirname(__FILE__) . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once dirname(__FILE__) . '/../includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Update call status
    $input = json_decode(file_get_contents('php://input'), true);
    $appointment_id = (int)$input['appointment_id'];
    $status = $input['status']; // 'waiting', 'active', 'ended'

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
            exit();
        }

        // Update or insert call status
        $stmt = $db->prepare("
            INSERT INTO call_sessions (appointment_id, status, started_by, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            updated_at = NOW()
        ");
        $stmt->execute([$appointment_id, $status, $user_id]);

        echo json_encode(['success' => true, 'status' => $status]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }

} elseif ($method === 'GET') {
    // Get call status
    $appointment_id = (int)$_GET['appointment_id'];

    try {
        // Verify user has access to this appointment
        $stmt = $db->prepare("
            SELECT a.*, cs.status as call_status, cs.started_by, cs.updated_at as call_updated
            FROM appointments a
            LEFT JOIN call_sessions cs ON a.id = cs.appointment_id
            WHERE a.id = ? AND (a.doctor_id = ? OR a.patient_id = ?)
        ");
        $stmt->execute([$appointment_id, $user_id, $user_id]);
        $result = $stmt->fetch();

        if (!$result) {
            http_response_code(404);
            echo json_encode(['error' => 'Appointment not found']);
            exit();
        }

        // Determine if current user is doctor or patient
        $is_doctor = ($user_id == $result['doctor_id']);
        $other_user_id = $is_doctor ? $result['patient_id'] : $result['doctor_id'];

        // Get other user's name
        $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$other_user_id]);
        $other_user = $stmt->fetch();

        $response = [
            'appointment_id' => $appointment_id,
            'call_status' => $result['call_status'] ?? 'none',
            'started_by' => $result['started_by'],
            'is_call_starter' => ($result['started_by'] == $user_id),
            'other_user_name' => $other_user ? $other_user['first_name'] . ' ' . $other_user['last_name'] : 'Unknown',
            'other_user_role' => $is_doctor ? 'Patient' : 'Doctor',
            'can_join' => ($result['call_status'] === 'waiting' || $result['call_status'] === 'active'),
            'call_updated' => $result['call_updated']
        ];

        echo json_encode($response);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>