<?php
/**
 * Get or update user profile
 * GET /api/profile.php?user_id=<id> - Get profile
 * PUT /api/profile.php - Update own profile
 * Authorization: Bearer <token>
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $targetId = (int)($_GET['user_id'] ?? $userId);

    $stmt = $pdo->prepare(
        "SELECT id, username, name, email, avatar, bio, is_verified, last_active, created_at
         FROM users WHERE id = ?"
    );
    $stmt->execute([$targetId]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(['error' => 'user_not_found'], 404);
    }

    $presence = get_user_presence($targetId);
    $user['presence'] = $presence['status'];
    $user['last_seen'] = $presence['last_seen'];

    // Relationship info
    $user['is_contact'] = are_contacts($userId, $targetId);
    $user['is_blocked'] = is_blocked($targetId, $userId);
    $user['i_blocked'] = is_blocked($userId, $targetId);

    // Pending request status
    $stmt2 = $pdo->prepare(
        "SELECT id, status, sender_id FROM friend_requests
         WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
           AND status = 'pending'
         LIMIT 1"
    );
    $stmt2->execute([$userId, $targetId, $targetId, $userId]);
    $req = $stmt2->fetch();
    $user['request_status'] = $req ? ($req['sender_id'] == $userId ? 'sent' : 'received') : null;
    $user['request_id'] = $req ? (int)$req['id'] : null;

    unset($user['email']); // Don't expose email
    json_response($user);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = get_json_body();

    $fields = [];
    $params = [];

    if (isset($data['name'])) {
        $fields[] = "name = ?";
        $params[] = sanitize($data['name']);
    }
    if (isset($data['bio'])) {
        $fields[] = "bio = ?";
        $params[] = sanitize($data['bio']);
    }
    if (isset($data['avatar'])) {
        $fields[] = "avatar = ?";
        $params[] = sanitize($data['avatar']);
    }

    if (empty($fields)) {
        json_response(['error' => 'nothing_to_update'], 400);
    }

    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    json_response(['status' => 'updated']);
}

json_response(['error' => 'method_not_allowed'], 405);
