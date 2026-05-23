<?php
/**
 * Database connection with PDO.
 * Supports both Docker environment variables and shared hosting config.
 */

$DB_HOST = getenv('DB_HOST') ?: "sql308.infinityfree.com";
$DB_USER = getenv('DB_USER') ?: "if0_41839085";
$DB_PASS = getenv('DB_PASS') ?: "vSwuD6ks0pi";
$DB_NAME = getenv('DB_NAME') ?: "if0_41839085_dbdbdv";

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "database_connection_failed"]);
    exit();
}

// Legacy mysqli connection for backward compatibility
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "database_connection_failed"]);
    exit();
}
$conn->set_charset("utf8mb4");
