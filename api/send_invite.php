<?php
/**
 * Send a friend request / invite (Instagram-style DM request)
 * POST /api/send_invite.php
 * Authorization: Bearer <token>
 * Body: { receiver_id }
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$senderId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$receiverId = (int)($data['receiver_id'] ?? 0);

if ($receiverId <= 0) {
    json_response(['error' => 'invalid_receiver'], 400);
}

if ($senderId === $receiverId) {
    json_response(['error' => 'cannot_invite_self', 'message' => 'You cannot send an invite to yourself'], 400);
}

// Check if receiver exists
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->execute([$receiverId]);
$receiver = $stmt->fetch();

if (!$receiver) {
    json_response(['error' => 'user_not_found', 'message' => 'User not found'], 404);
}

// Check if already blocked
if (is_blocked($senderId, $receiverId) || is_blocked($receiverId, $senderId)) {
    json_response(['error' => 'blocked', 'message' => 'Unable to send request'], 403);
}

// Check if already contacts
if (are_contacts($senderId, $receiverId)) {
    json_response(['error' => 'already_contacts', 'message' => 'You are already connected'], 409);
}

// Check if pending request exists
$stmt = $pdo->prepare(
    "SELECT id, status FROM friend_requests
     WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
       AND status IN ('pending', 'accepted')"
);
$stmt->execute([$senderId, $receiverId, $receiverId, $senderId]);
$existing = $stmt->fetch();

if ($existing) {
    if ($existing['status'] === 'pending') {
        json_response(['error' => 'request_pending', 'message' => 'A request is already pending'], 409);
    }
    if ($existing['status'] === 'accepted') {
        json_response(['error' => 'already_contacts', 'message' => 'You are already connected'], 409);
    }
}

// If the other user had previously rejected, allow resend (update the old record)
$stmt = $pdo->prepare(
    "SELECT id FROM friend_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'rejected'"
);
$stmt->execute([$senderId, $receiverId]);
$rejected = $stmt->fetch();

if ($rejected) {
    $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'pending', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$rejected['id']]);
    $requestId = $rejected['id'];
} else {
    // Create new request
    $stmt = $pdo->prepare("INSERT INTO friend_requests (sender_id, receiver_id) VALUES (?, ?)");
    $stmt->execute([$senderId, $receiverId]);
    $requestId = (int)$pdo->lastInsertId();
}

// Create notification for receiver
$senderInfo = $auth['username'] ?? 'Someone';
create_notification(
    $receiverId,
    'invite',
    $requestId,
    null,
    null,
    $senderId,
    'New Friend Request',
    "$senderInfo wants to connect with you"
);

json_response([
    'status' => 'invite_sent',
    'request_id' => $requestId,
    'message' => 'Friend request sent successfully'
]);
