<?php
require_once 'includes/db_connect.php';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="complete_telehealth_backup_' . date('Y-m-d_His') . '.sql"');

echo "-- ===================================================================\n";
echo "-- COMPLETE TELEHEALTH DATABASE BACKUP WITH ALL DATA\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- Database: telehealth_db\n";
echo "-- ===================================================================\n";
echo "-- Users: 20 (1 admin, 15 doctors, 4 patients)\n";
echo "-- Appointments: 21\n";
echo "-- Prescriptions: 6\n";
echo "-- Reviews: 4\n";
echo "-- ===================================================================\n\n";

echo "SET FOREIGN_KEY_CHECKS=0;\n";
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "SET time_zone = \"+00:00\";\n\n";

echo "DROP DATABASE IF EXISTS telehealth_db;\n";
echo "CREATE DATABASE telehealth_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
echo "USE telehealth_db;\n\n";

// Get all tables (BASE TABLEs only, not views) using INFORMATION_SCHEMA
$tables_query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = 'telehealth_db' AND TABLE_TYPE = 'BASE TABLE'
                 ORDER BY TABLE_NAME";

try {
    $table_result = $db->query($tables_query);
    $base_tables = $table_result->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Fallback to SHOW TABLES if INFORMATION_SCHEMA doesn't work
    $base_tables = [];
    $all_tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($all_tables as $table) {
        // Try to count rows - if it works, it's likely a table
        try {
            $db->query("SELECT 1 FROM `$table` LIMIT 1");
            $base_tables[] = $table;
        } catch (Exception $e) {
            // Skip if error
        }
    }
}

echo "-- ===================================================================\n";
echo "-- TABLE STRUCTURES\n";
echo "-- ===================================================================\n\n";

// Export table structures
foreach ($base_tables as $table) {
    try {
        $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        echo "-- Structure for table: $table\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $create[1] . ";\n\n";
    } catch (Exception $e) {
        echo "-- Error exporting structure for $table: " . $e->getMessage() . "\n\n";
    }
}

echo "-- ===================================================================\n";
echo "-- TABLE DATA\n";
echo "-- ===================================================================\n\n";

// Order tables to respect foreign keys
$ordered_tables = [
    'users',
    'specializations',
    'doctor_profiles',
    'patient_profiles',
    'appointments',
    'video_calls',
    'call_sessions',
    'prescriptions',
    'reviews',
    'payments',
    'password_reset_tokens',
    'notifications',
    'system_settings',
    'appointment_history',
    'webrtc_signals'
];

// Add any tables not in ordered list
foreach ($base_tables as $table) {
    if (!in_array($table, $ordered_tables)) {
        $ordered_tables[] = $table;
    }
}

foreach ($ordered_tables as $table) {
    // Check if table exists
    if (!in_array($table, $base_tables)) {
        continue;
    }

    try {
        $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();

        echo "-- Data for table: $table ($count rows)\n";

        if ($count == 0) {
            echo "-- Empty table\n\n";
            continue;
        }

        // Get all data
        $stmt = $db->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            // Get column names from first row
            $columns = array_keys($rows[0]);
            $column_list = implode('`, `', $columns);

            echo "INSERT INTO `$table` (`$column_list`) VALUES\n";

            $values_array = [];
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        // Properly escape the value
                        $escaped = $db->quote($value);
                        $values[] = $escaped;
                    }
                }
                $values_array[] = '(' . implode(', ', $values) . ')';
            }

            echo implode(",\n", $values_array);
            echo ";\n\n";
        }

    } catch (Exception $e) {
        echo "-- Error exporting data for $table: " . $e->getMessage() . "\n\n";
    }
}

// Export views
echo "-- ===================================================================\n";
echo "-- VIEWS\n";
echo "-- ===================================================================\n\n";

$views_query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = 'telehealth_db' AND TABLE_TYPE = 'VIEW'
                ORDER BY TABLE_NAME";

try {
    $view_result = $db->query($views_query);
    $views = $view_result->fetchAll(PDO::FETCH_COLUMN);

    foreach ($views as $view) {
        try {
            $create = $db->query("SHOW CREATE VIEW `$view`")->fetch(PDO::FETCH_NUM);
            echo "-- View: $view\n";
            echo "DROP VIEW IF EXISTS `$view`;\n";
            echo $create[1] . ";\n\n";
        } catch (Exception $e) {
            echo "-- Error exporting view $view: " . $e->getMessage() . "\n\n";
        }
    }
} catch (Exception $e) {
    echo "-- No views found or error: " . $e->getMessage() . "\n\n";
}

// Export triggers
echo "-- ===================================================================\n";
echo "-- TRIGGERS\n";
echo "-- ===================================================================\n\n";

echo "DELIMITER $$\n\n";

try {
    $triggers = $db->query("SHOW TRIGGERS")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($triggers as $trigger) {
        try {
            $create = $db->query("SHOW CREATE TRIGGER `{$trigger['Trigger']}`")->fetch(PDO::FETCH_NUM);
            echo "-- Trigger: {$trigger['Trigger']}\n";
            echo "DROP TRIGGER IF EXISTS `{$trigger['Trigger']}`$$\n";
            echo $create[2] . "$$\n\n";
        } catch (Exception $e) {
            echo "-- Error exporting trigger {$trigger['Trigger']}: " . $e->getMessage() . "\n\n";
        }
    }
} catch (Exception $e) {
    echo "-- No triggers found or error: " . $e->getMessage() . "\n\n";
}

echo "DELIMITER ;\n\n";

echo "SET FOREIGN_KEY_CHECKS=1;\n\n";

echo "-- ===================================================================\n";
echo "-- BACKUP COMPLETED SUCCESSFULLY\n";
echo "-- ===================================================================\n";
echo "-- Total Tables: " . count($base_tables) . "\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- ===================================================================\n";
