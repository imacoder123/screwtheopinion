<?php
/**
 * Refresh access token using refresh token
 * POST /api/refresh.php
 * Body: { refresh_token }
 */

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$refreshToken = $data['refresh_token'] ?? '';

if (empty($refreshToken)) {
    json_response(['error' => 'missing_token', 'message' => 'Refresh token required'], 400);
}

// Find the session
$stmt = $pdo->prepare(
    "SELECT s.*, u.username, u.is_admin FROM user_sessions s
     JOIN users u ON u.id = s.user_id
     WHERE s.refresh_token = ? AND s.is_revoked = 0 AND s.expires_at > NOW()"
);
$stmt->execute([$refreshToken]);
$session = $stmt->fetch();

if (!$session) {
    json_response(['error' => 'invalid_token', 'message' => 'Invalid or expired refresh token'], 401);
}

// Revoke old session
$stmt = $pdo->prepare("UPDATE user_sessions SET is_revoked = 1 WHERE id = ?");
$stmt->execute([$session['id']]);

// Generate new tokens
$newAccessToken = generate_jwt([
    'user_id' => (int)$session['user_id'],
    'username' => $session['username'],
    'is_admin' => (bool)$session['is_admin']
]);

$newRefreshToken = generate_token();

// Create new session
$stmt = $pdo->prepare(
    "INSERT INTO user_sessions (user_id, access_token, refresh_token, device_info, ip_address, last_activity, expires_at)
     VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))"
);
$stmt->execute([
    $session['user_id'],
    $newAccessToken,
    $newRefreshToken,
    $session['device_info'],
    get_client_ip()
]);

json_response([
    'access_token' => $newAccessToken,
    'refresh_token' => $newRefreshToken
]);
