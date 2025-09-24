<?php
$page_title = "Manage Availability";
require_once '../config.php';

// Check if user is logged in and is doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

require_once '../includes/db_connect.php';

$doctor_id = $_SESSION['user_id'];
$error = '';
$message = '';

// Get current availability settings
try {
    $stmt = $db->prepare("
        SELECT availability_start, availability_end, available_days
        FROM doctor_profiles
        WHERE user_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $availability = $stmt->fetch();

    // If no profile exists, create one with defaults
    if (!$availability) {
        $stmt = $db->prepare("
            INSERT INTO doctor_profiles (user_id, availability_start, availability_end, available_days)
            VALUES (?, '09:00:00', '18:00:00', 'monday,tuesday,wednesday,thursday,friday,saturday')
        ");
        $stmt->execute([$doctor_id]);

        // Fetch the newly created record
        $stmt = $db->prepare("
            SELECT availability_start, availability_end, available_days
            FROM doctor_profiles
            WHERE user_id = ?
        ");
        $stmt->execute([$doctor_id]);
        $availability = $stmt->fetch();
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle availability update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $availability_start = $_POST['availability_start'];
    $availability_end = $_POST['availability_end'];
    $available_days = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';

    // Validation
    if (empty($availability_start) || empty($availability_end)) {
        $error = 'Start time and end time are required.';
    } elseif ($availability_start >= $availability_end) {
        $error = 'End time must be after start time.';
    } elseif (empty($available_days)) {
        $error = 'Please select at least one working day.';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE doctor_profiles
                SET availability_start = ?, availability_end = ?, available_days = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$availability_start, $availability_end, $available_days, $doctor_id]);

            $message = 'Availability updated successfully!';

            // Refresh availability data
            $stmt = $db->prepare("
                SELECT availability_start, availability_end, available_days
                FROM doctor_profiles
                WHERE user_id = ?
            ");
            $stmt->execute([$doctor_id]);
            $availability = $stmt->fetch();

        } catch (PDOException $e) {
            $error = 'Error updating availability: ' . $e->getMessage();
        }
    }
}

// Convert available_days string to array for checkboxes
$selected_days = $availability['available_days'] ? explode(',', $availability['available_days']) : [];

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-calendar-alt me-2"></i>Manage Availability</h2>
    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Set Your Working Hours & Days</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <!-- Working Hours -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="availability_start" class="form-label">Start Time *</label>
                                <input type="time" class="form-control" id="availability_start" name="availability_start"
                                       value="<?php echo htmlspecialchars(substr($availability['availability_start'] ?? '09:00:00', 0, 5)); ?>" required>
                                <small class="text-muted">When do you start seeing patients?</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="availability_end" class="form-label">End Time *</label>
                                <input type="time" class="form-control" id="availability_end" name="availability_end"
                                       value="<?php echo htmlspecialchars(substr($availability['availability_end'] ?? '18:00:00', 0, 5)); ?>" required>
                                <small class="text-muted">When do you stop seeing patients?</small>
                            </div>
                        </div>
                    </div>

                    <!-- Working Days -->
                    <div class="mb-4">
                        <label class="form-label">Available Days *</label>
                        <div class="row">
                            <?php
                            $days = [
                                'monday' => 'Monday',
                                'tuesday' => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday' => 'Thursday',
                                'friday' => 'Friday',
                                'saturday' => 'Saturday',
                                'sunday' => 'Sunday'
                            ];

                            foreach ($days as $day_key => $day_name): ?>
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="day_<?php echo $day_key; ?>"
                                               name="available_days[]" value="<?php echo $day_key; ?>"
                                               <?php echo in_array($day_key, $selected_days) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="day_<?php echo $day_key; ?>">
                                            <?php echo $day_name; ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Select the days when you're available for appointments</small>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Update Availability
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Current Settings</h6>
            </div>
            <div class="card-body">
                <h6>Working Hours</h6>
                <p class="mb-2">
                    <strong>Start:</strong> <?php echo date('g:i A', strtotime($availability['availability_start'] ?? '09:00:00')); ?><br>
                    <strong>End:</strong> <?php echo date('g:i A', strtotime($availability['availability_end'] ?? '18:00:00')); ?>
                </p>

                <h6>Working Days</h6>
                <p class="mb-3">
                    <?php
                    if (!empty($selected_days)) {
                        $day_names = array_map(function($day) use ($days) {
                            return $days[$day] ?? ucfirst($day);
                        }, $selected_days);
                        echo implode(', ', $day_names);
                    } else {
                        echo 'No days selected';
                    }
                    ?>
                </p>

                <div class="alert alert-info">
                    <small>
                        <i class="fas fa-lightbulb me-1"></i>
                        <strong>Tip:</strong> Patients will only be able to book appointments during your available hours and days.
                        Appointments are created in 30-minute slots.
                    </small>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="my_appointments.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-calendar me-2"></i>View My Appointments
                    </a>
                    <a href="profile.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-user-edit me-2"></i>Edit Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const startTime = document.getElementById('availability_start');
    const endTime = document.getElementById('availability_end');

    function validateTimes() {
        if (startTime.value && endTime.value) {
            if (startTime.value >= endTime.value) {
                endTime.setCustomValidity('End time must be after start time');
            } else {
                endTime.setCustomValidity('');
            }
        }
    }

    startTime.addEventListener('change', validateTimes);
    endTime.addEventListener('change', validateTimes);

    // Validate at least one day is selected
    form.addEventListener('submit', function(e) {
        const checkedDays = document.querySelectorAll('input[name="available_days[]"]:checked');
        if (checkedDays.length === 0) {
            e.preventDefault();
            alert('Please select at least one working day.');
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>