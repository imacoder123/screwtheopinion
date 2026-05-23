<?php
/**
 * Block / unblock users
 * POST /api/block.php - Block a user
 * DELETE /api/block.php - Unblock a user
 * GET /api/block.php?user_id=<id> - Check block status
 * Authorization: Bearer <token>
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $targetId = (int)($_GET['user_id'] ?? 0);

    if ($targetId <= 0) {
        // Get all blocked users
        $stmt = $pdo->prepare(
            "SELECT b.*, u.username, u.name, u.avatar
             FROM blocks b
             JOIN users u ON u.id = b.blocked_id
             WHERE b.blocker_id = ?"
        );
        $stmt->execute([$userId]);
        json_response($stmt->fetchAll());
    }

    json_response([
        'is_blocked' => is_blocked($targetId, $userId) || is_blocked($userId, $targetId)
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_json_body();
    $blockedId = (int)($data['blocked_id'] ?? 0);

    if ($blockedId <= 0 || $blockedId === $userId) {
        json_response(['error' => 'invalid_user'], 400);
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (?, ?)");
    $stmt->execute([$userId, $blockedId]);

    // Remove from contacts if they are connected
    $stmt = $pdo->prepare(
        "DELETE FROM contacts WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)"
    );
    $stmt->execute([$userId, $blockedId, $blockedId, $userId]);

    // Reject any pending requests
    $stmt = $pdo->prepare(
        "UPDATE friend_requests SET status = 'rejected'
         WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
           AND status = 'pending'"
    );
    $stmt->execute([$userId, $blockedId, $blockedId, $userId]);

    json_response(['status' => 'blocked']);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = get_json_body();
    $blockedId = (int)($data['blocked_id'] ?? 0);

    if ($blockedId <= 0) {
        json_response(['error' => 'invalid_user'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->execute([$userId, $blockedId]);

    json_response(['status' => 'unblocked']);
}

json_response(['error' => 'method_not_allowed'], 405);
