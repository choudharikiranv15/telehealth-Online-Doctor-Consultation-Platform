<?php
$page_title = "Reschedule Appointment";
require_once dirname(__FILE__) . '/../config.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once dirname(__FILE__) . '/../includes/db_connect.php';

$patient_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$appointment_id) {
    header("Location: my_appointments.php");
    exit();
}

// Get appointment details
try {
    $stmt = $db->prepare("
        SELECT a.*, u.first_name, u.last_name, s.name as specialization, dp.consultation_fee
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE a.id = ? AND a.patient_id = ?
    ");
    $stmt->execute([$appointment_id, $patient_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        $_SESSION['error'] = 'Appointment not found.';
        header("Location: my_appointments.php");
        exit();
    }

    // Check if appointment can be rescheduled - ONLY pending and confirmed allowed
    if (!in_array($appointment['status'], ['pending', 'confirmed'])) {
        if ($appointment['status'] === 'completed') {
            $_SESSION['error'] = 'Cannot reschedule completed appointments. The consultation has already taken place.';
        } elseif ($appointment['status'] === 'cancelled') {
            $_SESSION['error'] = 'Cannot reschedule cancelled appointments.';
        } elseif ($appointment['status'] === 'rejected') {
            $_SESSION['error'] = 'Cannot reschedule rejected appointments.';
        } elseif ($appointment['status'] === 'reschedule_requested') {
            $_SESSION['error'] = 'A reschedule request is already pending for this appointment.';
        } else {
            $_SESSION['error'] = 'This appointment cannot be rescheduled.';
        }
        header("Location: my_appointments.php");
        exit();
    }

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle reschedule form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CRITICAL: Double-check status before processing (prevent direct POST)
    if (!in_array($appointment['status'], ['pending', 'confirmed'])) {
        $_SESSION['error'] = 'This appointment cannot be rescheduled. Status: ' . $appointment['status'];
        header("Location: my_appointments.php");
        exit();
    }

    $new_date = $_POST['appointment_date'];
    $new_time = $_POST['appointment_time'];
    $reschedule_reason = trim($_POST['reschedule_reason']);

    // Validation
    if (empty($new_date) || empty($new_time) || empty($reschedule_reason)) {
        $error = 'Please fill in all required fields.';
    } elseif (strtotime($new_date) < strtotime(date('Y-m-d'))) {
        $error = 'Cannot reschedule appointments for past dates.';
    } elseif ($new_date === date('Y-m-d') && $new_time <= date('H:i')) {
        $error = 'Cannot reschedule appointments for past times on today\'s date.';
    } else {
        try {
            // Check if new time slot is available
            // Check both original time slots and requested time slots
            $stmt = $db->prepare("
                SELECT id FROM appointments
                WHERE doctor_id = ?
                AND (
                    (appointment_date = ? AND appointment_time = ? AND status IN ('pending', 'confirmed', 'reschedule_requested'))
                    OR (requested_date = ? AND requested_time = ? AND status = 'reschedule_requested')
                )
                AND id != ?
            ");
            $stmt->execute([$appointment['doctor_id'], $new_date, $new_time, $new_date, $new_time, $appointment_id]);

            if ($stmt->fetch()) {
                $error = 'This time slot is not available. Please select another time.';
            } else {
                // Update appointment with reschedule request
                $stmt = $db->prepare("
                    UPDATE appointments
                    SET requested_date = ?,
                        requested_time = ?,
                        original_date = appointment_date,
                        original_time = appointment_time,
                        reschedule_reason = ?,
                        reschedule_requested_at = NOW(),
                        status = 'reschedule_requested'
                    WHERE id = ? AND patient_id = ?
                ");

                $result = $stmt->execute([
                    $new_date,
                    $new_time,
                    $reschedule_reason,
                    $appointment_id,
                    $patient_id
                ]);

                if ($result) {
                    $_SESSION['success'] = 'Reschedule request sent successfully! Waiting for doctor approval.';
                    header("Location: my_appointments.php");
                    exit();
                } else {
                    $error = 'Failed to submit reschedule request. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-alt me-2"></i>Reschedule Appointment</h2>
        <a href="my_appointments.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Appointments
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Current Appointment Info -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Current Appointment</h5>
                </div>
                <div class="card-body">
                    <p><strong>Doctor:</strong><br>Dr. <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></p>
                    <p><strong>Specialization:</strong><br><?php echo htmlspecialchars($appointment['specialization'] ?? 'General Medicine'); ?></p>
                    <p><strong>Current Date:</strong><br><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></p>
                    <p><strong>Current Time:</strong><br><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></p>
                    <p><strong>Consultation Fee:</strong><br>₹<?php echo number_format($appointment['consultation_fee'], 2); ?></p>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>No additional payment required for rescheduling</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reschedule Form -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Select New Date & Time</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="rescheduleForm">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Your reschedule request will be sent to the doctor for approval. You will be notified once the doctor responds.
                        </div>

                        <div class="mb-3">
                            <label for="appointment_date" class="form-label">New Appointment Date *</label>
                            <input type="date" class="form-control" id="appointment_date" name="appointment_date"
                                   min="<?php echo date('Y-m-d'); ?>" required>
                            <small class="text-muted">Select your preferred date</small>
                        </div>

                        <div class="mb-3">
                            <label for="appointment_time" class="form-label">New Appointment Time *</label>
                            <select class="form-select" id="appointment_time" name="appointment_time" required>
                                <option value="">Select date first...</option>
                            </select>
                            <small class="text-muted" id="slotsMessage">Select your preferred time slot</small>
                        </div>

                        <div class="mb-3">
                            <label for="reschedule_reason" class="form-label">Reason for Rescheduling *</label>
                            <textarea class="form-control" id="reschedule_reason" name="reschedule_reason"
                                      rows="4" required placeholder="Please explain why you need to reschedule this appointment..."></textarea>
                            <small class="text-muted">This will help the doctor understand your situation</small>
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes:</h6>
                            <ul class="mb-0">
                                <li>Your current appointment will remain active until the doctor approves</li>
                                <li>If approved, your appointment will be automatically updated</li>
                                <li>If rejected, you'll keep your original appointment time</li>
                                <li>No additional payment is required for rescheduling</li>
                            </ul>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Submit Reschedule Request
                            </button>
                            <a href="my_appointments.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load available time slots when date changes
function loadTimeSlots() {
    const doctorId = <?php echo $appointment['doctor_id']; ?>;
    const appointmentDate = document.getElementById('appointment_date').value;
    const timeSelect = document.getElementById('appointment_time');
    const slotsMessage = document.getElementById('slotsMessage');

    if (!appointmentDate) {
        timeSelect.innerHTML = '<option value="">Select date first...</option>';
        slotsMessage.textContent = 'Select your preferred time slot';
        return;
    }

    timeSelect.innerHTML = '<option value="">Loading available slots...</option>';
    slotsMessage.textContent = 'Loading...';

    const appointmentId = <?php echo $appointment_id; ?>;
    fetch(`get_time_slots.php?doctor_id=${doctorId}&date=${appointmentDate}&exclude_appointment_id=${appointmentId}`)
        .then(response => response.json())
        .then(data => {
            timeSelect.innerHTML = '<option value="">Select a time</option>';

            if (data.success && data.timeSlots.length > 0) {
                data.timeSlots.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot;
                    // Convert 24h format to 12h format for display
                    const [hours, minutes] = slot.split(':');
                    const time = new Date();
                    time.setHours(parseInt(hours), parseInt(minutes));
                    option.textContent = time.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    });
                    timeSelect.appendChild(option);
                });
                slotsMessage.textContent = `${data.timeSlots.length} slots available`;
                slotsMessage.className = 'text-muted text-success';
            } else {
                timeSelect.innerHTML = '<option value="">No slots available</option>';
                slotsMessage.textContent = data.message || 'No slots available for this date';
                slotsMessage.className = 'text-muted text-danger';
            }
        })
        .catch(error => {
            timeSelect.innerHTML = '<option value="">Error loading slots</option>';
            slotsMessage.textContent = 'Error loading time slots. Please try again.';
            slotsMessage.className = 'text-muted text-danger';
            console.error('Error fetching time slots:', error);
        });
}

// Date change event listener
document.getElementById('appointment_date').addEventListener('change', loadTimeSlots);

// Date and time validation
document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
    const selectedDate = document.getElementById('appointment_date').value;
    const selectedTime = document.getElementById('appointment_time').value;
    const reason = document.getElementById('reschedule_reason').value.trim();

    if (!selectedDate || !selectedTime || !reason) {
        e.preventDefault();
        alert('Please fill in all required fields');
        return false;
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const selected = new Date(selectedDate);

    if (selected < today) {
        e.preventDefault();
        alert('Cannot select past dates');
        return false;
    }

    // Check if same as current appointment
    const currentDate = '<?php echo $appointment['appointment_date']; ?>';
    const currentTime = '<?php echo substr($appointment['appointment_time'], 0, 5); ?>';

    if (selectedDate === currentDate && selectedTime === currentTime) {
        e.preventDefault();
        alert('Please select a different date or time from your current appointment');
        return false;
    }

    return confirm('Submit reschedule request to the doctor?');
});
</script>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
