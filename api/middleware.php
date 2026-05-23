<?php
/**
 * Middleware functions for auth, CSRF, rate limiting
 */

/**
 * CSRF token generation and validation
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Session auth (legacy support for existing PHP session system)
 */
function require_session_auth(): array {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'unauthorized', 'message' => 'Please log in first']);
        exit();
    }
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username']
    ];
}

/**
 * Dual auth: JWT (preferred) or session (fallback)
 */
function get_authenticated_user(): array {
    // Try JWT first
    $jwtUser = get_auth_user();
    if ($jwtUser) {
        update_presence($jwtUser['user_id'], 'online');
        return $jwtUser;
    }

    // Fall back to session
    return require_session_auth();
}
