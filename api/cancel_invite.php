<?php
/**
 * Cancel a sent friend request
 * POST /api/cancel_invite.php
 * Authorization: Bearer <token>
 * Body: { invite_id }
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$inviteId = (int)($data['invite_id'] ?? 0);

if ($inviteId <= 0) {
    json_response(['error' => 'invalid_invite_id'], 400);
}

$stmt = $pdo->prepare(
    "UPDATE friend_requests SET status = 'cancelled', updated_at = NOW()
     WHERE id = ? AND sender_id = ? AND status = 'pending'"
);
$stmt->execute([$inviteId, $userId]);

if ($stmt->rowCount() === 0) {
    json_response(['error' => 'invite_not_found', 'message' => 'Invite not found or already processed'], 404);
}

json_response(['status' => 'cancelled', 'message' => 'Request cancelled']);
