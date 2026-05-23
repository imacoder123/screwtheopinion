<?php
/**
 * Get all contacts (accepted connections) for the authenticated user
 * GET /api/get_contacts.php
 * Authorization: Bearer <token>
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

$stmt = $pdo->prepare(
    "SELECT u.id, u.username, u.name, u.avatar, u.is_verified
     FROM contacts c
     JOIN users u ON (
         (u.id = c.user1_id AND c.user2_id = ?) OR
         (u.id = c.user2_id AND c.user1_id = ?)
     )
     ORDER BY u.username ASC"
);
$stmt->execute([$userId, $userId]);
$contacts = $stmt->fetchAll();

// Add presence and last message info
foreach ($contacts as &$contact) {
    $presence = get_user_presence($contact['id']);
    $contact['presence'] = $presence['status'];
    $contact['last_seen'] = $presence['last_seen'];

    // Get last message for preview
    $stmt2 = $pdo->prepare(
        "SELECT m.id, m.content, m.type, m.created_at, m.sender_id
         FROM messages m
         JOIN conversation_participants cp1 ON cp1.user_id = ?
         JOIN conversation_participants cp2 ON cp2.user_id = ? AND cp1.conversation_id = cp2.conversation_id
         JOIN conversations c ON c.id = cp1.conversation_id AND c.type = 'direct'
         WHERE m.conversation_id = c.id
         ORDER BY m.created_at DESC
         LIMIT 1"
    );
    $stmt2->execute([$userId, $contact['id']]);
    $lastMsg = $stmt2->fetch();

    if ($lastMsg) {
        $contact['last_message'] = $lastMsg['type'] === 'text'
            ? substr($lastMsg['content'], 0, 100)
            : '[' . $lastMsg['type'] . ']';
        $contact['last_message_time'] = $lastMsg['created_at'];
        $contact['last_message_sender'] = (int)$lastMsg['sender_id'];
    } else {
        $contact['last_message'] = null;
        $contact['last_message_time'] = null;
    }

    // Get unread count
    $stmt3 = $pdo->prepare(
        "SELECT COUNT(*) as unread
         FROM messages m
         JOIN conversation_participants cp1 ON cp1.user_id = ?
         JOIN conversation_participants cp2 ON cp2.user_id = ? AND cp1.conversation_id = cp2.conversation_id
         JOIN conversations c ON c.id = cp1.conversation_id AND c.type = 'direct'
         LEFT JOIN message_status ms ON ms.message_id = m.id AND ms.user_id = ?
         WHERE m.conversation_id = c.id AND m.sender_id != ? AND (ms.id IS NULL OR ms.status != 'read')
           AND (cp1.last_read_at IS NULL OR m.created_at > cp1.last_read_at)"
    );
    $stmt3->execute([$userId, $contact['id'], $userId, $userId]);
    $contact['unread_count'] = (int)$stmt3->fetchColumn();
}

json_response($contacts);
