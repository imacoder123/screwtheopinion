<?php
/**
 * Send a message to a conversation
 * POST /api/send_message.php
 * Authorization: Bearer <token>
 * Body: { conversation_id, content, type: 'text', reply_to_id: null }
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$senderId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$conversationId = (int)($data['conversation_id'] ?? 0);
$content = trim($data['content'] ?? '');
$type = $data['type'] ?? 'text';
$replyToId = isset($data['reply_to_id']) ? (int)$data['reply_to_id'] : null;

if ($conversationId <= 0) {
    json_response(['error' => 'invalid_conversation'], 400);
}

if (empty($content) && $type === 'text') {
    json_response(['error' => 'empty_message', 'message' => 'Message cannot be empty'], 400);
}

// Verify user is a participant
$stmt = $pdo->prepare(
    "SELECT id FROM conversation_participants WHERE conversation_id = ? AND user_id = ?"
);
$stmt->execute([$conversationId, $senderId]);
if (!$stmt->fetch()) {
    json_response(['error' => 'not_participant', 'message' => 'You are not part of this conversation'], 403);
}

// Check rate limit
if (!check_rate_limit("msg:$senderId", 'send_message', 20, 10)) {
    json_response(['error' => 'rate_limited', 'message' => 'Sending too fast. Please slow down.'], 429);
}

// Insert the message
$stmt = $pdo->prepare(
    "INSERT INTO messages (conversation_id, sender_id, type, content, reply_to_id)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->execute([$conversationId, $senderId, $type, $content, $replyToId]);
$messageId = (int)$pdo->lastInsertId();

// Create delivery status records for all participants except sender
$stmt = $pdo->prepare(
    "SELECT user_id FROM conversation_participants WHERE conversation_id = ? AND user_id != ?"
);
$stmt->execute([$conversationId, $senderId]);
$participants = $stmt->fetchAll();

foreach ($participants as $p) {
    $stmt = $pdo->prepare(
        "INSERT INTO message_status (message_id, user_id, status) VALUES (?, ?, 'sent')"
    );
    $stmt->execute([$messageId, $p['user_id']]);

    // Create notification
    $senderName = $auth['username'] ?? 'Someone';
    $convInfo = $pdo->prepare("SELECT type FROM conversations WHERE id = ?");
    $convInfo->execute([$conversationId]);
    $convType = $convInfo->fetchColumn();

    $preview = $type === 'text' ? substr($content, 0, 100) : "[$type]";
    create_notification(
        $p['user_id'],
        'message',
        null,
        $conversationId,
        $messageId,
        $senderId,
        $convType === 'direct' ? $senderName : ($convType),
        $preview
    );
}

// Get the created message with sender info
$stmt = $pdo->prepare(
    "SELECT m.*, u.username as sender_username, u.name as sender_name, u.avatar as sender_avatar
     FROM messages m
     JOIN users u ON u.id = m.sender_id
     WHERE m.id = ?"
);
$stmt->execute([$messageId]);
$message = $stmt->fetch();

$message['id'] = (int)$message['id'];
$message['sender_id'] = (int)$message['sender_id'];
$message['is_mine'] = true;
$message['reactions'] = [];

json_response([
    'message' => $message,
    'status' => 'sent'
], 201);
