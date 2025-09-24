    </main>

    <footer class="bg-dark text-light py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-heartbeat text-primary me-2"></i><?php echo SITE_NAME; ?></h5>
                    <p class="mb-3">Providing quality healthcare through telemedicine technology. Connect with trusted doctors anytime, anywhere.</p>
                    <div class="social-links">
                        <a href="#" class="text-light me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-linkedin fa-lg"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-instagram fa-lg"></i></a>
                    </div>
                </div>

                <div class="col-md-2 mb-4">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo getPageUrl('index.php'); ?>" class="text-light-50 text-decoration-none">Home</a></li>
                        <li><a href="<?php echo getPageUrl('index.php'); ?>" class="text-light-50 text-decoration-none">Find Doctors</a></li>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'patient'): ?>
                            <li><a href="<?php echo getPageUrl('patient/'); ?>" class="text-light-50 text-decoration-none">Dashboard</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo getPageUrl('profile.php'); ?>" class="text-light-50 text-decoration-none">Profile</a></li>
                    </ul>
                </div>

                <div class="col-md-3 mb-4">
                    <h6>Services</h6>
                    <ul class="list-unstyled">
                        <li class="text-light-50">Video Consultations</li>
                        <li class="text-light-50">Digital Prescriptions</li>
                        <li class="text-light-50">24/7 Support</li>
                        <li class="text-light-50">Secure & Private</li>
                    </ul>
                </div>

                <div class="col-md-3 mb-4">
                    <h6>Contact Info</h6>
                    <p class="text-light-50 mb-1"><i class="fas fa-envelope me-2"></i><?php echo ADMIN_EMAIL; ?></p>
                    <p class="text-light-50 mb-1"><i class="fas fa-phone me-2"></i>+1 (555) 123-4567</p>
                    <p class="text-light-50 mb-3"><i class="fas fa-clock me-2"></i>24/7 Available</p>

                    <div class="security-badges">
                        <span class="badge bg-success me-2"><i class="fas fa-shield-alt"></i> SSL Secured</span>
                        <span class="badge bg-primary"><i class="fas fa-lock"></i> HIPAA Compliant</span>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-light-50 text-decoration-none me-3">Privacy Policy</a>
                    <a href="#" class="text-light-50 text-decoration-none me-3">Terms of Service</a>
                    <a href="#" class="text-light-50 text-decoration-none">HIPAA Compliance</a>
                </div>
            </div>
        </div>
    </footer>

                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
            <script src="<?php echo JS_URL; ?>/main.js"></script>
            <script src="<?php echo JS_URL; ?>/video_call.js"></script>
</body>
</html>