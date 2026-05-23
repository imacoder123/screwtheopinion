<?php
/**
 * Search messages in conversations
 * GET /api/search_messages.php?q=<query>&conversation_id=<id>
 * Authorization: Bearer <token>
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

$query = trim($_GET['q'] ?? '');
$conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : null;

if (strlen($query) < 1) {
    json_response([]);
}

$params = [];
$sql = "SELECT m.*, u.username as sender_username, u.avatar as sender_avatar, c.name as conversation_name, c.type as conversation_type
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        JOIN conversations c ON c.id = m.conversation_id
        JOIN conversation_participants cp ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
        WHERE m.content LIKE ? AND m.is_deleted = 0";

$params[] = $userId;
$params[] = "%$query%";

if ($conversationId) {
    $sql .= " AND m.conversation_id = ?";
    $params[] = $conversationId;
}

$sql .= " ORDER BY m.created_at DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

// For direct conversations, get the other user's name
foreach ($results as &$r) {
    $r['id'] = (int)$r['id'];
    $r['sender_id'] = (int)$r['sender_id'];
    if ($r['conversation_type'] === 'direct') {
        $stmt2 = $pdo->prepare(
            "SELECT u.username, u.name FROM conversation_participants cp
             JOIN users u ON u.id = cp.user_id
             WHERE cp.conversation_id = ? AND cp.user_id != ?"
        );
        $stmt2->execute([$r['conversation_id'], $userId]);
        $otherUser = $stmt2->fetch();
        $r['conversation_display_name'] = $otherUser ? $otherUser['name'] : 'Unknown';
    } else {
        $r['conversation_display_name'] = $r['conversation_name'];
    }
}

json_response($results);
