<?php
// Include path resolution
require_once dirname(__FILE__) . '/paths.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo getPageUrl('index.php'); ?>">
                <i class="fas fa-heartbeat me-2"></i><?php echo SITE_NAME; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getPageUrl('index.php'); ?>">Home</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo getPageUrl('admin/'); ?>">Admin Panel</a>
                            </li>
                        <?php elseif ($_SESSION['role'] === 'doctor'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo getPageUrl('doctor/'); ?>">Doctor Dashboard</a>
                            </li>
                        <?php elseif ($_SESSION['role'] === 'patient'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo getPageUrl('patient/'); ?>">Patient Dashboard</a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i><?php echo $_SESSION['first_name']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <?php if ($_SESSION['role'] === 'patient'): ?>
                                    <li><a class="dropdown-item" href="<?php echo getPageUrl('patient/profile.php'); ?>"><i class="fas fa-user-edit me-2"></i>My Profile</a></li>
                                    <li><a class="dropdown-item" href="<?php echo getPageUrl('patient/my_appointments.php'); ?>"><i class="fas fa-calendar me-2"></i>My Appointments</a></li>
                                    <li><a class="dropdown-item" href="<?php echo getPageUrl('patient/my_prescriptions.php'); ?>"><i class="fas fa-pills me-2"></i>My Prescriptions</a></li>
                                <?php elseif ($_SESSION['role'] === 'doctor'): ?>
                                    <li><a class="dropdown-item" href="<?php echo getPageUrl('doctor/profile.php'); ?>"><i class="fas fa-user-edit me-2"></i>My Profile</a></li>
                                    <li><a class="dropdown-item" href="<?php echo getPageUrl('doctor/manage_appointments.php'); ?>"><i class="fas fa-calendar-check me-2"></i>Manage Appointments</a></li>
                                    <li><a class="dropdown-item" href="<?php echo getPageUrl('doctor/my_appointments.php'); ?>"><i class="fas fa-calendar me-2"></i>My Appointments</a></li>
                                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                                    <li><a class="dropdown-item" href="<?php echo getPageUrl('admin/profile.php'); ?>"><i class="fas fa-user-edit me-2"></i>Profile</a></li>
                                    <li><a class="dropdown-item" href="<?php echo getPageUrl('admin/'); ?>"><i class="fas fa-tachometer-alt me-2"></i>Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?php echo getPageUrl('logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getPageUrl('login.php'); ?>">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getPageUrl('register.php'); ?>">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container my-4">

    <main>
