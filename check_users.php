<?php
// Temporary script to check existing users and their emails
require_once 'config.php';
require_once 'includes/db_connect.php';

echo "<h2>Existing Users in Database</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Name</th></tr>";

$stmt = $db->query("SELECT id, username, email, role, first_name, last_name FROM users");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
    echo "<td>" . ($user['email'] ? htmlspecialchars($user['email']) : '<span style="color:red;">NO EMAIL</span>') . "</td>";
    echo "<td>" . $user['role'] . "</td>";
    echo "<td>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<br><br>";
echo "<h3>To test forgot password:</h3>";
echo "<ol>";
echo "<li>If you see users without emails, run the SQL below to add emails</li>";
echo "<li>Or register a new account with an email</li>";
echo "<li>Then use that email in the forgot password page</li>";
echo "</ol>";

echo "<h3>SQL to add emails to existing users:</h3>";
echo "<pre>";
echo "UPDATE users SET email = CONCAT(username, '@example.com') WHERE email IS NULL OR email = '';";
echo "</pre>";
?>
