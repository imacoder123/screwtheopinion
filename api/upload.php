<?php
/**
 * File upload handler for images, files, voice notes, GIFs
 * POST /api/upload.php
 * Authorization: Bearer <token>
 * Body: multipart/form-data with file field
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$type = $_POST['type'] ?? 'image';
$conversationId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : null;

if (!isset($_FILES['file'])) {
    json_response(['error' => 'no_file', 'message' => 'No file uploaded'], 400);
}

$file = $_FILES['file'];

// Validate upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    json_response(['error' => 'upload_failed', 'message' => 'File upload failed'], 400);
}

if ($file['size'] > MAX_FILE_SIZE) {
    json_response(['error' => 'file_too_large', 'message' => 'File size exceeds maximum of 50MB'], 400);
}

$allowedTypes = [
    'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
    'gif' => ['image/gif'],
    'file' => ['application/pdf', 'application/zip', 'text/plain', 'application/msword',
               'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
               'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'voice' => ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm'],
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowedTypes[$type]) || !in_array($mimeType, $allowedTypes[$type])) {
    json_response(['error' => 'invalid_type', 'message' => 'File type not allowed for this upload type'], 400);
}

// Determine directory
$subDir = match($type) {
    'image' => 'images/',
    'gif' => 'gifs/',
    'voice' => 'voice/',
    default => 'files/'
};

$dir = UPLOAD_DIR . $subDir;
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('file_') . '_' . time() . '.' . $extension;
$filepath = $dir . $filename;
$relativePath = 'uploads/' . $subDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    json_response(['error' => 'upload_failed', 'message' => 'Failed to store file'], 500);
}

// Get image dimensions for images
$width = null;
$height = null;
if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
    [$width, $height] = getimagesize($filepath);
}

// Store attachment record
$stmt = $pdo->prepare(
    "INSERT INTO attachments (uploaded_by, file_path, file_type, file_size, original_name, width, height)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->execute([
    $userId,
    $relativePath,
    $mimeType,
    $file['size'],
    $file['name'],
    $width,
    $height
]);
$attachmentId = (int)$pdo->lastInsertId();

json_response([
    'attachment_id' => $attachmentId,
    'url' => BASE_PATH . '/' . $relativePath,
    'file_path' => $relativePath,
    'file_type' => $mimeType,
    'file_size' => $file['size'],
    'original_name' => $file['name'],
    'width' => $width,
    'height' => $height,
    'type' => $type
], 201);
