<?php
$page_title = "Home";
require_once 'config.php';
require_once 'includes/db_connect.php';

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$specialization = isset($_GET['specialization']) ? trim($_GET['specialization']) : '';

// Build search query for doctors
$where_conditions = ["u.role = 'doctor'", "u.status = 'active'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR dp.specialization LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param);
}

if (!empty($specialization)) {
    $where_conditions[] = "dp.specialization = ?";
    $params[] = $specialization;
}

$where_clause = implode(' AND ', $where_conditions);

// Get doctors
$sql = "SELECT u.*, dp.specialization, dp.experience_years, dp.consultation_fee, dp.license_number
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE $where_clause
        ORDER BY u.first_name";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

// Get unique specializations
$spec_stmt = $db->query("SELECT DISTINCT specialization FROM doctor_profiles WHERE specialization IS NOT NULL ORDER BY specialization");
$specializations = $spec_stmt->fetchAll();

require_once 'includes/header.php';
?>

<!-- Hero Section -->
<div class="bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3">Welcome to iHealth MediCare</h1>
                <p class="lead mb-4">Connect with qualified doctors through secure online video consultations. Healthcare from anywhere, anytime.</p>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo getPageUrl('register.php'); ?>" class="btn btn-light btn-lg me-3">Get Started</a>
                    <a href="#doctors" class="btn btn-outline-light btn-lg">Find Doctors</a>
                <?php else: ?>
                    <?php if ($_SESSION['role'] === 'patient'): ?>
                        <a href="<?php echo getPageUrl('patient/book_appointment.php'); ?>" class="btn btn-light btn-lg">Book Appointment</a>
                    <?php else: ?>
                        <a href="<?php echo getPageUrl('dashboard.php'); ?>" class="btn btn-light btn-lg">Go to Dashboard</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Search Section -->
<div class="container my-5">
    <form method="GET" action="" class="bg-light p-4 rounded">
        <h3 class="mb-3">Find a Doctor for Online Consultation</h3>
        <div class="row g-3">
            <div class="col-md-5">
                <input type="text" class="form-control" name="search" placeholder="Search by doctor name or specialty" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-4">
                <select class="form-select" name="specialization">
                    <option value="">All Specializations</option>
                    <?php foreach ($specializations as $spec): ?>
                        <option value="<?php echo htmlspecialchars($spec['specialization']); ?>" <?php echo $specialization === $spec['specialization'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($spec['specialization']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Search Doctors
                </button>
            </div>
        </div>
        <div class="text-center mt-3">
            <small class="text-muted">
                <i class="fas fa-video me-1"></i>All consultations are conducted online via secure video calls
            </small>
        </div>
    </form>
</div>

<!-- Why Choose Us Section -->
<div class="container my-5">
    <h2 class="text-center mb-5">Why Choose iHealth MediCare?</h2>
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="text-center">
                <i class="fas fa-video fa-3x text-primary mb-3"></i>
                <h5>HD Video Consultations</h5>
                <p>High-quality video calls with doctors from the comfort of your home</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="text-center">
                <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                <h5>Secure & Private</h5>
                <p>HIPAA compliant platform ensuring your privacy</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="text-center">
                <i class="fas fa-clock fa-3x text-primary mb-3"></i>
                <h5>24/7 Online Access</h5>
                <p>Access healthcare professionals online anytime, from anywhere</p>
            </div>
        </div>
    </div>
</div>

<!-- Doctors Section -->
<section id="doctors" class="container my-5">
    <h2 class="text-center mb-5">Our Expert Doctors</h2>

    <?php if (empty($doctors)): ?>
        <div class="text-center">
            <p>No doctors found matching your search criteria.</p>
            <a href="<?php echo getPageUrl('index.php'); ?>" class="btn btn-primary">Show All Doctors</a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($doctors as $doctor): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 doctor-card">
                        <div class="card-body text-center">
                            <div class="avatar-container">
                                <div class="doctor-avatar-wrapper">
                                    <div class="doctor-avatar">
                                        <?php if (!empty($doctor['profile_picture']) && file_exists('assets/images/' . $doctor['profile_picture'])): ?>
                                            <img src="<?php echo getPageUrl('assets/images/' . htmlspecialchars($doctor['profile_picture'])); ?>"
                                                 alt="Dr. <?php echo htmlspecialchars($doctor['first_name']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-user-md doctor-avatar-icon"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="doctor-avatar-badge">
                                        <i class="fas fa-check"></i>
                                    </div>
                                </div>
                            </div>
                            <h5 class="card-title">Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h5>
                            <p class="text-muted"><?php echo htmlspecialchars($doctor['specialization'] ?? 'General Medicine'); ?></p>
                            <p><small><?php echo ($doctor['experience_years'] ?? 0); ?> years experience</small></p>
                            <p><strong>₹<?php echo number_format($doctor['consultation_fee'] ?? 500, 0); ?></strong> per consultation</p>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'patient'): ?>
                                <a href="<?php echo getPageUrl('patient/book_appointment.php?doctor_id=' . $doctor['id']); ?>" class="btn btn-primary">Book Appointment</a>
                            <?php else: ?>
                                <a href="<?php echo getPageUrl('login.php'); ?>" class="btn btn-primary">Book Now</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- How It Works Section -->
<div class="container my-5">
    <h2 class="text-center mb-5">How It Works</h2>
    <div class="row">
        <div class="col-md-3 text-center mb-4">
            <div class="step-number">1</div>
            <h5>Search & Choose</h5>
            <p>Find a doctor based on specialization and availability for online consultation</p>
        </div>
        <div class="col-md-3 text-center mb-4">
            <div class="step-number">2</div>
            <h5>Book Appointment</h5>
            <p>Select your preferred date and time slot</p>
        </div>
        <div class="col-md-3 text-center mb-4">
            <div class="step-number">3</div>
            <h5>Video Consultation</h5>
            <p>Connect with your doctor through secure video call</p>
        </div>
        <div class="col-md-3 text-center mb-4">
            <div class="step-number">4</div>
            <h5>Get Prescription</h5>
            <p>Receive digital prescription and follow-up instructions</p>
        </div>
    </div>
</div>

<!-- Services Section -->
<div class="container my-5">
    <h2 class="text-center mb-5">Our Services</h2>
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="service-card text-center p-4">
                <i class="fas fa-heartbeat fa-3x text-primary mb-3"></i>
                <h5>Primary Care</h5>
                <p>General health consultations, check-ups, and preventive care</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="service-card text-center p-4">
                <i class="fas fa-brain fa-3x text-primary mb-3"></i>
                <h5>Mental Health</h5>
                <p>Professional counseling and psychiatric consultations</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="service-card text-center p-4">
                <i class="fas fa-baby fa-3x text-primary mb-3"></i>
                <h5>Pediatric Care</h5>
                <p>Specialized care for children and adolescents</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>