<?php
/**
 * Typing indicator
 * POST /api/typing.php
 * Authorization: Bearer <token>
 * Body: { conversation_id, typing: true/false }
 */

require_once __DIR__ . '/config.php';

$auth = require_auth();
$userId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method_not_allowed'], 405);
}

$data = get_json_body();
$conversationId = (int)($data['conversation_id'] ?? 0);
$isTyping = $data['typing'] ?? true;

if ($conversationId <= 0) {
    json_response(['error' => 'invalid_conversation'], 400);
}

// Return the event data for the frontend to broadcast
json_response([
    'status' => 'broadcast',
    'type' => 'typing',
    'user_id' => $userId,
    'username' => $auth['username'],
    'conversation_id' => $conversationId,
    'typing' => $isTyping
]);
