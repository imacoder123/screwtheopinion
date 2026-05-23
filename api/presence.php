<?php
/**
 * Update and get user presence (online/away/offline)
 * GET /api/presence.php?user_id=<id> - Get presence
 * POST /api/presence.php - Update presence
 * Authorization: Bearer <token>
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $targetUserId = (int)($_GET['user_id'] ?? $userId);

    if ($targetUserId <= 0) {
        json_response(['error' => 'invalid_user'], 400);
    }

    $presence = get_user_presence($targetUserId);

    $stmt = $pdo->prepare("SELECT username, avatar FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    $user = $stmt->fetch();

    json_response([
        'user_id' => $targetUserId,
        'username' => $user['username'] ?? null,
        'status' => $presence['status'],
        'last_seen' => $presence['last_seen']
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_json_body();
    $status = $data['status'] ?? 'online';

    if (!in_array($status, ['online', 'away', 'offline'])) {
        json_response(['error' => 'invalid_status'], 400);
    }

    update_presence($userId, $status);

    json_response([
        'status' => 'updated',
        'presence' => $status
    ]);
}

json_response(['error' => 'method_not_allowed'], 405);
