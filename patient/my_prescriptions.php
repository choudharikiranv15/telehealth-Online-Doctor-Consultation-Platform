<?php
$page_title = "My Prescriptions";
require_once '../config.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    // Redirect to the login page using the full URL for robustness
    header("Location: " . SITE_URL . "/login.php");
    exit();
}

require_once '../includes/db_connect.php';

$patient_id = $_SESSION['user_id'];
$error = '';
// Initialize prescriptions as an empty array to prevent count() errors on failure
$prescriptions = []; 

// Get patient's prescriptions with rating information
try {
    // --- START: CORRECTED SQL QUERY ---
    // The query now includes appointment_id, doctor_id and checks for existing reviews
    $stmt = $db->prepare("
        SELECT
            p.*,
            a.id as appointment_id,
            a.appointment_date,
            a.doctor_id,
            u.first_name,
            u.last_name,
            s.name as specialization,
            r.id as review_id,
            r.rating as review_rating
        FROM prescriptions p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        LEFT JOIN reviews r ON a.id = r.appointment_id
        WHERE a.patient_id = ?
        ORDER BY p.created_at DESC
    ");
    // --- END: CORRECTED SQL QUERY ---

    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll();

} catch (PDOException $e) {
    // Display a detailed error for debugging purposes.
    // In a live environment, you would log this error instead.
    $error = 'Database error: ' . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-pills me-2"></i>My Prescriptions</h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">Prescriptions Found (<?php echo count($prescriptions); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($prescriptions)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file-prescription fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No prescriptions found</h5>
                <p class="text-muted">You don't have any prescriptions yet. Complete a consultation to receive one.</p>
                <a href="book_appointment.php" class="btn btn-primary mt-2">
                    <i class="fas fa-calendar-plus me-2"></i>Book an Appointment
                </a>
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($prescriptions as $prescription): ?>
                    <div class="list-group-item list-group-item-action flex-column align-items-start mb-3 border rounded">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1 text-primary">
                                Prescription from Dr. <?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?>
                            </h5>
                            <small class="text-muted"><?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></small>
                        </div>
                        <p class="mb-1">
                            <strong>Specialization:</strong> <?php echo htmlspecialchars($prescription['specialization'] ?? 'General Medicine'); ?> <br>
                            <strong>Appointment Date:</strong> <?php echo date('M d, Y', strtotime($prescription['appointment_date'])); ?>
                        </p>
                        <hr>
                        <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars($prescription['diagnosis'] ?? 'Not specified'); ?></p>
                        <p><strong>Medications:</strong> <?php echo nl2br(htmlspecialchars($prescription['medications'] ?? 'Not specified')); ?></p>
                        <p><strong>Dosage Instructions:</strong> <?php echo nl2br(htmlspecialchars($prescription['dosage_instructions'] ?? 'Not specified')); ?></p>
                        <?php if(!empty($prescription['precautions'])): ?>
                            <p><strong>Precautions:</strong> <?php echo nl2br(htmlspecialchars($prescription['precautions'])); ?></p>
                        <?php endif; ?>
                        <?php if(!empty($prescription['follow_up_instructions'])): ?>
                            <p><strong>Follow-up Instructions:</strong> <?php echo nl2br(htmlspecialchars($prescription['follow_up_instructions'])); ?></p>
                        <?php endif; ?>
                        <?php if(!empty($prescription['follow_up_date'])): ?>
                            <p><strong>Next Follow-up:</strong> <?php echo date('M d, Y', strtotime($prescription['follow_up_date'])); ?></p>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="<?php echo getPageUrl('prescription_pdf.php?id=' . $prescription['id']); ?>"
                               class="btn btn-primary btn-sm" target="_blank" title="View Prescription PDF">
                                <i class="fas fa-eye me-1"></i>View PDF
                            </a>
                            <button class="btn btn-outline-primary btn-sm" onclick="printPrescription(<?php echo $prescription['id']; ?>)" title="Print Prescription">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                        </div>

                        <!-- Rating Section -->
                        <?php if (!empty($prescription['review_id'])): ?>
                            <div class="alert alert-success mt-3 mb-0">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>You rated this consultation:</strong>
                                <span class="text-warning ms-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $prescription['review_rating'] ? '' : '-o'; ?>"></i>
                                    <?php endfor; ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mt-3 mb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-star me-2"></i>
                                        <strong>How was your experience?</strong>
                                        <p class="mb-0 small">Help others by sharing your feedback about this consultation</p>
                                    </div>
                                    <button class="btn btn-warning btn-sm" onclick="openRatingModal(<?php echo $prescription['appointment_id']; ?>, <?php echo $prescription['doctor_id']; ?>, '<?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-star me-1"></i>Rate Doctor
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Rating Modal -->
<div class="modal fade" id="ratingModal" tabindex="-1" aria-labelledby="ratingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ratingModalLabel">Rate Your Consultation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="ratingForm">
                    <input type="hidden" id="appointment_id" name="appointment_id">
                    <input type="hidden" id="doctor_id" name="doctor_id">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Doctor: <span id="doctor_name"></span></label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rating *</label>
                        <div class="star-rating" id="star-rating">
                            <i class="far fa-star" data-rating="1"></i>
                            <i class="far fa-star" data-rating="2"></i>
                            <i class="far fa-star" data-rating="3"></i>
                            <i class="far fa-star" data-rating="4"></i>
                            <i class="far fa-star" data-rating="5"></i>
                        </div>
                        <input type="hidden" id="rating" name="rating" required>
                        <div class="invalid-feedback" id="rating-error">Please select a rating</div>
                    </div>

                    <div class="mb-3">
                        <label for="review_text" class="form-label">Review (Optional)</label>
                        <textarea class="form-control" id="review_text" name="review_text" rows="4" placeholder="Share your experience with this doctor..."></textarea>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_anonymous" name="is_anonymous">
                        <label class="form-check-label" for="is_anonymous">Post anonymously</label>
                    </div>

                    <div class="alert alert-danger d-none" id="error-message"></div>
                    <div class="alert alert-success d-none" id="success-message"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitRating()">Submit Rating</button>
            </div>
        </div>
    </div>
</div>

<style>
.star-rating {
    font-size: 2rem;
    cursor: pointer;
}
.star-rating i {
    color: #ddd;
    transition: color 0.2s;
}
.star-rating i.fas {
    color: #ffc107;
}
.star-rating i:hover,
.star-rating i:hover ~ i {
    color: #ffc107;
}
</style>

<script>
function printPrescription(prescriptionId) {
    // Open the PDF in a new window and trigger print
    const printWindow = window.open('<?php echo getPageUrl('prescription_pdf.php?id='); ?>' + prescriptionId, '_blank');
    printWindow.onload = function() {
        setTimeout(function() {
            printWindow.print();
        }, 500);
    };
}

// Rating Modal JavaScript
let ratingModal;

document.addEventListener('DOMContentLoaded', function() {
    ratingModal = new bootstrap.Modal(document.getElementById('ratingModal'));

    // Star rating interaction
    const stars = document.querySelectorAll('#star-rating i');
    stars.forEach((star, index) => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            document.getElementById('rating').value = rating;
            updateStars(rating);
        });

        star.addEventListener('mouseenter', function() {
            const rating = this.getAttribute('data-rating');
            updateStars(rating);
        });
    });

    document.getElementById('star-rating').addEventListener('mouseleave', function() {
        const currentRating = document.getElementById('rating').value;
        if (currentRating) {
            updateStars(currentRating);
        } else {
            updateStars(0);
        }
    });
});

function updateStars(rating) {
    const stars = document.querySelectorAll('#star-rating i');
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.remove('far');
            star.classList.add('fas');
        } else {
            star.classList.remove('fas');
            star.classList.add('far');
        }
    });
}

function openRatingModal(appointmentId, doctorId, doctorName) {
    document.getElementById('appointment_id').value = appointmentId;
    document.getElementById('doctor_id').value = doctorId;
    document.getElementById('doctor_name').textContent = 'Dr. ' + doctorName;
    document.getElementById('rating').value = '';
    document.getElementById('review_text').value = '';
    document.getElementById('is_anonymous').checked = false;
    updateStars(0);
    document.getElementById('error-message').classList.add('d-none');
    document.getElementById('success-message').classList.add('d-none');
    ratingModal.show();
}

function submitRating() {
    const appointmentId = document.getElementById('appointment_id').value;
    const doctorId = document.getElementById('doctor_id').value;
    const rating = document.getElementById('rating').value;
    const reviewText = document.getElementById('review_text').value;
    const isAnonymous = document.getElementById('is_anonymous').checked ? 1 : 0;

    // Validate rating
    if (!rating) {
        document.getElementById('rating-error').style.display = 'block';
        return;
    }

    document.getElementById('rating-error').style.display = 'none';
    document.getElementById('error-message').classList.add('d-none');

    // Send AJAX request
    fetch('submit_rating.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `appointment_id=${appointmentId}&doctor_id=${doctorId}&rating=${rating}&review_text=${encodeURIComponent(reviewText)}&is_anonymous=${isAnonymous}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('success-message').textContent = data.message;
            document.getElementById('success-message').classList.remove('d-none');
            setTimeout(() => {
                ratingModal.hide();
                location.reload();
            }, 1500);
        } else {
            document.getElementById('error-message').textContent = data.message;
            document.getElementById('error-message').classList.remove('d-none');
        }
    })
    .catch(error => {
        document.getElementById('error-message').textContent = 'An error occurred. Please try again.';
        document.getElementById('error-message').classList.remove('d-none');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
