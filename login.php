<?php
session_start();
require_once __DIR__ . '/api/config.php';

$isJson = ($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json' || !empty($_SERVER['HTTP_AUTHORIZATION']);

if ($isJson) {
    // JWT-based API login
    header('Content-Type: application/json');

    $data = get_json_body();
    $username = sanitize($data['username'] ?? '');
    $password = $data['password'] ?? '';
} else {
    // Legacy form-based login
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
}

if (empty($username) || empty($password)) {
    if ($isJson) {
        echo json_encode(['error' => 'missing_fields', 'message' => 'Username and password required']);
    } else {
        echo "ACCESS FAILURE.\nCredentials required.";
    }
    exit();
}

// Find user by username or email
$stmt = $pdo->prepare("SELECT id, username, name, email, password, avatar, bio, is_admin, is_verified FROM users WHERE username = ? OR email = ?");
$stmt->execute([$username, $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    if ($isJson) {
        echo json_encode(['error' => 'invalid_credentials', 'message' => 'Invalid username or password']);
    } else {
        echo "ACCESS FAILURE.\nYour authorization code does not match any known operative.\n(basically ur password is wrong, try again)";
    }
    exit();
}

// Generate JWT
$accessToken = generate_jwt([
    'user_id' => (int)$user['id'],
    'username' => $user['username'],
    'is_admin' => (bool)$user['is_admin']
]);

$refreshToken = generate_token();

// Store session
$deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$ip = get_client_ip();

$stmt = $pdo->prepare(
    "INSERT INTO user_sessions (user_id, access_token, refresh_token, device_info, ip_address, last_activity, expires_at)
     VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))"
);
$stmt->execute([$user['id'], $accessToken, $refreshToken, $deviceInfo, $ip]);

// Update presence
update_presence($user['id'], 'online');

// Set legacy session
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

if ($isJson) {
    echo json_encode([
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
} else {
    header("Location: dashboard.php");
    exit();
}
