<?php
/**
 * download.php — Proxies file downloads from GCS.
 *
 * The browser's `download` attribute is ignored for cross-origin URLs, so
 * pointing directly at storage.googleapis.com produces navigation instead of
 * a download. This endpoint fetches the object server-side and serves it with
 * Content-Disposition: attachment, keeping everything same-origin.
 */
require_once 'admin/resources-config.php';

$file_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$file_id) {
    http_response_code(400);
    exit('Invalid request.');
}

// Only serve files that belong to a published resource.
$stmt = $resources_pdo->prepare(
    "SELECT rf.*
     FROM resource_files rf
     JOIN resources r ON rf.resource_id = r.id
     WHERE rf.id = ? AND r.is_published = 1"
);
$stmt->execute([$file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file || empty($file['file_path'])) {
    http_response_code(404);
    exit('File not found.');
}

$gcsUrl = $file['file_path'];

// Use display_name for the downloaded filename, falling back to the stored name.
$extension    = strtolower(pathinfo($file['file_name'] ?? '', PATHINFO_EXTENSION));
$downloadName = $file['display_name'] ?: ($file['file_name'] ?: 'download');

// Ensure the filename has the correct extension.
if ($extension && !str_ends_with(strtolower($downloadName), '.' . $extension)) {
    $downloadName .= '.' . $extension;
}

$mimeMap = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'zip'  => 'application/zip',
    'mp4'  => 'video/mp4',
    'mp3'  => 'audio/mpeg',
];
$contentType = $mimeMap[$extension] ?? 'application/octet-stream';

if (str_starts_with($gcsUrl, 'https://storage.googleapis.com/')) {
    // New-style GCS upload — open the stream first, send headers only on success.
    $objectName = str_replace(RESOURCES_UPLOAD_DIR, '', $gcsUrl);
    try {
        $storage = new Google\Cloud\Storage\StorageClient();
        $stream  = $storage->bucket(RESOURCES_GCS_BUCKET)->object($objectName)->downloadAsStream();
    } catch (Exception $e) {
        http_response_code(502);
        exit('Unable to retrieve file from storage.');
    }

    if (ob_get_level()) ob_end_clean();
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
    if (!empty($file['file_size'])) {
        header('Content-Length: ' . (int) $file['file_size']);
    }
    header('Cache-Control: no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');

    while (!$stream->eof()) {
        echo $stream->read(65536);
    }
} else {
    // Old-style local upload — file no longer exists on App Engine.
    // The resource attachment needs to be re-uploaded through the admin.
    $localPath = realpath(__DIR__ . '/' . $gcsUrl);
    if (!$localPath || !file_exists($localPath)) {
        http_response_code(404);
        header('Content-Type: text/plain');
        exit('This file was uploaded under the old storage system and is no longer available. Please re-upload it through the admin.');
    }

    if (ob_get_level()) ob_end_clean();
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
    header('Content-Length: ' . filesize($localPath));
    header('Cache-Control: no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    readfile($localPath);
}
exit;
