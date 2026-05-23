<?php
/**
 * WebSocket handler for real-time messaging (Ratchet)
 * Handles: typing, presence, new messages, reactions, read receipts
 *
 * Run: php api/ratchet_server.php
 * Requires: composer require cboden/ratchet
 */

namespace ScrewTheOpinion;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ChatSocket implements MessageComponentInterface
{
    // Map of user_id => connection resourceId
    protected $clients;
    // Map of resourceId => user_id
    protected $userMap;
    // Map of resourceId => connection info
    protected $connectionInfo;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        $this->userMap = [];
        $this->connectionInfo = [];
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $connId = $conn->resourceId;
        $this->connectionInfo[$connId] = [
            'user_id' => null,
            'username' => null,
            'authenticated' => false,
        ];

        echo "[Open] New connection: #{$connId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['type'])) {
            $from->send(json_encode(['error' => 'invalid_message']));
            return;
        }

        $connId = $from->resourceId;

        switch ($data['type']) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;

            case 'typing':
                $this->handleTyping($from, $data);
                break;

            case 'message':
                $this->handleMessage($from, $data);
                break;

            case 'presence':
                $this->handlePresence($from, $data);
                break;

            case 'read_receipt':
                $this->handleReadReceipt($from, $data);
                break;

            case 'reaction':
                $this->handleReaction($from, $data);
                break;

            case 'ping':
                $from->send(json_encode(['type' => 'pong']));
                break;

            default:
                $from->send(json_encode(['error' => 'unknown_type', 'type' => $data['type']]));
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $connId = $conn->resourceId;
        $userId = $this->userMap[$connId] ?? null;

        $this->clients->detach($conn);
        unset($this->userMap[$connId]);
        unset($this->connectionInfo[$connId]);

        if ($userId) {
            // Check if user has other active connections
            $stillConnected = false;
            foreach ($this->userMap as $cid => $uid) {
                if ($uid === $userId) {
                    $stillConnected = true;
                    break;
                }
            }
            if (!$stillConnected) {
                $this->broadcast([
                    'type' => 'presence',
                    'user_id' => $userId,
                    'status' => 'offline'
                ]);
            }
        }

        echo "[Close] Connection #{$connId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "[Error] #{$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Authenticate a connection with JWT
     */
    private function handleAuth(ConnectionInterface $conn, array $data)
    {
        $token = $data['token'] ?? '';
        $connId = $conn->resourceId;

        // Verify the JWT (re-use your verify_jwt function)
        require_once __DIR__ . '/helpers.php';
        $payload = verify_jwt($token);

        if (!$payload) {
            $conn->send(json_encode(['type' => 'auth', 'status' => 'error', 'message' => 'Invalid token']));
            return;
        }

        $userId = $payload['user_id'];
        $username = $payload['username'];

        $this->userMap[$connId] = $userId;
        $this->connectionInfo[$connId] = [
            'user_id' => $userId,
            'username' => $username,
            'authenticated' => true,
        ];

        // Broadcast user as online
        $this->broadcast([
            'type' => 'presence',
            'user_id' => $userId,
            'username' => $username,
            'status' => 'online'
        ], $connId);

        // Send confirmation
        $conn->send(json_encode([
            'type' => 'auth',
            'status' => 'ok',
            'user_id' => $userId,
            'username' => $username
        ]));

        // Send current online users
        $onlineUsers = [];
        foreach ($this->userMap as $cid => $uid) {
            if ($uid !== $userId) {
                $onlineUsers[$uid] = [
                    'user_id' => $uid,
                    'username' => $this->connectionInfo[$cid]['username'] ?? 'Unknown'
                ];
            }
        }
        $conn->send(json_encode([
            'type' => 'online_users',
            'users' => array_values($onlineUsers)
        ]));

        echo "[Auth] User #{$userId} ({$username}) authenticated on connection #{$connId}\n";
    }

    /**
     * Handle typing indicators
     */
    private function handleTyping(ConnectionInterface $from, array $data)
    {
        $connId = $from->resourceId;
        $userId = $this->userMap[$connId] ?? null;
        $username = $this->connectionInfo[$connId]['username'] ?? 'Unknown';

        if (!$userId) {
            $from->send(json_encode(['error' => 'not_authenticated']));
            return;
        }

        $conversationId = $data['conversation_id'] ?? 0;
        $isTyping = $data['typing'] ?? true;

        $this->broadcastToConversation($conversationId, [
            'type' => 'typing',
            'user_id' => $userId,
            'username' => $username,
            'conversation_id' => $conversationId,
            'typing' => $isTyping
        ], $from);
    }

    /**
     * Handle new message broadcast
     */
    private function handleMessage(ConnectionInterface $from, array $data)
    {
        $connId = $from->resourceId;
        $userId = $this->userMap[$connId] ?? null;

        if (!$userId) {
            $from->send(json_encode(['error' => 'not_authenticated']));
            return;
        }

        $this->broadcastToConversation(
            $data['conversation_id'] ?? 0,
            array_merge($data, ['type' => 'message']),
            $from
        );
    }

    /**
     * Handle presence updates
     */
    private function handlePresence(ConnectionInterface $from, array $data)
    {
        $connId = $from->resourceId;
        $userId = $this->userMap[$connId] ?? null;

        if (!$userId) return;

        $this->broadcast([
            'type' => 'presence',
            'user_id' => $userId,
            'status' => $data['status'] ?? 'online'
        ], $connId);
    }

    /**
     * Handle read receipts
     */
    private function handleReadReceipt(ConnectionInterface $from, array $data)
    {
        $connId = $from->resourceId;
        $userId = $this->userMap[$connId] ?? null;

        if (!$userId) return;

        $this->broadcastToConversation(
            $data['conversation_id'] ?? 0,
            [
                'type' => 'read_receipt',
                'user_id' => $userId,
                'conversation_id' => $data['conversation_id'] ?? 0,
                'message_id' => $data['message_id'] ?? 0,
                'status' => 'read'
            ],
            $from
        );
    }

    /**
     * Handle reactions broadcast
     */
    private function handleReaction(ConnectionInterface $from, array $data)
    {
        $connId = $from->resourceId;
        $userId = $this->userMap[$connId] ?? null;

        if (!$userId) return;

        $this->broadcastToConversation(
            $data['conversation_id'] ?? 0,
            $data,
            $from
        );
    }

    /**
     * Broadcast to all connections of users who are participants in a conversation
     */
    private function broadcastToConversation(int $conversationId, array $data, ?ConnectionInterface $exclude = null)
    {
        // In a production system, you'd look up all participants of the conversation
        // and send only to those connected. For simplicity, broadcast to all.
        $this->broadcast($data, $exclude);
    }

    /**
     * Broadcast to all connected clients (optionally excluding one)
     */
    private function broadcast(array $data, ?ConnectionInterface $exclude = null)
    {
        $message = json_encode($data);

        foreach ($this->clients as $client) {
            if ($exclude && $client === $exclude) continue;
            $client->send($message);
        }
    }
}
