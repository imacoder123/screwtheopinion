<?php
/**
 * Authentication handler - Login with JWT token response
 * POST /api/auth.php
 * Body: { username, password }
 * Returns: { access_token, refresh_token, user }
 */

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$username = sanitize($data['username'] ?? '');
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    json_response(['error' => 'missing_fields', 'message' => 'Username and password required'], 400);
}

// Find user by username or email
$stmt = $pdo->prepare(
    "SELECT id, username, name, email, password, avatar, bio, is_admin, is_verified
     FROM users WHERE username = ? OR email = ?"
);
$stmt->execute([$username, $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    json_response(['error' => 'invalid_credentials', 'message' => 'Invalid username or password'], 401);
}

// Rate limiting check
$ip = get_client_ip();
if (!check_rate_limit("login:$ip", 'login', 5, 300)) {
    json_response(['error' => 'rate_limited', 'message' => 'Too many login attempts. Try again in 5 minutes.'], 429);
}

// Generate tokens
$accessToken = generate_jwt([
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'is_admin' => (bool)$user['is_admin']
]);

$refreshToken = generate_token();

// Store session in database
$deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$stmt = $pdo->prepare(
    "INSERT INTO user_sessions (user_id, access_token, refresh_token, device_info, ip_address, last_activity, expires_at)
     VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))"
);
$stmt->execute([
    $user['id'],
    $accessToken,
    $refreshToken,
    $deviceInfo,
    $ip
]);

// Update presence
update_presence($user['id'], 'online');

// Also set legacy session for backward compatibility
session_start();
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

json_response([
    'access_token' => $accessToken,
    'refresh_token' => $refreshToken,
    'user' => [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'name' => $user['name'],
        'email' => $user['email'],
        'avatar' => $user['avatar'],
        'bio' => $user['bio'],
        'is_admin' => (bool)$user['is_admin'],
        'is_verified' => (bool)$user['is_verified']
    ]
]);
