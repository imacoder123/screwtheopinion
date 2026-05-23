<?php
/**
 * Get and manage notifications
 * GET /api/notifications.php - Get notifications
 * POST /api/notifications.php - Mark as read
 * Authorization: Bearer <token>
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $unreadOnly = ($_GET['unread_only'] ?? 'false') === 'true';

    $sql = "SELECT n.*, u.username as sender_username, u.avatar as sender_avatar
            FROM notifications n
            LEFT JOIN users u ON u.id = n.sender_id
            WHERE n.user_id = ?";

    $params = [$userId];

    if ($unreadOnly) {
        $sql .= " AND n.is_read = 0";
    }

    $sql .= " ORDER BY n.created_at DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();

    // Get unread count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $unreadCount = (int)$stmt->fetchColumn();

    json_response([
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_json_body();
    $notificationId = isset($data['notification_id']) ? (int)$data['notification_id'] : null;

    if ($notificationId) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
    } else {
        // Mark all as read
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
    }

    json_response(['status' => 'updated']);
}

json_response(['error' => 'method_not_allowed'], 405);
