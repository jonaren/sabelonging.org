<?php
// Debug version to see what's going wrong
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Output</h2>";

echo "<h3>1. Testing require_once</h3>";
try {
    require_once '../admin/resources-config.php';
    echo "✓ resources-config.php loaded successfully<br>";
} catch (Exception $e) {
    echo "✗ Error loading resources-config.php: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>2. Testing database connection</h3>";
try {
    if (isset($resources_pdo)) {
        echo "✓ \$resources_pdo variable exists<br>";
        $stmt = $resources_pdo->query("SELECT 1");
        echo "✓ Database connection is working<br>";
    } else {
        echo "✗ \$resources_pdo variable does not exist<br>";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}

echo "<h3>3. Testing query</h3>";
try {
    $query = "
        SELECT
            r.id,
            r.resource_name AS ResourceName,
            r.slug,
            r.description AS Description,
            r.thumbnail_image AS ThumbnailImage,
            r.is_external_resource,
            r.external_url,
            r.button_link AS ButtonLink,
            r.meta_data AS MetaData,
            rt.name AS ResourceType,
            rt.icon AS ResourceTypeIcon,
            c.name AS Category,
            l.name AS Language,
            m.name AS Ministry,
            r.view_count,
            r.created_at
        FROM resources r
        LEFT JOIN resource_types rt ON r.resource_type_id = rt.id
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN languages l ON r.language_id = l.id
        LEFT JOIN ministries m ON r.ministry_id = m.id
        WHERE r.is_published = 1
        ORDER BY r.created_at DESC
    ";

    $stmt = $resources_pdo->prepare($query);
    $stmt->execute();
    $resources = $stmt->fetchAll();

    echo "✓ Query executed successfully<br>";
    echo "✓ Found " . count($resources) . " resources<br>";

    if (count($resources) > 0) {
        echo "<h4>First resource:</h4>";
        echo "<pre>" . print_r($resources[0], true) . "</pre>";
    }

} catch (Exception $e) {
    echo "✗ Query error: " . $e->getMessage() . "<br>";
}

echo "<h3>4. Testing RESOURCES_SITE_URL constant</h3>";
if (defined('RESOURCES_SITE_URL')) {
    echo "✓ RESOURCES_SITE_URL is defined: " . RESOURCES_SITE_URL . "<br>";
} else {
    echo "✗ RESOURCES_SITE_URL is not defined<br>";
}

echo "<h3>5. Testing JSON output</h3>";
$testData = [
    'success' => true,
    'count' => count($resources ?? []),
    'test' => 'This is a test'
];
$json = json_encode($testData);
if ($json !== false) {
    echo "✓ JSON encoding works<br>";
    echo "JSON output: " . $json . "<br>";
} else {
    echo "✗ JSON encoding failed<br>";
}
?>
