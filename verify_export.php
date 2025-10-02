<?php
require_once 'includes/db_connect.php';

echo "<pre>";
echo "=== DATABASE VERIFICATION REPORT ===\n\n";

try {
    // 1. Check all tables
    echo "1. TABLES IN DATABASE:\n";
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $check = $db->query("SHOW FULL TABLES LIKE '$table'")->fetch();
        $type = $check[1];

        if ($type == 'BASE TABLE') {
            $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "   ✓ $table ($count rows)\n";
        } else {
            echo "   ✓ $table (VIEW)\n";
        }
    }

    echo "\n2. DETAILED DATA VERIFICATION:\n\n";

    // Users
    echo "   USERS:\n";
    $stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY role");
    while ($row = $stmt->fetch()) {
        echo "      - {$row['role']}: {$row['count']}\n";
    }

    // Specializations
    echo "\n   SPECIALIZATIONS:\n";
    $stmt = $db->query("SELECT id, name FROM specializations ORDER BY id");
    while ($row = $stmt->fetch()) {
        echo "      - [{$row['id']}] {$row['name']}\n";
    }

    // Doctor Profiles
    echo "\n   DOCTOR PROFILES:\n";
    $stmt = $db->query("
        SELECT u.first_name, u.last_name, s.name as specialization, dp.license_number
        FROM doctor_profiles dp
        JOIN users u ON dp.user_id = u.id
        JOIN specializations s ON dp.specialization_id = s.id
        ORDER BY u.id
    ");
    $doctor_count = 0;
    while ($row = $stmt->fetch()) {
        $doctor_count++;
        echo "      - Dr. {$row['first_name']} {$row['last_name']} ({$row['specialization']}) - {$row['license_number']}\n";
    }
    echo "      Total: $doctor_count doctors\n";

    // Patient Profiles
    echo "\n   PATIENT PROFILES:\n";
    $stmt = $db->query("
        SELECT u.first_name, u.last_name, pp.blood_group
        FROM patient_profiles pp
        JOIN users u ON pp.user_id = u.id
        ORDER BY u.id
    ");
    $patient_count = 0;
    while ($row = $stmt->fetch()) {
        $patient_count++;
        $bg = $row['blood_group'] ?: 'N/A';
        echo "      - {$row['first_name']} {$row['last_name']} (Blood: $bg)\n";
    }
    echo "      Total: $patient_count patients\n";

    // Appointments
    echo "\n   APPOINTMENTS:\n";
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status ORDER BY status");
    while ($row = $stmt->fetch()) {
        echo "      - {$row['status']}: {$row['count']}\n";
    }
    $total = $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    echo "      Total: $total appointments\n";

    // Prescriptions
    echo "\n   PRESCRIPTIONS:\n";
    $total = $db->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn();
    echo "      Total: $total prescriptions\n";
    if ($total > 0) {
        $stmt = $db->query("SELECT prescription_number FROM prescriptions ORDER BY id LIMIT 5");
        while ($row = $stmt->fetch()) {
            echo "      - {$row['prescription_number']}\n";
        }
        if ($total > 5) echo "      ... and " . ($total - 5) . " more\n";
    }

    // Reviews
    echo "\n   REVIEWS:\n";
    $total = $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
    echo "      Total: $total reviews\n";
    if ($total > 0) {
        $avg = $db->query("SELECT AVG(rating) FROM reviews")->fetchColumn();
        echo "      Average rating: " . number_format($avg, 2) . " stars\n";
    }

    // Video Calls
    echo "\n   VIDEO CALLS:\n";
    $total = $db->query("SELECT COUNT(*) FROM video_calls")->fetchColumn();
    echo "      Total: $total video calls\n";

    // Password Reset Tokens
    try {
        $total = $db->query("SELECT COUNT(*) FROM password_reset_tokens")->fetchColumn();
        echo "\n   PASSWORD RESET TOKENS:\n";
        echo "      Total: $total tokens\n";
    } catch (Exception $e) {
        echo "\n   PASSWORD RESET TOKENS: Table not found\n";
    }

    // Notifications
    try {
        $total = $db->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
        echo "\n   NOTIFICATIONS:\n";
        echo "      Total: $total notifications\n";
    } catch (Exception $e) {
        echo "\n   NOTIFICATIONS: Table not found or error\n";
    }

    // System Settings
    try {
        $total = $db->query("SELECT COUNT(*) FROM system_settings")->fetchColumn();
        echo "\n   SYSTEM SETTINGS:\n";
        echo "      Total: $total settings\n";
        $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key LIMIT 10");
        while ($row = $stmt->fetch()) {
            echo "      - {$row['setting_key']}: {$row['setting_value']}\n";
        }
    } catch (Exception $e) {
        echo "\n   SYSTEM SETTINGS: Table not found or error\n";
    }

    echo "\n3. SCHEMA VERIFICATION:\n\n";

    // Check for important columns
    echo "   Checking appointments table schema:\n";
    $cols = $db->query("SHOW COLUMNS FROM appointments")->fetchAll();
    $important_cols = ['requested_date', 'requested_time', 'original_date', 'original_time', 'reschedule_reason', 'rejection_reason'];
    foreach ($important_cols as $col) {
        $found = false;
        foreach ($cols as $c) {
            if ($c['Field'] == $col) {
                echo "      ✓ $col exists\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "      ✗ $col MISSING\n";
        }
    }

    echo "\n4. VIEWS:\n";
    $views = [];
    foreach ($tables as $table) {
        $check = $db->query("SHOW FULL TABLES LIKE '$table'")->fetch();
        if ($check[1] == 'VIEW') {
            $views[] = $table;
            echo "      ✓ $table\n";
        }
    }
    if (empty($views)) {
        echo "      No views found\n";
    }

    echo "\n5. TRIGGERS:\n";
    $triggers = $db->query("SHOW TRIGGERS")->fetchAll();
    if (count($triggers) > 0) {
        foreach ($triggers as $trigger) {
            echo "      ✓ {$trigger['Trigger']} (on {$trigger['Table']}, {$trigger['Event']})\n";
        }
    } else {
        echo "      No triggers found\n";
    }

    echo "\n=== SUMMARY ===\n";
    echo "Tables: " . count($tables) . "\n";
    echo "Views: " . count($views) . "\n";
    echo "Triggers: " . count($triggers) . "\n";
    echo "Total Users: " . $db->query("SELECT COUNT(*) FROM users")->fetchColumn() . "\n";
    echo "Total Appointments: " . $db->query("SELECT COUNT(*) FROM appointments")->fetchColumn() . "\n";
    echo "Total Prescriptions: " . $db->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn() . "\n";

    echo "\n✓ Verification complete!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
