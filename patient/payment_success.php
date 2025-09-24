<?php
$page_title = "Payment Successful";
require_once dirname(__FILE__) . '/../config.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once dirname(__FILE__) . '/../includes/db_connect.php';

$patient_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$transaction_id = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : '';

if (!$appointment_id || !$transaction_id) {
    header("Location: " . getPageUrl('patient/'));
    exit();
}

// Get appointment and payment details
try {
    $stmt = $db->prepare("
        SELECT a.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name,
               dp.specialization, p.transaction_id, p.payment_method, p.created_at as payment_date
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN payments p ON a.id = p.appointment_id
        WHERE a.id = ? AND a.patient_id = ? AND p.transaction_id = ?
    ");
    $stmt->execute([$appointment_id, $patient_id, $transaction_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        header("Location: " . getPageUrl('patient/'));
        exit();
    }
} catch (PDOException $e) {
    header("Location: " . getPageUrl('patient/'));
    exit();
}

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    </div>

                    <h2 class="text-success mb-3">Payment Successful!</h2>
                    <p class="lead mb-4">Your payment has been processed successfully. Your appointment is now pending doctor approval.</p>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Next Steps:</strong> The doctor will review your appointment request and either approve or provide feedback. You will be notified of the status.
                    </div>

                    <!-- Payment Details -->
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <h5 class="card-title text-primary">Payment Details</h5>
                            <div class="row">
                                <div class="col-sm-6 text-start">
                                    <strong>Transaction ID:</strong>
                                </div>
                                <div class="col-sm-6 text-end">
                                    <code><?php echo htmlspecialchars($appointment['transaction_id']); ?></code>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6 text-start">
                                    <strong>Amount Paid:</strong>
                                </div>
                                <div class="col-sm-6 text-end">
                                    <span class="text-success">₹<?php echo number_format($appointment['payment_amount'], 2); ?></span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6 text-start">
                                    <strong>Payment Method:</strong>
                                </div>
                                <div class="col-sm-6 text-end">
                                    <?php
                                    $method_icons = [
                                        'upi' => '<i class="fab fa-google-pay text-primary"></i> UPI',
                                        'card' => '<i class="fas fa-credit-card text-success"></i> Card',
                                        'netbanking' => '<i class="fas fa-university text-warning"></i> Net Banking'
                                    ];
                                    echo $method_icons[$appointment['payment_method']] ?? ucfirst($appointment['payment_method']);
                                    ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6 text-start">
                                    <strong>Payment Date:</strong>
                                </div>
                                <div class="col-sm-6 text-end">
                                    <?php echo date('M d, Y h:i A', strtotime($appointment['payment_date'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Appointment Details -->
                    <div class="card bg-primary text-white mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Appointment Confirmed</h5>
                            <div class="row">
                                <div class="col-md-6 text-start">
                                    <p class="mb-2">
                                        <i class="fas fa-user-md me-2"></i>
                                        <strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-stethoscope me-2"></i>
                                        <strong>Specialization:</strong> <?php echo htmlspecialchars($appointment['specialization'] ?? 'General Medicine'); ?>
                                    </p>
                                </div>
                                <div class="col-md-6 text-start">
                                    <p class="mb-2">
                                        <i class="fas fa-calendar me-2"></i>
                                        <strong>Date:</strong> <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-clock me-2"></i>
                                        <strong>Time:</strong> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div class="alert alert-info text-start">
                        <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>What's Next?</h6>
                        <ul class="mb-0">
                            <li>You will receive a confirmation email with appointment details</li>
                            <li>The doctor will review your symptoms before the consultation</li>
                            <li>Join the video call 5 minutes before your appointment time</li>
                            <li>You can reschedule or cancel up to 2 hours before the appointment</li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2 d-md-block">
                        <a href="<?php echo getPageUrl('patient/my_appointments.php'); ?>" class="btn btn-primary btn-lg">
                            <i class="fas fa-calendar-check me-2"></i>View My Appointments
                        </a>
                        <a href="<?php echo getPageUrl('patient/'); ?>" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-home me-2"></i>Go to Dashboard
                        </a>
                    </div>

                    <div class="mt-4">
                        <small class="text-muted">
                            <i class="fas fa-print me-2"></i>
                            <a href="javascript:window.print()" class="text-decoration-none">Print this confirmation</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .alert {
        display: none !important;
    }

    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }

    .bg-primary {
        background-color: #000 !important;
        color: #fff !important;
    }
}

.text-success {
    color: #28a745 !important;
}

.card {
    border: none;
    border-radius: 15px;
}

.bg-light {
    background-color: #f8f9fa !important;
}

.bg-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}
</style>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>