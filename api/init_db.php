<?php
/**
 * Database initialization script
 * Run this once to create all required tables.
 * Access: /api/init_db.php?key=setup2026
 */

$setupKey = $_GET['key'] ?? '';
if ($setupKey !== 'setup2026') {
    die('Invalid setup key');
}

require_once __DIR__ . '/db.php';

$sql = file_get_contents(__DIR__ . '/schema.sql');

if ($pdo->exec($sql) !== false) {
    echo "<h2>Database initialized successfully!</h2>";
    echo "<p>All tables created.</p>";
} else {
    echo "<h2>Error initializing database</h2>";
    echo "<p>" . $pdo->errorInfo()[2] . "</p>";
}
