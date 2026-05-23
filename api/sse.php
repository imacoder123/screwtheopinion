<?php
/**
 * Server-Sent Events endpoint for real-time notifications (fallback when WebSocket unavailable)
 * GET /api/sse.php
 * Authorization: Bearer <token> (query param: token=xxx)
 */

require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';

$payload = verify_jwt($token);
if (!$payload) {
    http_response_code(401);
    exit();
}

$userId = $payload['user_id'];

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}
ob_implicit_flush(true);

update_presence($userId, 'online');

$lastCheck = date('Y-m-d H:i:s');

// Send initial connection event
echo "data: " . json_encode(['type' => 'connected', 'user_id' => $userId]) . "\n\n";
flush();

while (true) {
    // Check for new notifications
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND created_at > ?"
    );
    $stmt->execute([$userId, $lastCheck]);
    $newCount = (int)$stmt->fetchColumn();

    if ($newCount > 0) {
        $stmt = $pdo->prepare(
            "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 AND created_at > ? ORDER BY created_at DESC"
        );
        $stmt->execute([$userId, $lastCheck]);
        $notifications = $stmt->fetchAll();

        echo "event: notification\n";
        echo "data: " . json_encode($notifications) . "\n\n";
        flush();

        $lastCheck = date('Y-m-d H:i:s');
    }

    // Send heartbeat every 15 seconds
    echo "event: heartbeat\n";
    echo "data: " . json_encode(['time' => time()]) . "\n\n";
    flush();

    // Check if client disconnected
    if (connection_aborted()) {
        update_presence($userId, 'offline');
        break;
    }

    sleep(5);
}
