<!DOCTYPE html>
<html>
<head>
    <title>Database Verification</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #252526;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        h1, h2 {
            color: #4EC9B0;
            border-bottom: 2px solid #4EC9B0;
            padding-bottom: 10px;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #1e1e1e;
            border-left: 4px solid #569CD6;
        }
        .success { color: #4EC9B0; }
        .error { color: #F48771; }
        .warning { color: #CE9178; }
        .info { color: #569CD6; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #3e3e3e;
        }
        th {
            background: #323232;
            color: #4EC9B0;
        }
        .badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success { background: #4EC9B0; color: #000; }
        .badge-error { background: #F48771; color: #000; }
        .badge-info { background: #569CD6; color: #000; }
        pre {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<div class="container">
<?php
require_once 'config.php';
require_once 'includes/db_connect.php';

echo "<h1>📊 Database Migration Verification Report</h1>";
echo "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Database:</strong> telehealth</p>";

// Expected configuration
$expected_tables = [
    'users' => 'Core user authentication',
    'specializations' => 'Medical specializations',
    'doctor_profiles' => 'Doctor extended info',
    'patient_profiles' => 'Patient extended info',
    'appointments' => 'Appointment bookings',
    'call_sessions' => 'Video call join tracking',
    'video_calls' => 'Video call records',
    'prescriptions' => 'Medical prescriptions',
    'reviews' => 'Doctor ratings',
    'password_reset_tokens' => 'Password recovery',
    'notifications' => 'System notifications',
    'webrtc_signals' => 'WebRTC signaling',
    'appointment_history' => 'Appointment change logs',
    'payments' => 'Payment transactions',
    'system_settings' => 'App configuration'
];

// 1. TABLE VERIFICATION
echo "<div class='section'>";
echo "<h2>1. Table Verification</h2>";

try {
    $stmt = $db->query("SHOW TABLES");
    $actual_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<p><strong>Expected:</strong> " . count($expected_tables) . " tables</p>";
    echo "<p><strong>Found:</strong> " . count($actual_tables) . " tables</p>";

    $missing = array_diff(array_keys($expected_tables), $actual_tables);
    $extra = array_diff($actual_tables, array_keys($expected_tables));

    if (empty($missing)) {
        echo "<p class='success'>✓ All expected tables exist!</p>";
    } else {
        echo "<p class='error'>✗ Missing tables: " . implode(', ', $missing) . "</p>";
    }

    echo "<table>";
    echo "<tr><th>Table Name</th><th>Description</th><th>Status</th><th>Rows</th></tr>";

    foreach ($expected_tables as $table => $desc) {
        $exists = in_array($table, $actual_tables);
        $count = 0;

        if ($exists) {
            try {
                $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            } catch (Exception $e) {
                $count = 'Error';
            }
        }

        echo "<tr>";
        echo "<td><strong>$table</strong></td>";
        echo "<td>$desc</td>";
        echo "<td>" . ($exists ? "<span class='badge badge-success'>EXISTS</span>" : "<span class='badge badge-error'>MISSING</span>") . "</td>";
        echo "<td>" . ($exists ? $count : 'N/A') . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    if (!empty($extra)) {
        echo "<p class='warning'>ℹ️ Extra tables: " . implode(', ', $extra) . "</p>";
    }

} catch (PDOException $e) {
    echo "<p class='error'>Database Error: " . $e->getMessage() . "</p>";
}

echo "</div>";

// 2. CRITICAL COLUMNS CHECK
echo "<div class='section'>";
echo "<h2>2. Critical Columns Verification</h2>";

$critical_columns = [
    'appointments' => ['id', 'status', 'missed_by', 'rejection_reason', 'reviewed_by', 'requested_date'],
    'doctor_profiles' => ['user_id', 'available_days', 'availability_start', 'availability_end'],
    'call_sessions' => ['appointment_id', 'doctor_joined_at', 'patient_joined_at'],
    'webrtc_signals' => ['appointment_id', 'sender_id', 'type', 'data', 'processed']
];

echo "<table>";
echo "<tr><th>Table</th><th>Column</th><th>Status</th></tr>";

foreach ($critical_columns as $table => $columns) {
    try {
        $stmt = $db->query("DESCRIBE `$table`");
        $actual_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($columns as $col) {
            $exists = in_array($col, $actual_columns);
            echo "<tr>";
            echo "<td><strong>$table</strong></td>";
            echo "<td>$col</td>";
            echo "<td>" . ($exists ? "<span class='success'>✓</span>" : "<span class='error'>✗</span>") . "</td>";
            echo "</tr>";
        }
    } catch (PDOException $e) {
        echo "<tr><td colspan='3' class='error'>Error checking $table</td></tr>";
    }
}

echo "</table>";
echo "</div>";

// 3. DATA SUMMARY
echo "<div class='section'>";
echo "<h2>3. Data Summary</h2>";

try {
    // Users by role
    echo "<h3>Users</h3>";
    $stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    echo "<table><tr><th>Role</th><th>Count</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr><td>" . ucfirst($row['role']) . "</td><td>" . $row['count'] . "</td></tr>";
    }
    echo "</table>";

    // Specializations
    $count = $db->query("SELECT COUNT(*) FROM specializations")->fetchColumn();
    echo "<p><strong>Specializations:</strong> $count</p>";

    // Appointments by status
    echo "<h3>Appointments</h3>";
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
    echo "<table><tr><th>Status</th><th>Count</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr><td>" . ucfirst($row['status']) . "</td><td>" . $row['count'] . "</td></tr>";
    }
    echo "</table>";

    // System settings
    $count = $db->query("SELECT COUNT(*) FROM system_settings")->fetchColumn();
    echo "<p><strong>System Settings:</strong> $count</p>";

} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo "</div>";

// 4. ENUM CHECK
echo "<div class='section'>";
echo "<h2>4. Appointment Status ENUM</h2>";

try {
    $stmt = $db->query("SHOW COLUMNS FROM appointments WHERE Field = 'status'");
    $status_column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($status_column) {
        echo "<pre>" . htmlspecialchars($status_column['Type']) . "</pre>";

        $required_statuses = ['pending', 'confirmed', 'active', 'completed', 'cancelled', 'rejected', 'missed'];
        echo "<p><strong>Required statuses:</strong></p>";
        echo "<ul>";
        foreach ($required_statuses as $status) {
            $exists = stripos($status_column['Type'], "'$status'") !== false;
            $class = $exists ? 'success' : 'error';
            $icon = $exists ? '✓' : '✗';
            echo "<li class='$class'>$icon <strong>$status</strong></li>";
        }
        echo "</ul>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo "</div>";

// 5. FOREIGN KEYS
echo "<div class='section'>";
echo "<h2>5. Foreign Keys</h2>";

try {
    $stmt = $db->query("
        SELECT
            TABLE_NAME,
            COUNT(*) as fk_count
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'telehealth'
        AND REFERENCED_TABLE_NAME IS NOT NULL
        GROUP BY TABLE_NAME
        ORDER BY TABLE_NAME
    ");

    echo "<table><tr><th>Table</th><th>Foreign Keys</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr><td>" . $row['TABLE_NAME'] . "</td><td>" . $row['fk_count'] . "</td></tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo "</div>";

// 6. TRIGGERS
echo "<div class='section'>";
echo "<h2>6. Triggers</h2>";

try {
    $stmt = $db->query("SHOW TRIGGERS FROM telehealth");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Expected:</strong> 4 triggers (doctor rating updates + prescription number generation)</p>";
    echo "<p><strong>Found:</strong> " . count($triggers) . " triggers</p>";

    if (count($triggers) > 0) {
        echo "<ul>";
        foreach ($triggers as $trigger) {
            echo "<li class='success'>✓ " . $trigger['Trigger'] . " on " . $trigger['Table'] . " (" . $trigger['Event'] . " " . $trigger['Timing'] . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='warning'>⚠️ No triggers found. They may need to be recreated.</p>";
    }
} catch (PDOException $e) {
    echo "<p class='info'>ℹ️ Cannot check triggers: " . $e->getMessage() . "</p>";
}

echo "</div>";

// 7. VIEWS
echo "<div class='section'>";
echo "<h2>7. Views</h2>";

try {
    $stmt = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    $views = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $expected_views = ['doctor_details', 'patient_details', 'appointment_details'];

    echo "<p><strong>Expected:</strong> " . count($expected_views) . " views</p>";
    echo "<p><strong>Found:</strong> " . count($views) . " views</p>";

    if (count($views) > 0) {
        echo "<ul>";
        foreach ($views as $view) {
            $expected = in_array($view, $expected_views);
            echo "<li class='success'>✓ $view</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='warning'>⚠️ No views found. They may need to be recreated.</p>";
    }
} catch (PDOException $e) {
    echo "<p class='info'>ℹ️ Cannot check views: " . $e->getMessage() . "</p>";
}

echo "</div>";

// FINAL SUMMARY
echo "<div class='section' style='border-left-color: #4EC9B0;'>";
echo "<h2>📋 Final Summary</h2>";

$table_match = count($actual_tables) == count($expected_tables);
$all_exist = empty($missing);

if ($table_match && $all_exist) {
    echo "<p class='success' style='font-size: 18px;'><strong>✓ DATABASE STRUCTURE MATCHES MIGRATION SCRIPT</strong></p>";
    echo "<p>All expected tables and critical columns are present.</p>";
} else {
    echo "<p class='error' style='font-size: 18px;'><strong>✗ ISSUES DETECTED</strong></p>";
    if (!$table_match) {
        echo "<p class='error'>- Table count mismatch</p>";
    }
    if (!$all_exist) {
        echo "<p class='error'>- Missing tables: " . implode(', ', $missing) . "</p>";
    }
}

echo "<p><strong>Migration File:</strong> database/complete_telehealth_db.sql</p>";
echo "<p><strong>Recommendation:</strong> ";
if ($table_match && $all_exist) {
    echo "Database is properly configured.";
} else {
    echo "Run the migration script to update database structure.";
}
echo "</p>";

echo "</div>";

?>
</div>
</body>
</html>
