<?php
require_once 'includes/db_connect.php';

echo "<pre>";
echo "=== EXPORT VERIFICATION COMPARISON ===\n\n";

// Get all base tables
$tables_query = "SELECT TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = 'telehealth_db'
                 ORDER BY TABLE_TYPE, TABLE_NAME";

$all_objects = $db->query($tables_query)->fetchAll(PDO::FETCH_ASSOC);

$base_tables = [];
$views = [];

foreach ($all_objects as $obj) {
    if ($obj['TABLE_TYPE'] == 'BASE TABLE') {
        $base_tables[] = $obj['TABLE_NAME'];
    } else {
        $views[] = $obj['TABLE_NAME'];
    }
}

echo "DATABASE OBJECTS:\n";
echo "  Base Tables: " . count($base_tables) . "\n";
echo "  Views: " . count($views) . "\n\n";

echo "BASE TABLES (will be exported):\n";
foreach ($base_tables as $table) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "  ✓ $table ($count rows)\n";
    } catch (Exception $e) {
        echo "  ✗ $table (ERROR: {$e->getMessage()})\n";
    }
}

echo "\nVIEWS (will be exported separately):\n";
foreach ($views as $view) {
    echo "  ✓ $view\n";
}

echo "\nTRIGGERS:\n";
$triggers = $db->query("SHOW TRIGGERS")->fetchAll();
foreach ($triggers as $trigger) {
    echo "  ✓ {$trigger['Trigger']} (on {$trigger['Table']})\n";
}

echo "\n=== CRITICAL DATA COUNTS ===\n";
$critical_tables = [
    'users' => 'User accounts',
    'specializations' => 'Medical specializations',
    'doctor_profiles' => 'Doctor profiles',
    'patient_profiles' => 'Patient profiles',
    'appointments' => 'Appointments',
    'prescriptions' => 'Prescriptions',
    'reviews' => 'Doctor reviews',
    'password_reset_tokens' => 'Password reset tokens',
    'system_settings' => 'System settings',
    'video_calls' => 'Video call sessions',
    'payments' => 'Payment records',
    'notifications' => 'Notifications'
];

foreach ($critical_tables as $table => $description) {
    if (in_array($table, $base_tables)) {
        $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $status = $count > 0 ? "✓" : "⚠";
        echo "$status $description ($table): $count\n";
    } else {
        echo "✗ $description ($table): NOT FOUND\n";
    }
}

echo "\n=== SAMPLE DATA VERIFICATION ===\n";

// Verify users
echo "\nUsers (first 5):\n";
$users = $db->query("SELECT id, username, email, role FROM users ORDER BY id LIMIT 5")->fetchAll();
foreach ($users as $user) {
    echo "  - [{$user['role']}] {$user['username']} ({$user['email']})\n";
}

// Verify appointments
echo "\nAppointments (first 5):\n";
$appointments = $db->query("SELECT id, appointment_date, appointment_time, status FROM appointments ORDER BY id LIMIT 5")->fetchAll();
foreach ($appointments as $apt) {
    echo "  - ID {$apt['id']}: {$apt['appointment_date']} {$apt['appointment_time']} ({$apt['status']})\n";
}

// Verify prescriptions
echo "\nPrescriptions:\n";
$prescriptions = $db->query("SELECT id, prescription_number FROM prescriptions ORDER BY id")->fetchAll();
foreach ($prescriptions as $rx) {
    echo "  - {$rx['prescription_number']}\n";
}

echo "\n=== EXPORT READINESS CHECK ===\n";
$ready = true;

// Check for essential tables
$essential = ['users', 'appointments', 'specializations', 'doctor_profiles', 'patient_profiles'];
foreach ($essential as $table) {
    if (!in_array($table, $base_tables)) {
        echo "✗ Missing essential table: $table\n";
        $ready = false;
    }
}

if ($ready) {
    echo "✓ All essential tables present\n";
    echo "✓ Ready to export!\n";
    echo "\n→ Visit: export_full_database.php to download the complete backup\n";
} else {
    echo "✗ Database structure issues detected\n";
}

echo "\n</pre>";
