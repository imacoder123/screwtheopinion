<?php
/**
 * Core configuration for the messaging platform.
 * JWT secret, CORS settings, upload limits, etc.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

date_default_timezone_set('UTC');

// JWT Configuration
define('JWT_SECRET', 'screwtheopinion_jwt_secret_key_2026_!@#$%');
define('JWT_EXPIRY', 3600);        // Access token: 1 hour
define('JWT_REFRESH_EXPIRY', 604800); // Refresh token: 7 days

// Paths
define('BASE_PATH', '/ScrewTheOpinion');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ensure upload directory exists
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
    @mkdir(UPLOAD_DIR . 'avatars/', 0755, true);
    @mkdir(UPLOAD_DIR . 'images/', 0755, true);
    @mkdir(UPLOAD_DIR . 'files/', 0755, true);
    @mkdir(UPLOAD_DIR . 'gifs/', 0755, true);
    @mkdir(UPLOAD_DIR . 'voice/', 0755, true);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
