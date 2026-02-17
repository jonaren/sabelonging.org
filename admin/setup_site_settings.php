<?php
require_once 'resources-config.php';
requireAdmin();

// Read SQL file
$sql = file_get_contents('create_site_settings_table.sql');

try {
    // Execute SQL statements
    $resources_pdo->exec($sql);
    echo "Site settings table created successfully!<br>";
    echo "Default settings have been inserted.<br>";
    echo '<a href="site-settings.php">Go to Site Settings</a> | <a href="dashboard.php">Back to Dashboard</a>';
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
