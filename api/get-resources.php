<?php
// Turn off output buffering errors and start clean buffer
ob_start();

require_once '../admin/resources-config.php';

// Clear any output that might have been generated
ob_end_clean();

// Now set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    // Build query
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

    // Process resources to match the frontend expectations
    $processedResources = [];
    foreach ($resources as $resource) {
        // Determine the button link - prioritize external URL, then button_link
        $buttonLink = '';
        if ($resource['is_external_resource'] && !empty($resource['external_url'])) {
            $buttonLink = $resource['external_url'];
        } elseif (!empty($resource['ButtonLink'])) {
            $buttonLink = $resource['ButtonLink'];
        } else {
            // Link to internal resource page
            $buttonLink = RESOURCES_SITE_URL . '/resource.php?slug=' . $resource['slug'];
        }

        $processedResources[] = [
            'id' => $resource['id'],
            'ResourceName' => $resource['ResourceName'],
            'slug' => $resource['slug'],
            'Description' => $resource['Description'],
            'ThumbnailImage' => $resource['ThumbnailImage'] ?: '',
            'ButtonLink' => $buttonLink,
            'MetaData' => $resource['MetaData'] ?: '',
            'ResourceType' => $resource['ResourceType'] ?: '',
            'ResourceTypeIcon' => $resource['ResourceTypeIcon'] ?: 'fa-bookmark',
            'Category' => $resource['Category'] ?: '',
            'Language' => $resource['Language'] ?: '',
            'Ministry' => $resource['Ministry'] ?: '',
            'view_count' => $resource['view_count'],
            'created_at' => $resource['created_at'],
            'is_external_resource' => (bool)$resource['is_external_resource']
        ];
    }

    echo json_encode([
        'success' => true,
        'count' => count($processedResources),
        'resources' => $processedResources
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching resources: ' . $e->getMessage()
    ]);
}
?>
