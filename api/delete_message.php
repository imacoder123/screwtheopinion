<?php
/**
 * Delete a message (soft delete - "unsend")
 * DELETE /api/delete_message.php
 * Authorization: Bearer <token>
 * Body: { message_id }
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$messageId = (int)($data['message_id'] ?? 0);
$forEveryone = $data['for_everyone'] ?? true;

if ($messageId <= 0) {
    json_response(['error' => 'invalid_input'], 400);
}

$stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
$stmt->execute([$messageId]);
$message = $stmt->fetch();

if (!$message) {
    json_response(['error' => 'message_not_found'], 404);
}

// Verify ownership or admin
$isAdmin = $auth['is_admin'] ?? false;
if ((int)$message['sender_id'] !== $userId && !$isAdmin) {
    json_response(['error' => 'not_your_message', 'message' => 'You can only delete your own messages'], 403);
}

$stmt = $pdo->prepare("UPDATE messages SET is_deleted = 1 WHERE id = ?");
$stmt->execute([$messageId]);

json_response([
    'status' => 'deleted',
    'message_id' => $messageId
]);
