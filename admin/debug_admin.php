<?php
require_once 'resources-config.php';
requireLogin();

// Enable error output
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Debug Information</h2>";

echo "<h3>Session Variables:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Admin Check:</h3>";
echo "isLoggedIn(): " . (isLoggedIn() ? 'YES' : 'NO') . "<br>";
echo "isAdmin(): " . (isAdmin() ? 'YES' : 'NO') . "<br>";
echo "Session admin_role: " . ($_SESSION['admin_role'] ?? 'NOT SET') . "<br>";
echo "Session admin_id: " . ($_SESSION['admin_id'] ?? 'NOT SET') . "<br>";

echo "<h3>Admin Users Table:</h3>";
try {
    $stmt = $resources_pdo->query("SELECT id, username, email, role FROM admin_users");
    $users = $stmt->fetchAll();

    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

echo "<h3>Site Settings Table:</h3>";
try {
    $stmt = $resources_pdo->query("SELECT COUNT(*) as count FROM site_settings");
    $result = $stmt->fetch();
    echo "Records in site_settings: " . $result['count'];
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

echo "<br><br><a href='dashboard.php'>Back to Dashboard</a>";
?>
