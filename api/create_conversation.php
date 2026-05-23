<?php
/**
 * Create a new conversation (group chat)
 * POST /api/create_conversation.php
 * Authorization: Bearer <token>
 * Body: { type: 'group', name: 'Group Name', members: [user_id, ...] }
 * For direct: just ensure it exists or create
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$type = $data['type'] ?? 'direct';
$name = sanitize($data['name'] ?? '');
$memberIds = $data['members'] ?? [];

if ($type === 'direct') {
    $otherUserId = (int)($data['user_id'] ?? 0);

    if ($otherUserId <= 0 || $otherUserId === $userId) {
        json_response(['error' => 'invalid_user'], 400);
    }

    // Check if conversation already exists
    $stmt = $pdo->prepare(
        "SELECT c.id FROM conversations c
         JOIN conversation_participants cp1 ON cp1.conversation_id = c.id AND cp1.user_id = ?
         JOIN conversation_participants cp2 ON cp2.conversation_id = c.id AND cp2.user_id = ?
         WHERE c.type = 'direct'"
    );
    $stmt->execute([$userId, $otherUserId]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        json_response([
            'conversation_id' => (int)$existing,
            'existing' => true
        ]);
    }

    // Create new direct conversation
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO conversations (type, created_by) VALUES ('direct', ?)");
        $stmt->execute([$userId]);
        $convId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)");
        $stmt->execute([$convId, $userId, $convId, $otherUserId]);

        $pdo->commit();
        json_response(['conversation_id' => $convId, 'existing' => false], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_response(['error' => 'server_error'], 500);
    }
}

if ($type === 'group') {
    if (empty($name)) {
        json_response(['error' => 'name_required', 'message' => 'Group name is required'], 400);
    }

    if (count($memberIds) < 1) {
        json_response(['error' => 'members_required', 'message' => 'At least one member is required'], 400);
    }

    // Add the creator to members
    $memberIds[] = $userId;
    $memberIds = array_unique(array_map('intval', $memberIds));

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO conversations (type, name, created_by) VALUES ('group', ?, ?)"
        );
        $stmt->execute([$name, $userId]);
        $convId = (int)$pdo->lastInsertId();

        $insertStmt = $pdo->prepare(
            "INSERT INTO conversation_participants (conversation_id, user_id, role)
             VALUES (?, ?, CASE WHEN ? = ? THEN 'admin' ELSE 'member' END)"
        );
        foreach ($memberIds as $mid) {
            $insertStmt->execute([$convId, $mid, $mid, $userId]);
        }

        $pdo->commit();
        json_response(['conversation_id' => $convId], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_response(['error' => 'server_error'], 500);
    }
}

json_response(['error' => 'invalid_type'], 400);
