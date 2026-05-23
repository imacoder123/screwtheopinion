<?php
/**
 * Accept or reject a friend request (Instagram-style)
 * POST /api/accept_invite.php
 * Authorization: Bearer <token>
 * Body: { invite_id, action: 'accept' | 'reject' }
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$inviteId = (int)($data['invite_id'] ?? 0);
$action = $data['action'] ?? 'accept';

if ($inviteId <= 0) {
    json_response(['error' => 'invalid_invite_id'], 400);
}

// Get the invite
$stmt = $pdo->prepare(
    "SELECT fr.*, u.username as sender_username FROM friend_requests fr
     JOIN users u ON u.id = fr.sender_id
     WHERE fr.id = ? AND fr.status = 'pending'"
);
$stmt->execute([$inviteId]);
$invite = $stmt->fetch();

if (!$invite) {
    json_response(['error' => 'invite_not_found', 'message' => 'Invite not found or already processed'], 404);
}

// Verify the receiver is the current user
if ((int)$invite['receiver_id'] !== $userId) {
    json_response(['error' => 'not_your_invite', 'message' => 'This invite is not for you'], 403);
}

if ($action === 'reject') {
    $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'rejected', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$inviteId]);

    json_response([
        'status' => 'rejected',
        'message' => 'Friend request rejected'
    ]);
}

// Accept - update status and create contact
$pdo->beginTransaction();

try {
    // Update request status
    $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'accepted', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$inviteId]);

    // Add to contacts
    $user1Id = min($invite['sender_id'], $invite['receiver_id']);
    $user2Id = max($invite['sender_id'], $invite['receiver_id']);

    $stmt = $pdo->prepare("INSERT IGNORE INTO contacts (user1_id, user2_id) VALUES (?, ?)");
    $stmt->execute([$user1Id, $user2Id]);

    // Create or get existing direct conversation
    $stmt = $pdo->prepare(
        "SELECT c.id FROM conversations c
         JOIN conversation_participants cp1 ON cp1.conversation_id = c.id AND cp1.user_id = ?
         JOIN conversation_participants cp2 ON cp2.conversation_id = c.id AND cp2.user_id = ?
         WHERE c.type = 'direct'
         LIMIT 1"
    );
    $stmt->execute([$userId, $invite['sender_id']]);
    $existingConv = $stmt->fetchColumn();

    if (!$existingConv) {
        // Create a direct conversation
        $stmt = $pdo->prepare("INSERT INTO conversations (type, created_by) VALUES ('direct', ?)");
        $stmt->execute([$userId]);
        $convId = (int)$pdo->lastInsertId();

        // Add both users as participants
        $stmt = $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)");
        $stmt->execute([$convId, $userId, $convId, $invite['sender_id']]);
    }

    // Create notification for the sender
    $currentUsername = $auth['username'] ?? 'Someone';
    create_notification(
        $invite['sender_id'],
        'accept',
        $inviteId,
        null,
        null,
        $userId,
        'Request Accepted',
        "$currentUsername accepted your friend request"
    );

    $pdo->commit();

    json_response([
        'status' => 'accepted',
        'message' => 'Friend request accepted',
        'conversation_id' => $existingConv ?? $convId ?? null
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['error' => 'server_error', 'message' => 'Failed to process request'], 500);
}
