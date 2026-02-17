<?php
// Debug version of categories.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Categories Debug Mode</h2>";

try {
    require_once 'resources-config.php';
    echo "<p style='color: green;'>✓ Config loaded</p>";

    requireLogin();
    echo "<p style='color: green;'>✓ Login check passed</p>";

    // Test the query step by step
    echo "<h3>Testing Categories Query...</h3>";

    // Simple query first
    $stmt = $resources_pdo->query("SELECT * FROM categories LIMIT 5");
    $test = $stmt->fetchAll();
    echo "<p style='color: green;'>✓ Simple SELECT works (" . count($test) . " categories found)</p>";

    // Now try with JOIN
    $stmt = $resources_pdo->query("
        SELECT c.*, COUNT(r.id) as resource_count
        FROM categories c
        LEFT JOIN resources r ON c.id = r.category_id
        GROUP BY c.id
        ORDER BY c.name
    ");
    $categories = $stmt->fetchAll();
    echo "<p style='color: green;'>✓ JOIN with GROUP BY works (" . count($categories) . " categories)</p>";

    echo "<h3>Categories Data:</h3>";
    echo "<pre>" . print_r($categories, true) . "</pre>";

} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
