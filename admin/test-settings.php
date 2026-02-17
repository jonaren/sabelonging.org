<?php
require_once 'resources-config.php';
requireAdmin();

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test Settings Page</h1>";

echo "<p>Connected to database: " . (isset($resources_pdo) ? "YES" : "NO") . "</p>";

try {
    $stmt = $resources_pdo->query("SELECT COUNT(*) as count FROM site_settings");
    $result = $stmt->fetch();
    echo "<p>Site settings records: " . $result['count'] . "</p>";

    $stmt2 = $resources_pdo->query("SELECT * FROM site_settings LIMIT 5");
    $settings = $stmt2->fetchAll();

    echo "<h3>First 5 Settings:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Key</th><th>Value</th><th>Type</th></tr>";
    foreach ($settings as $setting) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
        echo "<td>" . htmlspecialchars($setting['setting_value']) . "</td>";
        echo "<td>" . htmlspecialchars($setting['setting_type']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='site-settings.php'>Go to Site Settings</a>";
echo " | <a href='dashboard.php'>Back to Dashboard</a>";
?>
