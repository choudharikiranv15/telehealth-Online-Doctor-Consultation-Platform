<?php
require_once 'includes/db_connect.php';

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="complete_telehealth_with_data_' . date('Y-m-d_H-i-s') . '.sql"');

echo "-- ===================================================================\n";
echo "-- COMPLETE TELEHEALTH DATABASE WITH ALL EXISTING DATA\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- ===================================================================\n\n";

echo "DROP DATABASE IF EXISTS telehealth_db;\n";
echo "CREATE DATABASE telehealth_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
echo "USE telehealth_db;\n\n";

echo "-- ===================================================================\n";
echo "-- TABLE STRUCTURES\n";
echo "-- ===================================================================\n\n";

// Get all table structures
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    // Skip views
    $check = $db->query("SHOW FULL TABLES LIKE '$table'")->fetch();
    if ($check[1] == 'VIEW') continue;

    $create = $db->query("SHOW CREATE TABLE `$table`")->fetch();
    echo "-- Table: $table\n";
    echo $create[1] . ";\n\n";
}

echo "\n-- ===================================================================\n";
echo "-- TABLE DATA\n";
echo "-- ===================================================================\n\n";

// Export data for each table in correct order (respecting foreign keys)
$data_tables = [
    'users',
    'specializations',
    'doctor_profiles',
    'patient_profiles',
    'appointments',
    'video_calls',
    'prescriptions',
    'reviews',
    'password_reset_tokens',
    'notifications',
    'system_settings'
];

foreach ($data_tables as $table) {
    // Check if table exists
    try {
        $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();

        if ($count == 0) {
            echo "-- Table: $table (empty)\n\n";
            continue;
        }

        echo "-- Table: $table ($count rows)\n";

        // Get all rows
        $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            // Get column names
            $columns = array_keys($rows[0]);
            $escaped_columns = array_map(function($col) { return "`$col`"; }, $columns);

            echo "INSERT INTO `$table` (" . implode(', ', $escaped_columns) . ") VALUES\n";

            $values = [];
            foreach ($rows as $row) {
                $row_values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $row_values[] = 'NULL';
                    } else {
                        $escaped = str_replace(["\\", "'"], ["\\\\", "''"], $value);
                        $row_values[] = "'$escaped'";
                    }
                }
                $values[] = '(' . implode(', ', $row_values) . ')';
            }

            echo implode(",\n", $values) . ";\n\n";
        }

    } catch (Exception $e) {
        echo "-- Table $table: Not found or error\n\n";
    }
}

echo "\n-- ===================================================================\n";
echo "-- VIEWS\n";
echo "-- ===================================================================\n\n";

// Export views
foreach ($tables as $table) {
    $check = $db->query("SHOW FULL TABLES LIKE '$table'")->fetch();
    if ($check[1] == 'VIEW') {
        $create = $db->query("SHOW CREATE VIEW `$table`")->fetch();
        echo "-- View: $table\n";
        echo $create[1] . ";\n\n";
    }
}

echo "\n-- ===================================================================\n";
echo "-- TRIGGERS\n";
echo "-- ===================================================================\n\n";

$triggers = $db->query("SHOW TRIGGERS")->fetchAll();
foreach ($triggers as $trigger) {
    echo "-- Trigger: {$trigger['Trigger']}\n";
    $create = $db->query("SHOW CREATE TRIGGER `{$trigger['Trigger']}`")->fetch();
    echo $create[2] . ";\n\n";
}

echo "\n-- Export completed successfully!\n";
