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
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Track when user joins the call
    $input = json_decode(file_get_contents('php://input'), true);
    $appointment_id = (int)($input['appointment_id'] ?? 0);

    if (!$appointment_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing appointment_id']);
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

        $is_doctor = ($user_id == $appointment['doctor_id']);
        $join_field = $is_doctor ? 'doctor_joined_at' : 'patient_joined_at';

        // Insert or update call session
        $stmt = $db->prepare("
            INSERT INTO call_sessions (appointment_id, started_by, $join_field)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            $join_field = NOW(),
            started_by = COALESCE(started_by, ?),
            updated_at = NOW()
        ");
        $stmt->execute([$appointment_id, $user_id, $user_id]);

        // Get current session status
        $stmt = $db->prepare("
            SELECT doctor_joined_at, patient_joined_at
            FROM call_sessions
            WHERE appointment_id = ?
        ");
        $stmt->execute([$appointment_id]);
        $session = $stmt->fetch();

        $both_joined = !empty($session['doctor_joined_at']) && !empty($session['patient_joined_at']);

        // If both joined, update appointment status to 'active'
        if ($both_joined && $appointment['status'] === 'confirmed') {
            $stmt = $db->prepare("
                UPDATE appointments
                SET status = 'active'
                WHERE id = ?
            ");
            $stmt->execute([$appointment_id]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Join tracked successfully',
            'role' => $is_doctor ? 'doctor' : 'patient',
            'both_joined' => $both_joined,
            'doctor_joined' => !empty($session['doctor_joined_at']),
            'patient_joined' => !empty($session['patient_joined_at'])
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

} elseif ($method === 'GET') {
    // Check call join status
    $appointment_id = (int)($_GET['appointment_id'] ?? 0);

    if (!$appointment_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing appointment_id']);
        exit();
    }

    try {
        // Verify user has access to this appointment
        $stmt = $db->prepare("
            SELECT a.*, cs.doctor_joined_at, cs.patient_joined_at, cs.created_at as session_created
            FROM appointments a
            LEFT JOIN call_sessions cs ON a.id = cs.appointment_id
            WHERE a.id = ? AND (a.doctor_id = ? OR a.patient_id = ?)
        ");
        $stmt->execute([$appointment_id, $user_id, $user_id]);
        $data = $stmt->fetch();

        if (!$data) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            exit();
        }

        $is_doctor = ($user_id == $data['doctor_id']);
        $doctor_joined = !empty($data['doctor_joined_at']);
        $patient_joined = !empty($data['patient_joined_at']);
        $both_joined = $doctor_joined && $patient_joined;

        // Check for timeout (15 minutes waiting time)
        $timeout_minutes = 15;
        $is_timed_out = false;
        $missed_by = null;

        if ($data['session_created']) {
            $session_created_time = strtotime($data['session_created']);
            $current_time = time();
            $elapsed_minutes = ($current_time - $session_created_time) / 60;

            if ($elapsed_minutes > $timeout_minutes && !$both_joined) {
                $is_timed_out = true;
                // Determine who missed it
                if ($doctor_joined && !$patient_joined) {
                    $missed_by = 'patient';
                } elseif ($patient_joined && !$doctor_joined) {
                    $missed_by = 'doctor';
                }
            }
        }

        echo json_encode([
            'success' => true,
            'appointment_id' => $appointment_id,
            'is_doctor' => $is_doctor,
            'doctor_joined' => $doctor_joined,
            'patient_joined' => $patient_joined,
            'both_joined' => $both_joined,
            'is_timed_out' => $is_timed_out,
            'missed_by' => $missed_by,
            'session_created' => $data['session_created'],
            'doctor_joined_at' => $data['doctor_joined_at'],
            'patient_joined_at' => $data['patient_joined_at']
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
