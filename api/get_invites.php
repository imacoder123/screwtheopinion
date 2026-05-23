<?php
/**
 * Get pending friend requests (invites) for the authenticated user
 * GET /api/get_invites.php
 * Authorization: Bearer <token>
 * Query: type=sent|received (default: received)
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

$type = $_GET['type'] ?? 'received';

if ($type === 'sent') {
    // Requests sent by the current user (pending)
    $stmt = $pdo->prepare(
        "SELECT fr.id, fr.status, fr.created_at, u.id as user_id, u.username, u.name, u.avatar, u.is_verified
         FROM friend_requests fr
         JOIN users u ON u.id = fr.receiver_id
         WHERE fr.sender_id = ? AND fr.status = 'pending'
         ORDER BY fr.created_at DESC"
    );
    $stmt->execute([$userId]);
} else {
    // Requests received by the current user
    $stmt = $pdo->prepare(
        "SELECT fr.id, fr.status, fr.created_at, u.id as user_id, u.username, u.name, u.avatar, u.is_verified
         FROM friend_requests fr
         JOIN users u ON u.id = fr.sender_id
         WHERE fr.receiver_id = ? AND fr.status = 'pending'
         ORDER BY fr.created_at DESC"
    );
    $stmt->execute([$userId]);
}

$invites = $stmt->fetchAll();

// Add presence info
foreach ($invites as &$inv) {
    $presence = get_user_presence($inv['user_id']);
    $inv['presence'] = $presence['status'];
    $inv['last_seen'] = $presence['last_seen'];
}

json_response($invites);
