<?php
/**
 * Group management (add/remove members, update info, leave)
 * POST /api/group.php?action=add_member|remove_member|update|leave
 * Authorization: Bearer <token>
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$action = $_GET['action'] ?? '';
$data = get_json_body();
$conversationId = (int)($data['conversation_id'] ?? 0);

if ($conversationId <= 0) {
    json_response(['error' => 'invalid_conversation'], 400);
}

// Check conversation exists and is a group
$stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND type = 'group'");
$stmt->execute([$conversationId]);
$conversation = $stmt->fetch();

if (!$conversation) {
    json_response(['error' => 'not_found_or_not_group'], 404);
}

// Check user's role
$stmt = $pdo->prepare(
    "SELECT role FROM conversation_participants WHERE conversation_id = ? AND user_id = ?"
);
$stmt->execute([$conversationId, $userId]);
$participant = $stmt->fetch();

if (!$participant) {
    json_response(['error' => 'not_participant'], 403);
}

$isAdmin = $participant['role'] === 'admin' || (int)$conversation['created_by'] === $userId;

switch ($action) {
    case 'add_member':
        if (!$isAdmin) {
            json_response(['error' => 'not_admin'], 403);
        }
        $newMemberId = (int)($data['user_id'] ?? 0);
        if ($newMemberId <= 0) {
            json_response(['error' => 'invalid_user'], 400);
        }
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)"
        );
        $stmt->execute([$conversationId, $newMemberId]);

        // System message
        $stmt = $pdo->prepare(
            "INSERT INTO messages (conversation_id, sender_id, type, content)
             VALUES (?, ?, 'system', ?)"
        );
        $stmt->execute([$conversationId, $userId, "User $newMemberId was added to the group"]);

        json_response(['status' => 'added', 'user_id' => $newMemberId]);
        break;

    case 'remove_member':
        if (!$isAdmin) {
            json_response(['error' => 'not_admin'], 403);
        }
        $removeId = (int)($data['user_id'] ?? 0);
        if ($removeId <= 0 || $removeId === (int)$conversation['created_by']) {
            json_response(['error' => 'cannot_remove_creator'], 400);
        }
        $stmt = $pdo->prepare(
            "DELETE FROM conversation_participants WHERE conversation_id = ? AND user_id = ?"
        );
        $stmt->execute([$conversationId, $removeId]);

        $stmt = $pdo->prepare(
            "INSERT INTO messages (conversation_id, sender_id, type, content)
             VALUES (?, ?, 'system', ?)"
        );
        $stmt->execute([$conversationId, $userId, "User was removed from the group"]);

        json_response(['status' => 'removed']);
        break;

    case 'update':
        if (!$isAdmin) {
            json_response(['error' => 'not_admin'], 403);
        }
        $updates = [];
        $params = [];
        if (isset($data['name'])) {
            $updates[] = "name = ?";
            $params[] = sanitize($data['name']);
        }
        if (isset($data['avatar'])) {
            $updates[] = "avatar = ?";
            $params[] = sanitize($data['avatar']);
        }
        if (isset($data['description'])) {
            $updates[] = "description = ?";
            $params[] = sanitize($data['description']);
        }
        if (empty($updates)) {
            json_response(['error' => 'nothing_to_update'], 400);
        }
        $params[] = $conversationId;
        $stmt = $pdo->prepare("UPDATE conversations SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        json_response(['status' => 'updated']);
        break;

    case 'leave':
        $stmt = $pdo->prepare(
            "DELETE FROM conversation_participants WHERE conversation_id = ? AND user_id = ?"
        );
        $stmt->execute([$conversationId, $userId]);

        $stmt = $pdo->prepare(
            "INSERT INTO messages (conversation_id, sender_id, type, content)
             VALUES (?, ?, 'system', ?)"
        );
        $stmt->execute([$conversationId, $userId, "A user left the group"]);

        json_response(['status' => 'left']);
        break;

    default:
        json_response(['error' => 'invalid_action'], 400);
}
