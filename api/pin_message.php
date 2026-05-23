<?php
/**
 * Pin or unpin a message in a conversation
 * POST /api/pin_message.php
 * Authorization: Bearer <token>
 * Body: { message_id, pin: true/false }
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$messageId = (int)($data['message_id'] ?? 0);
$pin = $data['pin'] ?? true;

if ($messageId <= 0) {
    json_response(['error' => 'invalid_input'], 400);
}

$stmt = $pdo->prepare(
    "SELECT m.*, c.created_by as conv_creator
     FROM messages m
     JOIN conversations c ON c.id = m.conversation_id
     WHERE m.id = ? AND m.is_deleted = 0"
);
$stmt->execute([$messageId]);
$message = $stmt->fetch();

if (!$message) {
    json_response(['error' => 'message_not_found'], 404);
}

// Check if user is admin or conversation creator
$stmt = $pdo->prepare(
    "SELECT role FROM conversation_participants WHERE conversation_id = ? AND user_id = ?"
);
$stmt->execute([$message['conversation_id'], $userId]);
$participant = $stmt->fetch();

$isAdmin = $participant && ($participant['role'] === 'admin' || (int)$message['conv_creator'] === $userId || ($auth['is_admin'] ?? false));

if (!$isAdmin) {
    // Allow users to pin their own messages
    if ((int)$message['sender_id'] !== $userId) {
        json_response(['error' => 'not_allowed', 'message' => 'Only admins can pin other users\' messages'], 403);
    }
}

if ($pin) {
    $stmt = $pdo->prepare("UPDATE messages SET is_pinned = 1 WHERE id = ?");
    $stmt->execute([$messageId]);

    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO pinned_messages (conversation_id, message_id, pinned_by) VALUES (?, ?, ?)"
    );
    $stmt->execute([$message['conversation_id'], $messageId, $userId]);
} else {
    $stmt = $pdo->prepare("UPDATE messages SET is_pinned = 0 WHERE id = ?");
    $stmt->execute([$messageId]);

    $stmt = $pdo->prepare(
        "DELETE FROM pinned_messages WHERE message_id = ? AND conversation_id = ?"
    );
    $stmt->execute([$messageId, $message['conversation_id']]);
}

json_response([
    'status' => $pin ? 'pinned' : 'unpinned',
    'message_id' => $messageId
]);
