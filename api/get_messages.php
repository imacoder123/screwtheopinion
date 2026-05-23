<?php
/**
 * Get messages for a conversation (with infinite scroll / pagination)
 * GET /api/get_messages.php?conversation_id=<id>&before=<message_id>&limit=50
 * Authorization: Bearer <token>
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

$conversationId = (int)($_GET['conversation_id'] ?? 0);
$beforeId = isset($_GET['before']) ? (int)$_GET['before'] : null;
$limit = min((int)($_GET['limit'] ?? 50), 100);

if ($conversationId <= 0) {
    json_response(['error' => 'invalid_conversation'], 400);
}

// Check if user is a participant
$stmt = $pdo->prepare(
    "SELECT id FROM conversation_participants WHERE conversation_id = ? AND user_id = ?"
);
$stmt->execute([$conversationId, $userId]);
if (!$stmt->fetch()) {
    json_response(['error' => 'not_participant', 'message' => 'You are not part of this conversation'], 403);
}

// Get conversation info
$stmt = $pdo->prepare(
    "SELECT c.*, cp.last_read_at FROM conversations c
     JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
     WHERE c.id = ?"
);
$stmt->execute([$userId, $conversationId]);
$conversation = $stmt->fetch();

if (!$conversation) {
    json_response(['error' => 'conversation_not_found'], 404);
}

// Build query with pagination
$sql = "SELECT m.*, u.username as sender_username, u.name as sender_name, u.avatar as sender_avatar
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.conversation_id = ?";

$params = [$conversationId];

if ($beforeId) {
    $sql .= " AND m.id < ?";
    $params[] = $beforeId;
}

$sql .= " ORDER BY m.created_at DESC LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Reverse to chronological order
$messages = array_reverse($messages);

// Get reactions, status, and replies for each message
$messageIds = array_column($messages, 'id');

if (!empty($messageIds)) {
    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));

    // Get reactions
    $stmt = $pdo->prepare(
        "SELECT mr.*, u.username FROM message_reactions mr
         JOIN users u ON u.id = mr.user_id
         WHERE mr.message_id IN ($placeholders)
         ORDER BY mr.created_at ASC"
    );
    $stmt->execute($messageIds);
    $reactions = $stmt->fetchAll();

    $reactionsByMessage = [];
    foreach ($reactions as $r) {
        $reactionsByMessage[$r['message_id']][] = $r;
    }

    // Get message status for the current user
    $stmt = $pdo->prepare(
        "SELECT message_id, status FROM message_status
         WHERE message_id IN ($placeholders) AND user_id = ?"
    );
    $stmt->execute(array_merge($messageIds, [$userId]));
    $statuses = $stmt->fetchAll();
    $statusByMessage = [];
    foreach ($statuses as $s) {
        $statusByMessage[$s['message_id']] = $s['status'];
    }
}

// Enrich messages
foreach ($messages as &$msg) {
    $msg['id'] = (int)$msg['id'];
    $msg['sender_id'] = (int)$msg['sender_id'];
    $msg['is_mine'] = $msg['sender_id'] === $userId;
    $msg['reactions'] = $reactionsByMessage[$msg['id']] ?? [];
    $msg['my_status'] = $statusByMessage[$msg['id']] ?? null;

    // Get reply preview if it's a reply
    if ($msg['reply_to_id']) {
        $stmt = $pdo->prepare(
            "SELECT m.id, m.content, m.type, u.username as sender_username
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.id = ?"
        );
        $stmt->execute([$msg['reply_to_id']]);
        $replyTo = $stmt->fetch();
        if ($replyTo) {
            $msg['reply_to'] = [
                'id' => (int)$replyTo['id'],
                'content' => substr($replyTo['content'] ?? '', 0, 100),
                'type' => $replyTo['type'],
                'sender_username' => $replyTo['sender_username']
            ];
        }
    }

    // Mark message as delivered/read for this user (if sent by someone else)
    if (!$msg['is_mine']) {
        $stmt = $pdo->prepare(
            "INSERT INTO message_status (message_id, user_id, status, timestamp)
             VALUES (?, ?, 'read', NOW())
             ON DUPLICATE KEY UPDATE status = 'read', timestamp = NOW()"
        );
        $stmt->execute([$msg['id'], $userId]);
    }
}

// Update last_read_at for the participant
$stmt = $pdo->prepare(
    "UPDATE conversation_participants SET last_read_at = NOW() WHERE conversation_id = ? AND user_id = ?"
);
$stmt->execute([$conversationId, $userId]);

// Get other participant info for direct conversations
$otherUsers = [];
if ($conversation['type'] === 'direct') {
    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.name, u.avatar, u.is_verified
         FROM conversation_participants cp
         JOIN users u ON u.id = cp.user_id
         WHERE cp.conversation_id = ? AND cp.user_id != ?"
    );
    $stmt->execute([$conversationId, $userId]);
    $otherUsers = $stmt->fetchAll();

    foreach ($otherUsers as &$ou) {
        $presence = get_user_presence($ou['id']);
        $ou['presence'] = $presence['status'];
        $ou['last_seen'] = $presence['last_seen'];
    }
}

json_response([
    'conversation' => $conversation,
    'messages' => $messages,
    'other_users' => $otherUsers,
    'has_more' => count($messages) === $limit
]);
