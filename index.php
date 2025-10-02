<?php
$page_title = "Home";
require_once 'config.php';
require_once 'includes/db_connect.php';

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$specialization = isset($_GET['specialization']) ? trim($_GET['specialization']) : '';
$min_rating = isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : 0;

// Build search query for doctors
$where_conditions = ["u.role = 'doctor'", "u.status = 'active'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR s.name LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param);
}

if (!empty($specialization)) {
    $where_conditions[] = "s.name = ?";
    $params[] = $specialization;
}

if ($min_rating > 0) {
    $where_conditions[] = "dp.rating >= ?";
    $params[] = $min_rating;
}

$where_clause = implode(' AND ', $where_conditions);

// Get doctors
$sql = "SELECT u.*, s.name as specialization, dp.experience_years, dp.consultation_fee, dp.license_number, dp.qualification, dp.languages, dp.rating, dp.total_reviews
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        WHERE $where_clause
        ORDER BY u.first_name";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

// Get unique specializations
$spec_stmt = $db->query("SELECT DISTINCT s.name as specialization FROM specializations s INNER JOIN doctor_profiles dp ON s.id = dp.specialization_id ORDER BY s.name");
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
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search by doctor name or specialty" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="specialization">
                    <option value="">All Specializations</option>
                    <?php foreach ($specializations as $spec): ?>
                        <option value="<?php echo htmlspecialchars($spec['specialization']); ?>" <?php echo $specialization === $spec['specialization'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($spec['specialization']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="min_rating">
                    <option value="0" <?php echo $min_rating == 0 ? 'selected' : ''; ?>>All Ratings</option>
                    <option value="4.5" <?php echo $min_rating == 4.5 ? 'selected' : ''; ?>>4.5+ ⭐</option>
                    <option value="4.0" <?php echo $min_rating == 4.0 ? 'selected' : ''; ?>>4.0+ ⭐</option>
                    <option value="3.5" <?php echo $min_rating == 3.5 ? 'selected' : ''; ?>>3.5+ ⭐</option>
                    <option value="3.0" <?php echo $min_rating == 3.0 ? 'selected' : ''; ?>>3.0+ ⭐</option>
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
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($doctor['specialization'] ?? 'General Medicine'); ?></p>

                            <?php if (!empty($doctor['rating']) && $doctor['total_reviews'] > 0): ?>
                            <div class="mb-2">
                                <span class="text-warning">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= round($doctor['rating']) ? '' : '-o'; ?>"></i>
                                    <?php endfor; ?>
                                </span>
                                <span class="text-muted small">
                                    <?php echo number_format($doctor['rating'], 1); ?> (<?php echo $doctor['total_reviews']; ?> review<?php echo $doctor['total_reviews'] != 1 ? 's' : ''; ?>)
                                </span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($doctor['qualification'])): ?>
                            <p class="text-muted small mb-2">
                                <i class="fas fa-graduation-cap text-primary me-1"></i>
                                <?php
                                    // Extract only degree names, remove college/university names
                                    $lines = explode("\n", $doctor['qualification']);
                                    $degrees = [];
                                    foreach ($lines as $line) {
                                        $line = trim($line);
                                        if (empty($line)) continue;
                                        // Remove everything after dash, "from", "at", or opening parenthesis
                                        $degree = preg_split('/\s*[-–—]\s*|\s+from\s+|\s+at\s+|\s*\(/', $line, 2)[0];
                                        $degree = trim($degree);
                                        if (!empty($degree)) {
                                            $degrees[] = $degree;
                                        }
                                    }
                                    echo htmlspecialchars(implode(', ', $degrees));
                                ?>
                            </p>
                            <?php endif; ?>

                            <?php if (!empty($doctor['languages'])): ?>
                            <p class="text-muted small mb-2">
                                <i class="fas fa-language text-primary me-1"></i>
                                <?php echo htmlspecialchars($doctor['languages']); ?>
                            </p>
                            <?php endif; ?>

                            <p class="mb-2"><small><?php echo ($doctor['experience_years'] ?? 0); ?> years experience</small></p>
                            <p class="mb-3"><strong>₹<?php echo number_format($doctor['consultation_fee'] ?? 500, 0); ?></strong> per consultation</p>
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