<?php
// Script to fix HTML-encoded data in the database
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'resources-config.php';
requireLogin();

echo "<h2>Fix HTML-Encoded Data</h2>";
echo "<p>This script will decode HTML entities in your database records.</p>";

if ($_POST && isset($_POST['confirm'])) {
    try {
        $fixed = [];

        // Fix ministries
        $stmt = $resources_pdo->query("SELECT id, name, description FROM ministries");
        $ministries = $stmt->fetchAll();

        foreach ($ministries as $ministry) {
            $decoded_name = html_entity_decode($ministry['name'], ENT_QUOTES, 'UTF-8');
            $decoded_desc = html_entity_decode($ministry['description'], ENT_QUOTES, 'UTF-8');

            if ($decoded_name !== $ministry['name'] || $decoded_desc !== $ministry['description']) {
                $update = $resources_pdo->prepare("UPDATE ministries SET name = ?, description = ? WHERE id = ?");
                $update->execute([$decoded_name, $decoded_desc, $ministry['id']]);
                $fixed[] = "Ministry: {$ministry['name']} → {$decoded_name}";
            }
        }

        // Fix categories
        $stmt = $resources_pdo->query("SELECT id, name, description FROM categories");
        $categories = $stmt->fetchAll();

        foreach ($categories as $category) {
            $decoded_name = html_entity_decode($category['name'], ENT_QUOTES, 'UTF-8');
            $decoded_desc = html_entity_decode($category['description'], ENT_QUOTES, 'UTF-8');

            if ($decoded_name !== $category['name'] || $decoded_desc !== $category['description']) {
                $update = $resources_pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $update->execute([$decoded_name, $decoded_desc, $category['id']]);
                $fixed[] = "Category: {$category['name']} → {$decoded_name}";
            }
        }

        // Fix languages
        $stmt = $resources_pdo->query("SELECT id, name FROM languages");
        $languages = $stmt->fetchAll();

        foreach ($languages as $language) {
            $decoded_name = html_entity_decode($language['name'], ENT_QUOTES, 'UTF-8');

            if ($decoded_name !== $language['name']) {
                $update = $resources_pdo->prepare("UPDATE languages SET name = ? WHERE id = ?");
                $update->execute([$decoded_name, $language['id']]);
                $fixed[] = "Language: {$language['name']} → {$decoded_name}";
            }
        }

        // Fix resource types
        $stmt = $resources_pdo->query("SELECT id, name FROM resource_types");
        $types = $stmt->fetchAll();

        foreach ($types as $type) {
            $decoded_name = html_entity_decode($type['name'], ENT_QUOTES, 'UTF-8');

            if ($decoded_name !== $type['name']) {
                $update = $resources_pdo->prepare("UPDATE resource_types SET name = ? WHERE id = ?");
                $update->execute([$decoded_name, $type['id']]);
                $fixed[] = "Resource Type: {$type['name']} → {$decoded_name}";
            }
        }

        // Fix resources
        $stmt = $resources_pdo->query("SELECT id, resource_name, description, about_content, meta_data FROM resources");
        $resources = $stmt->fetchAll();

        foreach ($resources as $resource) {
            $decoded_name = html_entity_decode($resource['resource_name'], ENT_QUOTES, 'UTF-8');
            $decoded_desc = html_entity_decode($resource['description'], ENT_QUOTES, 'UTF-8');
            $decoded_about = html_entity_decode($resource['about_content'], ENT_QUOTES, 'UTF-8');
            $decoded_meta = html_entity_decode($resource['meta_data'], ENT_QUOTES, 'UTF-8');

            if ($decoded_name !== $resource['resource_name'] ||
                $decoded_desc !== $resource['description'] ||
                $decoded_about !== $resource['about_content'] ||
                $decoded_meta !== $resource['meta_data']) {
                $update = $resources_pdo->prepare("UPDATE resources SET resource_name = ?, description = ?, about_content = ?, meta_data = ? WHERE id = ?");
                $update->execute([$decoded_name, $decoded_desc, $decoded_about, $decoded_meta, $resource['id']]);
                $fixed[] = "Resource: {$resource['resource_name']} → {$decoded_name}";
            }
        }

        echo "<div style='padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3 style='color: #155724;'>✓ Fixed " . count($fixed) . " records!</h3>";
        if (!empty($fixed)) {
            echo "<ul style='color: #155724;'>";
            foreach ($fixed as $fix) {
                echo "<li>" . htmlspecialchars($fix) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: #155724;'>No HTML-encoded data found. Everything looks good!</p>";
        }
        echo "</div>";

        echo "<p><a href='ministries.php' class='btn btn-primary'>Back to Admin</a></p>";

    } catch (Exception $e) {
        echo "<div style='padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3 style='color: #721c24;'>Error:</h3>";
        echo "<p style='color: #721c24;'>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
} else {
    ?>
    <div style="padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;">
        <h3 style="color: #856404;">⚠️ Warning</h3>
        <p style="color: #856404;">This will decode HTML entities (like <code>&amp;#039;</code>) in all your database records.</p>
        <p style="color: #856404;">This is a one-time fix and is safe to run.</p>
    </div>

    <form method="POST" style="margin: 20px 0;">
        <input type="hidden" name="confirm" value="1">
        <button type="submit" style="padding: 10px 30px; background: #007bff; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer;">
            Fix Encoded Data
        </button>
        <a href="ministries.php" style="padding: 10px 30px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;">
            Cancel
        </a>
    </form>
    <?php
}
?>
