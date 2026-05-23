<?php
/**
 * Logout - revoke all sessions for the authenticated user
 * POST /api/logout.php
 * Authorization: Bearer <token>
 * Body (optional): { all_devices: true }
 */

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$auth = get_auth_user();

if ($auth) {
    $data = get_json_body();
    $allDevices = $data['all_devices'] ?? false;

    if ($allDevices) {
        // Revoke all sessions
        $stmt = $pdo->prepare("UPDATE user_sessions SET is_revoked = 1 WHERE user_id = ?");
        $stmt->execute([$auth['user_id']]);
    } else {
        // Revoke current session via token
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = str_replace('Bearer ', '', $authHeader);
        $stmt = $pdo->prepare("UPDATE user_sessions SET is_revoked = 1 WHERE access_token = ? AND user_id = ?");
        $stmt->execute([$token, $auth['user_id']]);
    }

    update_presence($auth['user_id'], 'offline');
}

// Also destroy legacy session
session_start();
session_destroy();

json_response(['status' => 'logged_out']);
