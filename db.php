<?php
$DB_HOST = getenv('DB_HOST');
$DB_PORT = getenv('DB_PORT') ?: 5432;
$DB_NAME = getenv('DB_NAME');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');

try {
    $pdo = new PDO(
        "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
