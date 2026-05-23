<?php
/**
 * Search users by username (real-time suggestions)
 * GET /api/search_users.php?q=<query>
 * Authorization: Bearer <token>
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$currentUserId = $auth['user_id'];

$query = sanitize($_GET['q'] ?? '');

if (strlen($query) < 1) {
    json_response([]);
}

$searchTerm = "%$query%";

// Search users excluding self and blocked users
$stmt = $pdo->prepare(
    "SELECT u.id, u.username, u.name, u.avatar, u.is_verified,
            CASE WHEN fr.status IS NOT NULL THEN fr.status ELSE NULL END as request_status,
            CASE WHEN c.id IS NOT NULL THEN 'contacts' ELSE NULL END as relationship
     FROM users u
     LEFT JOIN friend_requests fr ON (
         (fr.sender_id = ? AND fr.receiver_id = u.id) OR
         (fr.receiver_id = ? AND fr.sender_id = u.id)
     )
     LEFT JOIN contacts c ON (
         (c.user1_id = ? AND c.user2_id = u.id) OR
         (c.user2_id = ? AND c.user1_id = u.id)
     )
     LEFT JOIN blocks b ON (b.blocker_id = u.id AND b.blocked_id = ?) OR (b.blocker_id = ? AND b.blocked_id = u.id)
     WHERE u.id != ? AND (u.username LIKE ? OR u.name LIKE ?)
       AND b.id IS NULL
     ORDER BY u.username ASC
     LIMIT 20"
);
$stmt->execute([$currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId, $searchTerm, $searchTerm]);
$users = $stmt->fetchAll();

// Add presence info
foreach ($users as &$user) {
    if ($user['request_status'] === 'accepted') {
        $user['request_status'] = null;
        $user['relationship'] = 'contacts';
    }
    $presence = get_user_presence($user['id']);
    $user['presence'] = $presence['status'];
    $user['last_seen'] = $presence['last_seen'];
}

json_response($users);
