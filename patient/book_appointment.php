<?php
$page_title = "Book Appointment";
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

// Get doctor ID from URL if provided
$selected_doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = (int)$_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 30; // Default duration
    $symptoms = trim($_POST['symptoms']);
    $notes = trim($_POST['notes']);
    
    // DEBUG: Log what we received
    error_log("APPOINTMENT BOOKING DEBUG - Received doctor_id: $doctor_id from patient: $patient_id for date: $appointment_date at time: $appointment_time");

    // Validation
    if (empty($doctor_id) || empty($appointment_date) || empty($appointment_time) || empty($symptoms)) {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
        $error = 'Invalid date format. Please use YYYY-MM-DD format.';
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $appointment_time)) {
        $error = 'Invalid time format. Please use HH:MM format.';
    } elseif (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = 'Cannot book appointments for past dates.';
    } elseif ($appointment_date === date('Y-m-d') && $appointment_time <= date('H:i')) {
        $error = 'Cannot book appointments for past times on today\'s date.';
    } else {
        try {
            // Check if time slot is still available (using correct status values)
            $stmt = $db->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('pending', 'confirmed')");
            $stmt->execute([$doctor_id, $appointment_date, $appointment_time]);
            
            if ($stmt->fetch()) {
                $error = 'This time slot is no longer available. Please select another time.';
            } else {
                // Get doctor's consultation fee and verify doctor details
                $stmt = $db->prepare("
                    SELECT dp.consultation_fee, u.first_name, u.last_name 
                    FROM doctor_profiles dp 
                    JOIN users u ON dp.user_id = u.id 
                    WHERE dp.user_id = ?
                ");
                $stmt->execute([$doctor_id]);
                $doctor_profile = $stmt->fetch();
                $consultation_fee = $doctor_profile ? $doctor_profile['consultation_fee'] : 0.00;
                
                // DEBUG: Log which doctor we're booking for
                if ($doctor_profile) {
                    error_log("APPOINTMENT BOOKING DEBUG - Booking for Doctor: {$doctor_profile['first_name']} {$doctor_profile['last_name']} (ID: $doctor_id)");
                } else {
                    error_log("APPOINTMENT BOOKING DEBUG - ERROR: No doctor found for ID: $doctor_id");
                }
                
                // Insert appointment with payment pending status
                $stmt = $db->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, symptoms, notes, payment_amount, payment_status) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, 'pending')");
                $result = $stmt->execute([$patient_id, $doctor_id, $appointment_date, $appointment_time, $symptoms, $notes, $consultation_fee]);

                if ($result && $stmt->rowCount() > 0) {
                    $appointment_id = $db->lastInsertId();

                    // Store appointment data in session for payment page
                    $_SESSION['pending_appointment'] = [
                        'id' => $appointment_id,
                        'doctor_id' => $doctor_id,
                        'doctor_first_name' => $doctor_profile['first_name'],
                        'doctor_last_name' => $doctor_profile['last_name'],
                        'appointment_date' => $appointment_date,
                        'appointment_time' => $appointment_time,
                        'symptoms' => $symptoms,
                        'notes' => $notes,
                        'payment_amount' => $consultation_fee,
                        'specialization' => null // Will be fetched in payment page
                    ];

                    // Debug: Log successful booking
                    error_log("Appointment created successfully: ID={$appointment_id}, redirecting to payment");

                    // Redirect to payment page
                    header("Location: " . getPageUrl('patient/payment.php?appointment_id=' . $appointment_id));
                    exit();
                } else {
                    $error = "Failed to create appointment. Please try again.";
                    error_log("Appointment booking failed: No rows affected. Patient={$patient_id}, Doctor={$doctor_id}, Date={$appointment_date}, Time={$appointment_time}");
                }

            }
        } catch (PDOException $e) {
            // Log the error for debugging
            error_log("Appointment booking error: " . $e->getMessage() . " | Code: " . $e->getCode());
            
            // Check if it's a duplicate entry error (UNIQUE constraint violation)
            if ($e->getCode() == 23000 && strpos($e->getMessage(), 'unique_appointment') !== false) {
                $error = 'This time slot is already booked by another patient. Please select a different time slot.';
            } else {
                // This will now show the REAL database error for debugging.
                $error = 'Database Error: ' . $e->getMessage();
            }
        }
    }
}


// --- START: CORRECTED DOCTOR QUERY WITH SPECIALIZATIONS ---
// This query includes specializations from the new database structure
$sql = "SELECT u.id as id, u.first_name, u.last_name, u.email, u.phone, u.city, u.state, u.country, u.status, u.role,
               dp.specialization_id, dp.experience_years, dp.consultation_fee, dp.license_number, dp.rating, dp.languages,
               s.name as specialization, s.icon as specialization_icon,
               COALESCE(ac.completed_appointments, 0) as total_consultations
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        LEFT JOIN (
            SELECT doctor_id, COUNT(*) as completed_appointments
            FROM appointments
            WHERE status = 'completed'
            GROUP BY doctor_id
        ) AS ac ON u.id = ac.doctor_id
        WHERE u.role = 'doctor' AND u.status = 'active'
        ORDER BY dp.rating DESC, COALESCE(ac.completed_appointments, 0) DESC";

try {
    $doctors = $db->query($sql)->fetchAll();
    
    // Debug: Log doctor IDs being loaded for dropdown
    error_log("=== DOCTOR DROPDOWN DEBUG ===");
    foreach ($doctors as $doctor) {
        error_log("Doctor ID: {$doctor['id']}, Name: {$doctor['first_name']} {$doctor['last_name']}, Specialization: {$doctor['specialization']}");
    }
    error_log("============================");
    
} catch (PDOException $e) {
    die("Fatal Error: Could not fetch doctor list. " . $e->getMessage());
}
// --- END: CORRECTED DOCTOR QUERY ---


// Get selected doctor details
$selected_doctor = null;
if ($selected_doctor_id) {
    $stmt = $db->prepare("SELECT u.*, dp.* FROM users u LEFT JOIN doctor_profiles dp ON u.id = dp.user_id WHERE u.id = ? AND u.role = 'doctor'");
    $stmt->execute([$selected_doctor_id]);
    $selected_doctor = $stmt->fetch();
}

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-calendar-plus me-2"></i>Book Appointment</h2>
    <a href="<?php echo getPageUrl('patient/'); ?>" class="btn btn-outline-primary">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($_POST['doctor_id'])): ?>
    <div class="alert alert-info">
        <strong>DEBUG - Form Submission Received:</strong><br>
        Doctor ID: <?php echo htmlspecialchars($_POST['doctor_id']); ?><br>
        Date: <?php echo htmlspecialchars($_POST['appointment_date']); ?><br>
        Time: <?php echo htmlspecialchars($_POST['appointment_time']); ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Appointment Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="appointmentForm" onsubmit="debugFormSubmission()">
                    <!-- Doctor Selection -->
                    <div class="mb-3">
                        <label for="doctor_id" class="form-label">Select Doctor *</label>
                        <select class="form-select" id="doctor_id" name="doctor_id" required onchange="updateDoctorInfo()">
                            <option value="">Choose a doctor...</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" 
                                        data-fee="<?php echo htmlspecialchars($doctor['consultation_fee'] ?? '500'); ?>"
                                        data-specialization="<?php echo htmlspecialchars($doctor['specialization'] ?? 'General Medicine'); ?>"
                                        <?php echo $selected_doctor_id == $doctor['id'] ? 'selected' : ''; ?>>
                                    Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                    - <?php echo htmlspecialchars($doctor['specialization'] ?? 'General Medicine'); ?>
                                    (₹<?php echo number_format((float)($doctor['consultation_fee'] ?? 500), 0); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Doctor Info Card (Populated by JS) -->
                    <div id="doctorInfo" class="mb-3" style="display: none;">
                        <!-- JS will populate this -->
                    </div>

                    <!-- Date and Time Selection -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="appointment_date" class="form-label">Appointment Date *</label>
                                <input type="date" class="form-control" id="appointment_date" name="appointment_date"
                                       min="" id="min_date" required onchange="loadTimeSlots()">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="appointment_time" class="form-label">Appointment Time *</label>
                                <select class="form-select" id="appointment_time" name="appointment_time" required>
                                    <option value="">Select doctor and date first...</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Symptoms -->
                    <div class="mb-3">
                        <label for="symptoms" class="form-label">Symptoms *</label>
                        <textarea class="form-control" id="symptoms" name="symptoms" rows="3" 
                                  placeholder="Please describe your symptoms..." required><?php echo isset($_POST['symptoms']) ? htmlspecialchars($_POST['symptoms']) : ''; ?></textarea>
                    </div>

                    <!-- Additional Notes -->
                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" 
                                  placeholder="Any additional information..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                            <i class="fas fa-calendar-check me-2"></i>Book Appointment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Available Doctors List -->
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-user-md me-2"></i>Available Doctors</h6>
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-item mb-2 p-2 border rounded <?php echo $selected_doctor_id == $doctor['id'] ? 'border-primary bg-light' : ''; ?>">
                        <div class="d-flex align-items-center">
                            <!-- Doctor Avatar -->
                            <div class="me-3">
                                <?php if (!empty($doctor['profile_picture']) && file_exists('../assets/images/' . $doctor['profile_picture'])): ?>
                                    <div class="doctor-avatar">
                                        <img src="<?php echo getPageUrl('assets/images/' . htmlspecialchars($doctor['profile_picture'])); ?>"
                                             alt="Dr. <?php echo htmlspecialchars($doctor['first_name']); ?>"
                                             class="rounded-circle avatar-md" style="object-fit: cover;">
                                    </div>
                                <?php else: ?>
                                    <div class="bg-primary rounded-circle doctor-default-avatar avatar-md">
                                        <i class="fas fa-user-md text-white"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h6>
                                <p class="text-primary mb-1 small"><?php echo htmlspecialchars($doctor['specialization'] ?? 'General Medicine'); ?></p>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="selectDoctor(<?php echo $doctor['id']; ?>)">
                                Select
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
function selectDoctor(doctorId) {
    console.log('Selecting doctor with ID:', doctorId);
    document.getElementById('doctor_id').value = doctorId;
    console.log('Doctor select value set to:', document.getElementById('doctor_id').value);
    // Trigger the change event to update everything
    document.getElementById('doctor_id').dispatchEvent(new Event('change'));
    
    // Show which doctor was selected
    const selectedOption = document.querySelector('#doctor_id option[value="' + doctorId + '"]');
    if (selectedOption) {
        console.log('Selected doctor option text:', selectedOption.textContent);
    }
}

function loadTimeSlots() {
    const doctorId = document.getElementById('doctor_id').value;
    const appointmentDate = document.getElementById('appointment_date').value;
    const timeSelect = document.getElementById('appointment_time');
    
    if (!doctorId || !appointmentDate) {
        timeSelect.innerHTML = '<option value="">Select doctor and date</option>';
        return;
    }
    
    timeSelect.innerHTML = '<option value="">Loading...</option>';
    
    // NOTE: You will need to create this get_time_slots.php file for this to work.
    fetch(`get_time_slots.php?doctor_id=${doctorId}&date=${appointmentDate}`)
        .then(response => response.json())
        .then(data => {
            timeSelect.innerHTML = '<option value="">Select a time</option>';
            if (data.success && data.timeSlots.length > 0) {
                data.timeSlots.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot;
                    option.textContent = slot; // Assuming the API returns simple time strings like "09:00:00"
                    timeSelect.appendChild(option);
                });
            } else {
                timeSelect.innerHTML = '<option value="">No slots available</option>';
            }
        })
        .catch(error => {
            timeSelect.innerHTML = '<option value="">Error loading slots</option>';
            console.error('Error fetching time slots:', error);
        });
}

function updateDoctorInfo() {
    const doctorSelect = document.getElementById('doctor_id');
    const selectedOption = doctorSelect.options[doctorSelect.selectedIndex];
    const doctorInfoDiv = document.getElementById('doctorInfo');

    if (selectedOption.value) {
        const fee = parseFloat(selectedOption.dataset.fee).toFixed(2);
        const specialization = selectedOption.dataset.specialization;

        doctorInfoDiv.innerHTML = `
            <div class="card border-primary">
                <div class="card-body">
                    <h6 class="card-title text-primary">Doctor Information</h6>
                    <p class="mb-1"><strong>Specialization:</strong> ${specialization}</p>
                    <h5 class="text-success mt-2">Fee: ₹${fee}</h5>
                </div>
            </div>`;
        doctorInfoDiv.style.display = 'block';

        // Update minimum date based on doctor's availability
        updateMinimumDate(selectedOption.value);

        loadTimeSlots(); // Also load time slots when doctor info is updated
    } else {
        doctorInfoDiv.style.display = 'none';
        // Reset to default minimum date
        document.getElementById('appointment_date').min = new Date().toISOString().split('T')[0];
    }
}

function updateMinimumDate(doctorId) {
    // Check doctor's availability to set minimum date
    fetch(`check_doctor_availability.php?doctor_id=${doctorId}`)
        .then(response => response.json())
        .then(data => {
            const appointmentDateInput = document.getElementById('appointment_date');
            if (data.success) {
                appointmentDateInput.min = data.min_date;
            } else {
                // Fallback to tomorrow if can't determine
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                appointmentDateInput.min = tomorrow.toISOString().split('T')[0];
            }
        })
        .catch(error => {
            console.error('Error checking doctor availability:', error);
            // Fallback to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('appointment_date').min = tomorrow.toISOString().split('T')[0];
        });
}

function debugFormSubmission() {
    const doctorId = document.getElementById('doctor_id').value;
    const doctorSelect = document.getElementById('doctor_id');
    const selectedOption = doctorSelect.options[doctorSelect.selectedIndex];
    
    console.log('=== FORM SUBMISSION DEBUG ===');
    console.log('Doctor ID being submitted:', doctorId);
    console.log('Selected doctor option text:', selectedOption ? selectedOption.textContent : 'None');
    console.log('Date:', document.getElementById('appointment_date').value);
    console.log('Time:', document.getElementById('appointment_time').value);
    console.log('=============================');
    
    return true; // Allow form to submit
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('doctor_id').value) {
        updateDoctorInfo();
    }
    document.getElementById('doctor_id').addEventListener('change', updateDoctorInfo);
});
</script>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
