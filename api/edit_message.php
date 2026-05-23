<?php
/**
 * Edit a message (only the sender can edit within reasonable time)
 * PUT /api/edit_message.php
 * Authorization: Bearer <token>
 * Body: { message_id, content }
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$messageId = (int)($data['message_id'] ?? 0);
$content = trim($data['content'] ?? '');

if ($messageId <= 0 || empty($content)) {
    json_response(['error' => 'invalid_input'], 400);
}

// Get the message
$stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ? AND is_deleted = 0");
$stmt->execute([$messageId]);
$message = $stmt->fetch();

if (!$message) {
    json_response(['error' => 'message_not_found'], 404);
}

if ((int)$message['sender_id'] !== $userId) {
    json_response(['error' => 'not_your_message', 'message' => 'You can only edit your own messages'], 403);
}

// Check if it's too old to edit (24 hours)
$createdAt = strtotime($message['created_at']);
if (time() - $createdAt > 86400) {
    json_response(['error' => 'edit_window_expired', 'message' => 'Messages can only be edited within 24 hours'], 403);
}

// Update the message
$stmt = $pdo->prepare("UPDATE messages SET content = ?, is_edited = 1, edited_at = NOW() WHERE id = ?");
$stmt->execute([$content, $messageId]);

json_response([
    'status' => 'edited',
    'message_id' => $messageId,
    'content' => $content,
    'edited_at' => date('Y-m-d H:i:s')
]);
