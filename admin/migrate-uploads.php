<?php
/**
 * migrate-uploads.php — One-shot script.
 *
 * Finds resource_files records that still have old local paths
 * (../uploads/resources/...), uploads each file to GCS, and updates
 * the DB row to the new public GCS URL.
 *
 * Run LOCALLY (not on App Engine) since the files only exist on disk here.
 * Delete this file after the migration is confirmed complete.
 *
 * Usage: php admin/migrate-uploads.php
 *   OR:  visit https://sacentralequip.org/admin/migrate-uploads.php
 *        (must be logged in as admin)
 */

// ---- Auth guard when run via browser ----
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/auth.php';
    if (!isAdmin()) { http_response_code(403); exit('Admins only.'); }
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/resources-config.php';

$uploadsDir        = __DIR__ . '/../uploads/resources/';
$downloadsDir      = '/Users/jon.aren/Downloads/SA Belonging Resources/';

// Find all resource_files rows with old-style local paths.
$stmt = $resources_pdo->query(
    "SELECT rf.id, rf.resource_id, rf.file_name, rf.file_path, rf.display_name, rf.file_size,
            r.resource_name
     FROM resource_files rf
     JOIN resources r ON rf.resource_id = r.id
     WHERE rf.file_path NOT LIKE 'https://storage.googleapis.com/%'"
);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "Nothing to migrate — all file_path values already point to GCS.\n";
    exit;
}

echo "Found " . count($rows) . " record(s) to migrate.\n\n";

$storage = new Google\Cloud\Storage\StorageClient();
$bucket  = $storage->bucket(RESOURCES_GCS_BUCKET);

$update = $resources_pdo->prepare(
    "UPDATE resource_files SET file_path = ? WHERE id = ?"
);

foreach ($rows as $row) {
    $storedPath = $row['file_path'];                         // e.g. ../uploads/resources/abc.pdf
    $basename   = basename($storedPath);                     // e.g. abc.pdf
    $localFile  = $uploadsDir . $basename;

    echo "Row {$row['id']} — {$basename}: ";

    $downloadFile = $downloadsDir . $basename;

    if (file_exists($downloadFile)) {
        $source = fopen($downloadFile, 'r');
        echo "(downloads folder) ";
    } elseif (file_exists($localFile)) {
        $source = fopen($localFile, 'r');
        echo "(local uploads) ";
    } else {
        echo "MISSING — not found | resource: \"{$row['resource_name']}\" (id {$row['resource_id']})\n";
        continue;
    }

    try {
        $bucket->upload($source, ['name' => $basename]);
        $newUrl = RESOURCES_UPLOAD_DIR . $basename;
        $update->execute([$newUrl, $row['id']]);
        echo "OK → {$newUrl}\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
