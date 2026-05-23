<?php
/**
 * Ratchet WebSocket Server for real-time messaging
 *
 * Usage:
 *   composer require cboden/ratchet
 *   php api/ratchet_server.php
 *
 * Clients connect to: ws://localhost:8080
 */

require __DIR__ . '/../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use ScrewTheOpinion\ChatSocket;

$port = (int)($argv[1] ?? 8080);

echo "============================================\n";
echo "  ScrewTheOpinion WebSocket Server\n";
echo "  Listening on port: {$port}\n";
echo "============================================\n\n";

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatSocket()
        )
    ),
    $port
);

$server->run();
