<?php
header('Content-Type: application/json');

require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../includes/db_connect.php';

$response = ['success' => false, 'min_date' => '', 'message' => ''];

$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

if (empty($doctor_id)) {
    $response['message'] = 'Doctor ID is required.';
    echo json_encode($response);
    exit();
}

try {
    // Get doctor's availability settings
    $stmt = $db->prepare("SELECT availability_end FROM doctor_profiles WHERE user_id = ?");
    $stmt->execute([$doctor_id]);
    $doctor_availability = $stmt->fetch();

    if (!$doctor_availability) {
        $response['message'] = 'Doctor availability settings not found.';
        echo json_encode($response);
        exit();
    }

    $doctor_end_time = substr($doctor_availability['availability_end'], 0, 5); // Format: HH:MM
    $current_time = date('H:i');
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    // If current time is past doctor's end time, start from tomorrow
    if ($current_time >= $doctor_end_time) {
        $response['min_date'] = $tomorrow;
        $response['message'] = 'Doctor hours ended for today. Starting from tomorrow.';
    } else {
        $response['min_date'] = $today;
        $response['message'] = 'Doctor still available today.';
    }

    $response['success'] = true;

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
}

echo json_encode($response);
?>