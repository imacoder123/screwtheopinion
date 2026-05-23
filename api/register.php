<?php
/**
 * User Registration
 * POST /api/register.php
 * Body: { name, email, username, password, confirm_password }
 */

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$name = sanitize($data['name'] ?? '');
$email = sanitize($data['email'] ?? '');
$username = sanitize($data['username'] ?? '');
$password = $data['password'] ?? '';
$confirmPassword = $data['confirm_password'] ?? '';

// Validation
if (empty($name) || empty($email) || empty($username) || empty($password)) {
    json_response(['error' => 'missing_fields', 'message' => 'All fields are required'], 400);
}

if (!valid_email($email)) {
    json_response(['error' => 'invalid_email', 'message' => 'Please provide a valid email address'], 400);
}

if (strlen($username) < 3 || strlen($username) > 30) {
    json_response(['error' => 'invalid_username', 'message' => 'Username must be between 3 and 30 characters'], 400);
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    json_response(['error' => 'invalid_username', 'message' => 'Username can only contain letters, numbers, and underscores'], 400);
}

if (strlen($password) < 6) {
    json_response(['error' => 'weak_password', 'message' => 'Password must be at least 6 characters'], 400);
}

if ($password !== $confirmPassword) {
    json_response(['error' => 'password_mismatch', 'message' => 'Passwords do not match'], 400);
}

// Rate limiting
$ip = get_client_ip();
if (!check_rate_limit("register:$ip", 'register', 3, 3600)) {
    json_response(['error' => 'rate_limited', 'message' => 'Too many registration attempts'], 429);
}

// Check for existing user
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
$stmt->execute([$email, $username]);

if ($stmt->fetch()) {
    json_response(['error' => 'user_exists', 'message' => 'Email or username already exists'], 409);
}

// Hash password and create user
$hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $pdo->prepare(
    "INSERT INTO users (name, email, username, password) VALUES (?, ?, ?, ?)"
);
$stmt->execute([$name, $email, $username, $hashedPassword]);
$userId = (int)$pdo->lastInsertId();

// Generate tokens
$accessToken = generate_jwt([
    'user_id' => $userId,
    'username' => $username,
    'is_admin' => false
]);

$refreshToken = generate_token();

// Store session
$deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$stmt = $pdo->prepare(
    "INSERT INTO user_sessions (user_id, access_token, refresh_token, device_info, ip_address, last_activity, expires_at)
     VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))"
);
$stmt->execute([$userId, $accessToken, $refreshToken, $deviceInfo, $ip]);

// Update presence
update_presence($userId, 'online');

// Legacy session
session_start();
$_SESSION['user_id'] = $userId;
$_SESSION['username'] = $username;

json_response([
    'access_token' => $accessToken,
    'refresh_token' => $refreshToken,
    'user' => [
        'id' => $userId,
        'username' => $username,
        'name' => $name,
        'email' => $email,
        'avatar' => null,
        'bio' => null
    ]
], 201);
