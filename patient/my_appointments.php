<?php
$page_title = "My Appointments";
require_once dirname(__FILE__) . '/../config.php';
// Note: paths.php is included via config.php now, so this line is not needed here.
require_once dirname(__FILE__) . '/../includes/db_connect.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

$patient_id = $_SESSION['user_id'];
$error = '';
$appointments = [];

try {
    // --- START: CORRECTED QUERY WITH SPECIALIZATIONS, REVIEWS, AND RESCHEDULE INFO ---
    // Updated to use the new database structure with specializations table and check for existing reviews
    $stmt = $db->prepare("
        SELECT a.*, u.first_name, u.last_name, s.name as specialization,
               r.id as review_id, r.rating as review_rating, r.review_text
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        LEFT JOIN reviews r ON a.id = r.appointment_id
        WHERE a.patient_id = ?
        AND a.status IN ('pending', 'confirmed', 'active', 'completed', 'cancelled', 'rejected', 'reschedule_requested')
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    // --- END: CORRECTED QUERY ---
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-alt me-2"></i>My Appointments</h2>
        <a href="<?php echo getPageUrl('patient/book_appointment.php'); ?>" class="btn btn-primary">Book New Appointment</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($appointments)): ?>
        <div class="alert alert-info">No appointments found.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['specialization'] ?? 'General Medicine'); ?></td>
                            <td>
                                <?php 
                                    $date = $appointment['appointment_date'];
                                    if ($date && $date !== '0000-00-00') {
                                        echo date('M d, Y', strtotime($date));
                                    } else {
                                        echo '<span class="text-muted">Invalid Date</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $time = $appointment['appointment_time'];
                                    if ($time && $time !== '00:00:00') {
                                        echo date('h:i A', strtotime($time));
                                    } else {
                                        echo '<span class="text-muted">Invalid Time</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php
                                    echo $appointment['status'] === 'completed' ? 'success' :
                                        ($appointment['status'] === 'cancelled' ? 'secondary' :
                                        ($appointment['status'] === 'rejected' ? 'danger' :
                                        ($appointment['status'] === 'missed' ? 'warning' :
                                        ($appointment['status'] === 'confirmed' ? 'primary' : 'warning'))));
                                ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                                <?php if ($appointment['status'] === 'missed' && !empty($appointment['missed_by'])): ?>
                                    <br><small class="text-muted">
                                        <i class="fas fa-user-times"></i> Missed by: <?php echo ucfirst($appointment['missed_by']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($appointment['status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Awaiting Doctor Approval</span>
                                    <br>
                                    <a href="reschedule_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-outline-primary btn-sm mt-2">
                                        <i class="fas fa-calendar-alt me-1"></i>Reschedule
                                    </a>
                                <?php elseif ($appointment['status'] === 'confirmed' || $appointment['status'] === 'active'): ?>
                                    <a href="<?php echo getPageUrl('video_call_jitsi.php'); ?>?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-video me-1"></i>Start Call
                                    </a>
                                    <br>
                                    <a href="reschedule_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-outline-primary btn-sm mt-2">
                                        <i class="fas fa-calendar-alt me-1"></i>Reschedule
                                    </a>
                                <?php elseif ($appointment['status'] === 'reschedule_requested'): ?>
                                    <div>
                                        <span class="badge bg-info">Reschedule Requested</span>
                                        <?php if (!empty($appointment['requested_date'])): ?>
                                            <br><small class="text-muted mt-1">
                                                <i class="fas fa-calendar-alt"></i>
                                                <strong>New Date:</strong> <?php echo date('M d, Y h:i A', strtotime($appointment['requested_date'] . ' ' . $appointment['requested_time'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($appointment['status'] === 'completed'): ?>
                                    <a href="my_prescriptions.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-info btn-sm">View Details</a>
                                <?php elseif ($appointment['status'] === 'rejected'): ?>
                                    <div>
                                        <span class="badge bg-danger">Rejected by Doctor</span>
                                        <?php if (!empty($appointment['rejection_reason'])): ?>
                                            <br><button type="button" class="btn btn-outline-danger btn-sm mt-2" onclick="showRejectionDetailsModal('<?php echo htmlspecialchars($appointment['rejection_reason'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-info-circle me-1"></i>View Reason
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($appointment['status'] === 'cancelled'): ?>
                                    <div>
                                        <span class="badge bg-secondary">Cancelled</span>
                                        <?php
                                        // Check if this was a doctor rejection (backward compatibility)
                                        $rejection_text = !empty($appointment['rejection_reason']) ? $appointment['rejection_reason'] :
                                                         (!empty($appointment['doctor_notes']) && strpos($appointment['doctor_notes'], 'Rejected:') !== false ? $appointment['doctor_notes'] : '');
                                        if (!empty($rejection_text)):
                                        ?>
                                            <br><button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="showRejectionDetailsModal('<?php echo htmlspecialchars($rejection_text, ENT_QUOTES); ?>')">
                                                <i class="fas fa-info-circle me-1"></i>View Reason
                                            </button>
                                        <?php elseif (!empty($appointment['cancellation_reason'])): ?>
                                            <br><small class="text-muted mt-1">
                                                <i class="fas fa-info-circle"></i>
                                                <?php echo htmlspecialchars($appointment['cancellation_reason']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($appointment['status'] === 'missed'): ?>
                                    <div>
                                        <span class="badge bg-warning text-dark">Missed Appointment</span>
                                        <?php if (!empty($appointment['missed_by'])): ?>
                                            <br><small class="text-muted mt-1">
                                                <i class="fas fa-user-times"></i>
                                                Missed by: <strong><?php echo ucfirst($appointment['missed_by']); ?></strong>
                                            </small>
                                            <?php if ($appointment['missed_by'] === 'patient'): ?>
                                                <br><small class="text-danger">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    You did not join the video call within the waiting period.
                                                </small>
                                            <?php else: ?>
                                                <br><small class="text-info">
                                                    <i class="fas fa-info-circle"></i>
                                                    The doctor did not join the call. Please contact support for a refund.
                                                </small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo ucfirst($appointment['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($appointment['status'] === 'completed'): ?>
                                    <?php if (!empty($appointment['review_id'])): ?>
                                        <!-- Already rated -->
                                        <div class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $appointment['review_rating'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted">Rated</small>
                                    <?php else: ?>
                                        <!-- Show rating button -->
                                        <button class="btn btn-sm btn-outline-warning" onclick="openRatingModal(<?php echo $appointment['id']; ?>, <?php echo $appointment['doctor_id']; ?>, '<?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-star me-1"></i>Rate Doctor
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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

function showRejectionDetailsModal(reason) {
    const modalHtml = `
        <div class="modal fade" id="rejectionDetailsModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Appointment Rejection Reason</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger mb-0">
                            <strong>Doctor's Reason:</strong><br>
                            ${reason.replace(/Rejected:\s*/gi, '')}
                        </div>
                        <p class="text-muted mt-3 mb-0">
                            <small><i class="fas fa-info-circle me-1"></i>You may book a new appointment with this doctor or try another doctor.</small>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <a href="<?php echo getPageUrl('patient/book_appointment.php'); ?>" class="btn btn-primary">
                            <i class="fas fa-calendar-plus me-1"></i>Book New Appointment
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existingModal = document.getElementById('rejectionDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('rejectionDetailsModal'));
    modal.show();

    // Clean up after modal is hidden
    document.getElementById('rejectionDetailsModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}
</script>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
