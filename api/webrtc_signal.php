<?php
header('Content-Type: application/json');

try {
    require_once dirname(__FILE__) . '/../config.php';
    require_once dirname(__FILE__) . '/../includes/db_connect.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Create webrtc_signals table if it doesn't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS webrtc_signals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL,
            sender_id INT NOT NULL,
            receiver_id INT,
            type VARCHAR(50) NOT NULL,
            data TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed BOOLEAN DEFAULT FALSE,
            INDEX idx_appointment (appointment_id),
            INDEX idx_receiver (receiver_id),
            INDEX idx_processed (processed)
        )
    ");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Send signal
        $input = json_decode(file_get_contents('php://input'), true);

        $appointment_id = (int)$input['appointment_id'];
        $type = $input['type'];
        $data = json_encode($input['data']);
        $sender_id = (int)$input['sender_id'];

        // Get the other participant
        $stmt = $db->prepare("
            SELECT doctor_id, patient_id
            FROM appointments
            WHERE id = ? AND (doctor_id = ? OR patient_id = ?)
        ");
        $stmt->execute([$appointment_id, $user_id, $user_id]);
        $appointment = $stmt->fetch();

        if (!$appointment) {
            throw new Exception('Appointment not found');
        }

        $receiver_id = ($appointment['doctor_id'] == $user_id) ?
            $appointment['patient_id'] : $appointment['doctor_id'];

        // Store signal
        $stmt = $db->prepare("
            INSERT INTO webrtc_signals (appointment_id, sender_id, receiver_id, type, data)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$appointment_id, $sender_id, $receiver_id, $type, $data]);

        echo json_encode(['success' => true]);

    } else {
        // Get signals
        $appointment_id = (int)$_GET['appointment_id'];
        $user_id = (int)$_GET['user_id'];

        // Get unprocessed signals for this user
        $stmt = $db->prepare("
            SELECT id, type, data
            FROM webrtc_signals
            WHERE appointment_id = ? AND receiver_id = ? AND processed = FALSE
            ORDER BY created_at ASC
        ");
        $stmt->execute([$appointment_id, $user_id]);
        $signals = $stmt->fetchAll();

        // Mark as processed
        if (!empty($signals)) {
            $signal_ids = array_column($signals, 'id');
            $placeholders = str_repeat('?,', count($signal_ids) - 1) . '?';
            $stmt = $db->prepare("UPDATE webrtc_signals SET processed = TRUE WHERE id IN ($placeholders)");
            $stmt->execute($signal_ids);
        }

        // Parse data
        $result = [];
        foreach ($signals as $signal) {
            $result[] = [
                'type' => $signal['type'],
                'data' => json_decode($signal['data'], true)
            ];
        }

        echo json_encode(['success' => true, 'data' => $result]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>