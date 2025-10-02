<?php
require_once 'includes/db_connect.php';

echo "=== CURRENT DATABASE CONTENTS ===\n\n";

try {
    // Count users by role
    $stmt = $db->query('SELECT role, COUNT(*) as count FROM users GROUP BY role');
    echo "Users by role:\n";
    while ($row = $stmt->fetch()) {
        echo "  {$row['role']}: {$row['count']}\n";
    }
    echo "\n";

    // List all specializations
    $stmt = $db->query('SELECT * FROM specializations ORDER BY id');
    echo "Specializations:\n";
    while ($row = $stmt->fetch()) {
        echo "  {$row['id']}. {$row['name']}\n";
    }
    echo "\n";

    // Count appointments
    $count = $db->query('SELECT COUNT(*) FROM appointments')->fetchColumn();
    echo "Total Appointments: $count\n";

    // Count prescriptions if table exists
    try {
        $count = $db->query('SELECT COUNT(*) FROM prescriptions')->fetchColumn();
        echo "Total Prescriptions: $count\n";
    } catch (Exception $e) {
        echo "Prescriptions table: Not found\n";
    }

    // Count reviews if table exists
    try {
        $count = $db->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
        echo "Total Reviews: $count\n";
    } catch (Exception $e) {
        echo "Reviews table: Not found\n";
    }

    // List all users
    echo "\n=== ALL USERS ===\n";
    $stmt = $db->query('SELECT id, username, email, role, first_name, last_name FROM users ORDER BY role, id');
    while ($row = $stmt->fetch()) {
        echo "{$row['id']}. [{$row['role']}] {$row['first_name']} {$row['last_name']} ({$row['username']}) - {$row['email']}\n";
    }

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
