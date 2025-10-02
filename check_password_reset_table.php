<?php
// Quick diagnostic script to check if password reset table exists
require_once 'config.php';
require_once 'includes/db_connect.php';

echo "<h3>Password Reset Table Diagnostic</h3>";

try {
    // Check if table exists
    $stmt = $db->query("SHOW TABLES LIKE 'password_reset_tokens'");
    $table_exists = $stmt->fetch();

    if ($table_exists) {
        echo "<p style='color: green;'>✓ Table 'password_reset_tokens' EXISTS</p>";

        // Check table structure
        $stmt = $db->query("DESCRIBE password_reset_tokens");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h4>Table Structure:</h4>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Check if there are any tokens
        $stmt = $db->query("SELECT COUNT(*) as count FROM password_reset_tokens");
        $result = $stmt->fetch();
        echo "<p>Total tokens in table: " . $result['count'] . "</p>";

        // Show recent tokens (without exposing the actual token values)
        $stmt = $db->query("SELECT id, user_id, expires_at, used, created_at FROM password_reset_tokens ORDER BY created_at DESC LIMIT 5");
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($tokens)) {
            echo "<h4>Recent Tokens (Last 5):</h4>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>User ID</th><th>Expires At</th><th>Used</th><th>Created At</th></tr>";
            foreach ($tokens as $token) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($token['id']) . "</td>";
                echo "<td>" . htmlspecialchars($token['user_id']) . "</td>";
                echo "<td>" . htmlspecialchars($token['expires_at']) . "</td>";
                echo "<td>" . ($token['used'] ? 'Yes' : 'No') . "</td>";
                echo "<td>" . htmlspecialchars($token['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

    } else {
        echo "<p style='color: red;'>✗ Table 'password_reset_tokens' DOES NOT EXIST</p>";
        echo "<p><strong>Solution:</strong> You need to run the SQL migration script:</p>";
        echo "<pre>database/add_password_reset.sql</pre>";
        echo "<p>Steps:</p>";
        echo "<ol>";
        echo "<li>Open phpMyAdmin or MySQL command line</li>";
        echo "<li>Select your 'telehealth_db' database</li>";
        echo "<li>Run the SQL file: database/add_password_reset.sql</li>";
        echo "</ol>";
    }

} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
