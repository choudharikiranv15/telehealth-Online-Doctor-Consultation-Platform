<?php
$page_title = "Payment Gateway";
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

// Get appointment data from session or URL parameters
$appointment_data = isset($_SESSION['pending_appointment']) ? $_SESSION['pending_appointment'] : null;
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;

// If appointment_id is provided, get appointment details
if ($appointment_id > 0 && !$appointment_data) {
    try {
        $stmt = $db->prepare("
            SELECT a.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name,
                   s.name as specialization, dp.consultation_fee
            FROM appointments a
            JOIN users u ON a.doctor_id = u.id
            LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
            LEFT JOIN specializations s ON dp.specialization_id = s.id
            WHERE a.id = ? AND a.patient_id = ? AND a.payment_status = 'pending'
        ");
        $stmt->execute([$appointment_id, $patient_id]);
        $appointment_data = $stmt->fetch();
    } catch (PDOException $e) {
        $error = 'Error fetching appointment details: ' . $e->getMessage();
    }
}

if (!$appointment_data) {
    header("Location: " . getPageUrl('patient/book_appointment.php'));
    exit();
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'];
    $amount = floatval($_POST['amount']);

    // Validate payment amount
    if ($amount != $appointment_data['payment_amount']) {
        $error = 'Invalid payment amount.';
    } else {
        // Simulate payment processing based on method
        $payment_success = true; // In real implementation, this would be actual payment gateway response

        if ($payment_success) {
            try {
                $db->beginTransaction();

                // Update appointment payment status (keep appointment status as pending for doctor approval)
                $stmt = $db->prepare("
                    UPDATE appointments
                    SET payment_status = 'paid', payment_method = ?
                    WHERE id = ?
                ");
                $stmt->execute([$payment_method, $appointment_data['id']]);

                // Insert payment record
                $transaction_id = 'TXN' . time() . rand(1000, 9999);
                $stmt = $db->prepare("
                    INSERT INTO payments (appointment_id, patient_id, amount, payment_method, transaction_id, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'completed', NOW())
                ");
                $stmt->execute([
                    $appointment_data['id'],
                    $patient_id,
                    $amount,
                    $payment_method,
                    $transaction_id
                ]);

                $db->commit();

                // Clear pending appointment from session
                unset($_SESSION['pending_appointment']);

                // Redirect to success page
                header("Location: " . getPageUrl('patient/payment_success.php?appointment_id=' . $appointment_data['id'] . '&transaction_id=' . $transaction_id));
                exit();

            } catch (PDOException $e) {
                $db->rollBack();
                $error = 'Payment processing failed: ' . $e->getMessage();
            }
        } else {
            $error = 'Payment failed. Please try again.';
        }
    }
}

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-credit-card me-2"></i>Payment Gateway</h2>
        <a href="<?php echo getPageUrl('patient/book_appointment.php'); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Booking
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Payment Details -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Payment Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($appointment_data['doctor_first_name'] . ' ' . $appointment_data['doctor_last_name']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Specialization:</strong> <?php echo htmlspecialchars($appointment_data['specialization'] ?? 'General Medicine'); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Date:</strong> <?php echo date('M d, Y', strtotime($appointment_data['appointment_date'])); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Time:</strong> <?php echo date('h:i A', strtotime($appointment_data['appointment_time'])); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <strong>Symptoms:</strong> <?php echo htmlspecialchars($appointment_data['symptoms']); ?>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Consultation Fee:</strong>
                        </div>
                        <div class="col-md-6 text-end">
                            <h4 class="text-primary">₹<?php echo number_format($appointment_data['payment_amount'], 2); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>Select Payment Method</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="paymentForm">
                        <input type="hidden" name="amount" value="<?php echo $appointment_data['payment_amount']; ?>">

                        <!-- UPI Payment -->
                        <div class="payment-method mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="upi" value="upi" required>
                                <label class="form-check-label w-100" for="upi">
                                    <div class="d-flex align-items-center">
                                        <i class="fab fa-google-pay fa-2x text-primary me-3"></i>
                                        <div>
                                            <strong>UPI Payment</strong>
                                            <small class="d-block text-muted">Pay using Google Pay, PhonePe, Paytm</small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div id="upi-details" class="payment-details mt-2" style="display: none;">
                                <div class="form-group">
                                    <label for="upi-id">UPI ID:</label>
                                    <input type="text" class="form-control" id="upi-id" placeholder="yourname@paytm" name="upi_id">
                                    <small class="text-muted">Scan QR code or enter UPI ID in your payment app</small>
                                </div>
                            </div>
                        </div>

                        <!-- Card Payment -->
                        <div class="payment-method mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="card" value="card" required>
                                <label class="form-check-label w-100" for="card">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-credit-card fa-2x text-success me-3"></i>
                                        <div>
                                            <strong>Debit/Credit Card</strong>
                                            <small class="d-block text-muted">Visa, Mastercard, RuPay accepted</small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div id="card-details" class="payment-details mt-2" style="display: none;">
                                <div class="form-group mb-2">
                                    <label for="card-number">Card Number:</label>
                                    <input type="text" class="form-control" id="card-number" placeholder="1234 5678 9012 3456" maxlength="19">
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="expiry">Expiry:</label>
                                        <input type="text" class="form-control" id="expiry" placeholder="MM/YY" maxlength="5">
                                    </div>
                                    <div class="col-6">
                                        <label for="cvv">CVV:</label>
                                        <input type="text" class="form-control" id="cvv" placeholder="123" maxlength="3">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Net Banking -->
                        <div class="payment-method mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="netbanking" value="netbanking" required>
                                <label class="form-check-label w-100" for="netbanking">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-university fa-2x text-warning me-3"></i>
                                        <div>
                                            <strong>Net Banking</strong>
                                            <small class="d-block text-muted">All major banks supported</small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div id="netbanking-details" class="payment-details mt-2" style="display: none;">
                                <div class="form-group">
                                    <label for="bank">Select Bank:</label>
                                    <select class="form-control" id="bank">
                                        <option value="">Choose your bank</option>
                                        <option value="sbi">State Bank of India</option>
                                        <option value="hdfc">HDFC Bank</option>
                                        <option value="icici">ICICI Bank</option>
                                        <option value="axis">Axis Bank</option>
                                        <option value="pnb">Punjab National Bank</option>
                                        <option value="other">Other Banks</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" id="payButton">
                                <i class="fas fa-lock me-2"></i>Pay ₹<?php echo number_format($appointment_data['payment_amount'], 2); ?>
                            </button>
                        </div>

                        <small class="text-muted d-block text-center mt-2">
                            <i class="fas fa-shield-alt me-1"></i>Secure payment powered by iHealth MediCare
                        </small>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Payment method selection
document.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        // Hide all payment details
        document.querySelectorAll('.payment-details').forEach(function(detail) {
            detail.style.display = 'none';
        });

        // Show selected payment method details
        const selectedMethod = this.value;
        const detailsDiv = document.getElementById(selectedMethod + '-details');
        if (detailsDiv) {
            detailsDiv.style.display = 'block';
        }
    });
});

// Card number formatting
document.getElementById('card-number').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
    let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
    e.target.value = formattedValue;
});

// Expiry date formatting
document.getElementById('expiry').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.substring(0, 2) + '/' + value.substring(2, 4);
    }
    e.target.value = value;
});

// Form submission with payment simulation
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const payButton = document.getElementById('payButton');
    const originalText = payButton.innerHTML;

    // Show processing state
    payButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing Payment...';
    payButton.disabled = true;

    // Simulate payment processing delay
    setTimeout(function() {
        // Submit the form
        document.getElementById('paymentForm').submit();
    }, 2000);
});
</script>

<style>
.payment-method {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.payment-method:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.payment-method .form-check-input:checked ~ .form-check-label {
    color: #007bff;
}

.payment-details {
    background-color: #f8f9fa;
    border-radius: 5px;
    padding: 15px;
    border-left: 3px solid #007bff;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}
</style>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
