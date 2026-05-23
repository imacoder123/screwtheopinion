<?php
/**
 * React to a message (emoji reaction)
 * POST /api/react_message.php
 * Authorization: Bearer <token>
 * Body: { message_id, reaction: '❤️' }
 * To remove: { message_id, reaction: '❤️', remove: true }
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$messageId = (int)($data['message_id'] ?? 0);
$reaction = trim($data['reaction'] ?? '');
$remove = $data['remove'] ?? false;

if ($messageId <= 0 || empty($reaction)) {
    json_response(['error' => 'invalid_input'], 400);
}

// Check message exists
$stmt = $pdo->prepare("SELECT id, conversation_id, sender_id FROM messages WHERE id = ? AND is_deleted = 0");
$stmt->execute([$messageId]);
$message = $stmt->fetch();

if (!$message) {
    json_response(['error' => 'message_not_found'], 404);
}

// Check user is a participant
$stmt = $pdo->prepare(
    "SELECT id FROM conversation_participants WHERE conversation_id = ? AND user_id = ?"
);
$stmt->execute([$message['conversation_id'], $userId]);
if (!$stmt->fetch()) {
    json_response(['error' => 'not_participant'], 403);
}

if ($remove) {
    $stmt = $pdo->prepare(
        "DELETE FROM message_reactions WHERE message_id = ? AND user_id = ? AND reaction = ?"
    );
    $stmt->execute([$messageId, $userId, $reaction]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO message_reactions (message_id, user_id, reaction) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE reaction = VALUES(reaction), created_at = NOW()"
    );
    $stmt->execute([$messageId, $userId, $reaction]);

    // Notify the message sender
    if ((int)$message['sender_id'] !== $userId) {
        $senderName = $auth['username'] ?? 'Someone';
        create_notification(
            $message['sender_id'],
            'reaction',
            null,
            $message['conversation_id'],
            $messageId,
            $userId,
            'New Reaction',
            "$senderName reacted $reaction to your message"
        );
    }
}

// Get updated reactions
$stmt = $pdo->prepare(
    "SELECT mr.*, u.username FROM message_reactions mr
     JOIN users u ON u.id = mr.user_id
     WHERE mr.message_id = ?
     ORDER BY mr.created_at ASC"
);
$stmt->execute([$messageId]);
$reactions = $stmt->fetchAll();

json_response([
    'status' => $remove ? 'removed' : 'added',
    'message_id' => $messageId,
    'reactions' => $reactions
]);
