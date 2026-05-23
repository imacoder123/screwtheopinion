<?php
/**
 * Get all conversations for the authenticated user
 * GET /api/get_conversations.php
 * Authorization: Bearer <token>
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

// Update presence
update_presence($userId, 'online');

$stmt = $pdo->prepare(
    "SELECT c.id, c.type, c.name as group_name, c.avatar as group_avatar, c.created_at,
            cp.last_read_at, cp.is_muted, cp.role as my_role
     FROM conversations c
     JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
     WHERE c.is_archived = 0
     ORDER BY (
         SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.id
     ) DESC, c.created_at DESC"
);
$stmt->execute([$userId]);
$conversations = $stmt->fetchAll();

foreach ($conversations as &$conv) {
    if ($conv['type'] === 'direct') {
        // Get the other user's info
        $stmt2 = $pdo->prepare(
            "SELECT u.id, u.username, u.name, u.avatar, u.is_verified
             FROM conversation_participants cp
             JOIN users u ON u.id = cp.user_id
             WHERE cp.conversation_id = ? AND cp.user_id != ?"
        );
        $stmt2->execute([$conv['id'], $userId]);
        $otherUser = $stmt2->fetch();

        $conv['other_user'] = $otherUser ?: null;
        $conv['name'] = $otherUser ? $otherUser['name'] : 'Unknown';
        $conv['avatar'] = $otherUser ? $otherUser['avatar'] : null;

        if ($otherUser) {
            $presence = get_user_presence($otherUser['id']);
            $conv['presence'] = $presence['status'];
            $conv['last_seen'] = $presence['last_seen'];
        }
    } else {
        // Group chat - get member count
        $stmt2 = $pdo->prepare(
            "SELECT COUNT(*) as cnt FROM conversation_participants WHERE conversation_id = ?"
        );
        $stmt2->execute([$conv['id']]);
        $conv['member_count'] = (int)$stmt2->fetchColumn();
    }

    // Last message
    $stmt2 = $pdo->prepare(
        "SELECT m.id, m.content, m.type, m.created_at, m.sender_id, m.sender_id as last_sender_id,
                u.username as sender_username
         FROM messages m
         LEFT JOIN users u ON u.id = m.sender_id
         WHERE m.conversation_id = ? AND m.is_deleted = 0
         ORDER BY m.created_at DESC
         LIMIT 1"
    );
    $stmt2->execute([$conv['id']]);
    $lastMsg = $stmt2->fetch();

    if ($lastMsg) {
        $conv['last_message'] = $lastMsg['type'] === 'text'
            ? substr($lastMsg['content'], 0, 120)
            : '[' . $lastMsg['type'] . ']';
        $conv['last_message_time'] = $lastMsg['created_at'];
        $conv['last_sender_id'] = (int)$lastMsg['sender_id'];
        $conv['last_sender_username'] = $lastMsg['sender_username'];
    } else {
        $conv['last_message'] = null;
        $conv['last_message_time'] = null;
    }

    // Unread count
    $stmt2 = $pdo->prepare(
        "SELECT COUNT(*) FROM messages m
         WHERE m.conversation_id = ? AND m.sender_id != ?
           AND m.created_at > COALESCE(?, '2000-01-01')
           AND m.is_deleted = 0"
    );
    $stmt2->execute([$conv['id'], $userId, $conv['last_read_at']]);
    $conv['unread_count'] = (int)$stmt2->fetchColumn();

    // Pinned messages indicator
    $stmt2 = $pdo->prepare(
        "SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND is_pinned = 1 AND is_deleted = 0"
    );
    $stmt2->execute([$conv['id']]);
    $conv['pinned_count'] = (int)$stmt2->fetchColumn();
}

json_response($conversations);
