<?php
// Set the content type to JSON so the JavaScript can understand the response.
header('Content-Type: application/json');

// Include necessary files
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../includes/db_connect.php';

// --- Configuration for Time Slots ---
$slot_duration_minutes = 30; // e.g., 30-minute slots

// --- Main Logic ---
$response = ['success' => false, 'timeSlots' => [], 'message' => ''];

// Get parameters from the URL
$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';
$exclude_appointment_id = isset($_GET['exclude_appointment_id']) ? (int)$_GET['exclude_appointment_id'] : 0;

if (empty($doctor_id) || empty($date)) {
    $response['message'] = 'Doctor ID and date are required.';
    echo json_encode($response);
    exit();
}

try {
    // 1. Get doctor's availability settings
    $stmt = $db->prepare("SELECT availability_start, availability_end, available_days FROM doctor_profiles WHERE user_id = ?");
    $stmt->execute([$doctor_id]);
    $doctor_availability = $stmt->fetch();

    if (!$doctor_availability) {
        $response['message'] = 'Doctor availability settings not found.';
        echo json_encode($response);
        exit();
    }

    // 2. Check if doctor is available on this day
    $day_name = strtolower(date('l', strtotime($date))); // Get day name like 'monday'
    $available_days = explode(',', $doctor_availability['available_days']);

    if (!in_array($day_name, $available_days)) {
        $response['message'] = 'Doctor is not available on this day.';
        echo json_encode($response);
        exit();
    }

    // 3. Use doctor's custom working hours
    $working_start_time = substr($doctor_availability['availability_start'], 0, 5); // Format: HH:MM
    $working_end_time = substr($doctor_availability['availability_end'], 0, 5);

    // 4. Get all appointments already booked for this doctor on this date.
    // Check both original time slots and requested time slots
    // Exclude the current appointment if we're rescheduling
    if ($exclude_appointment_id > 0) {
        $stmt = $db->prepare("
            SELECT appointment_time FROM appointments
            WHERE doctor_id = ? AND appointment_date = ?
            AND status IN ('pending', 'confirmed', 'reschedule_requested')
            AND id != ?
            UNION
            SELECT requested_time FROM appointments
            WHERE doctor_id = ? AND requested_date = ?
            AND status = 'reschedule_requested'
            AND requested_time IS NOT NULL
            AND id != ?
        ");
        $stmt->execute([$doctor_id, $date, $exclude_appointment_id, $doctor_id, $date, $exclude_appointment_id]);
    } else {
        $stmt = $db->prepare("
            SELECT appointment_time FROM appointments
            WHERE doctor_id = ? AND appointment_date = ?
            AND status IN ('pending', 'confirmed', 'reschedule_requested')
            UNION
            SELECT requested_time FROM appointments
            WHERE doctor_id = ? AND requested_date = ?
            AND status = 'reschedule_requested'
            AND requested_time IS NOT NULL
        ");
        $stmt->execute([$doctor_id, $date, $doctor_id, $date]);
    }
    $booked_slots_raw = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Format booked slots for easy lookup (e.g., '09:30:00' -> '09:30')
    $booked_slots = array_map(function($time) {
        return substr($time, 0, 5);
    }, $booked_slots_raw);

    // 5. Generate all possible time slots for the day based on doctor's availability.
    $all_slots = [];
    $start = new DateTime($working_start_time);
    $end = new DateTime($working_end_time);
    $interval = new DateInterval('PT' . $slot_duration_minutes . 'M');
    $period = new DatePeriod($start, $interval, $end);

    foreach ($period as $time) {
        $all_slots[] = $time->format('H:i');
    }

    // 6. Filter out the booked slots to find what's available.
    $available_slots = array_diff($all_slots, $booked_slots);

    // 7. If the selected date is today, check if any slots are still available
    if ($date === date('Y-m-d')) {
        $current_time = date('H:i');

        // If current time is past doctor's end time, return no slots
        if ($current_time >= $working_end_time) {
            $response['success'] = true;
            $response['timeSlots'] = [];
            $response['message'] = 'No slots available for today - doctor hours ended.';
            echo json_encode($response);
            exit();
        }

        // Filter out past time slots (including current time)
        $available_slots = array_filter($available_slots, function($slot) use ($current_time) {
            return $slot > $current_time;
        });

        // Additional safety check: ensure slots are within doctor's working hours and after current time
        $available_slots = array_filter($available_slots, function($slot) use ($working_end_time, $current_time) {
            return $slot > $current_time && $slot < $working_end_time;
        });

        // If no slots remain after filtering, return empty
        if (empty($available_slots)) {
            $response['success'] = true;
            $response['timeSlots'] = [];
            $response['message'] = 'No more slots available for today.';
            echo json_encode($response);
            exit();
        }
    }

    $response['success'] = true;
    $response['timeSlots'] = array_values($available_slots); // Re-index array for clean JSON
    $response['message'] = count($available_slots) . ' slots available.';

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
}

// 4. Return the final response as JSON.
echo json_encode($response);
?>
