<?php
/**
 * Helper functions: JWT, rate limiting, input validation, response helpers.
 */

/**
 * Generate a JWT access token
 */
function generate_jwt(array $payload): string {
    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_EXPIRY;
    $payloadEncoded = base64url_encode(json_encode($payload));
    $signature = base64url_encode(
        hash_hmac('sha256', "$header.$payloadEncoded", JWT_SECRET, true)
    );
    return "$header.$payloadEncoded.$signature";
}

/**
 * Verify and decode a JWT token
 */
function verify_jwt(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $payload, $signature] = $parts;
    $expectedSig = base64url_encode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    );

    if (!hash_equals($expectedSig, $signature)) return null;

    $data = json_decode(base64url_decode($payload), true);
    if (!$data || !isset($data['exp']) || $data['exp'] < time()) return null;

    return $data;
}

/**
 * Base64url encode
 */
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64url decode
 */
function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

/**
 * Generate a secure random token
 */
function generate_token(int $length = 64): string {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Get authenticated user from JWT (Authorization header)
 */
function get_auth_user(): ?array {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) return null;

    return verify_jwt($matches[1]);
}

/**
 * Require authentication, exit with 401 if missing
 */
function require_auth(): array {
    $user = get_auth_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'unauthorized', 'message' => 'Authentication required']);
        exit();
    }
    return $user;
}

/**
 * Sanitize input string
 */
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get JSON request body
 */
function get_json_body(): array {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

/**
 * Send a JSON response
 */
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Rate limiting - check and increment
 */
function check_rate_limit(string $identifier, string $action, int $maxHits = 30, int $windowSecs = 60): bool {
    global $pdo;

    $windowStart = date('Y-m-d H:i:s', time() - $windowSecs);

    // Clean old entries
    $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE window_start < ?");
    $stmt->execute([$windowStart]);

    // Check current hits
    $stmt = $pdo->prepare(
        "SELECT SUM(hits) as total FROM rate_limits WHERE identifier = ? AND action = ? AND window_start >= ?"
    );
    $stmt->execute([$identifier, $action, $windowStart]);
    $row = $stmt->fetch();

    if ($row && $row['total'] >= $maxHits) {
        return false; // Rate limited
    }

    // Increment or insert
    $stmt = $pdo->prepare(
        "INSERT INTO rate_limits (identifier, action, hits, window_start)
         VALUES (?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE hits = hits + 1"
    );
    $stmt->execute([$identifier, $action]);

    return true;
}

/**
 * Create a notification
 */
function create_notification(
    int $userId,
    string $type,
    ?int $referenceId = null,
    ?int $conversationId = null,
    ?int $messageId = null,
    ?int $senderId = null,
    ?string $title = null,
    ?string $body = null
): int {
    global $pdo;

    $stmt = $pdo->prepare(
        "INSERT INTO notifications (user_id, type, reference_id, conversation_id, message_id, sender_id, title, body)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $type, $referenceId, $conversationId, $messageId, $senderId, $title, $body]);
    return (int)$pdo->lastInsertId();
}

/**
 * Get user presence info
 */
function get_user_presence(int $userId): array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT status, last_seen FROM user_presence WHERE user_id = ?");
    $stmt->execute([$userId]);
    $presence = $stmt->fetch();

    if (!$presence) {
        $stmt = $pdo->prepare("INSERT INTO user_presence (user_id, status) VALUES (?, 'offline')");
        $stmt->execute([$userId]);
        return ['status' => 'offline', 'last_seen' => null];
    }

    return $presence;
}

/**
 * Update user presence
 */
function update_presence(int $userId, string $status = 'online'): void {
    global $pdo;

    $stmt = $pdo->prepare(
        "INSERT INTO user_presence (user_id, status, last_seen)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE status = ?, last_seen = NOW()"
    );
    $stmt->execute([$userId, $status, $status]);
}

/**
 * Get client IP address
 */
function get_client_ip(): string {
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ips = explode(',', $_SERVER[$h]);
            return trim($ips[0]);
        }
    }
    return '0.0.0.0';
}

/**
 * Check if two users are contacts
 */
function are_contacts(int $userId1, int $userId2): bool {
    global $pdo;

    $stmt = $pdo->prepare(
        "SELECT id FROM contacts
         WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)"
    );
    $stmt->execute([$userId1, $userId2, $userId2, $userId1]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Check if a user is blocked
 */
function is_blocked(int $userId, int $blockedByUserId): bool {
    global $pdo;

    $stmt = $pdo->prepare("SELECT id FROM blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->execute([$blockedByUserId, $userId]);
    return (bool)$stmt->fetchColumn();
}
