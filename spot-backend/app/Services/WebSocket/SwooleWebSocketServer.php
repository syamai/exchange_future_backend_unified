<?php

namespace App\Services\WebSocket;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;
use Swoole\Http\Request;
use Swoole\Timer;

/**
 * Swoole WebSocket Server for real-time spot trading data.
 *
 * Channels:
 * - orderbook:{symbol} - Order book updates
 * - trades:{symbol} - Trade executions
 * - ticker:{symbol} - Price ticker updates
 * - user:{userId} - User-specific updates (orders, balances)
 */
class SwooleWebSocketServer
{
    private Server $server;
    private string $host;
    private int $port;

    // Connection tracking
    private array $connections = [];           // fd => ['subscriptions' => [...], 'userId' => null]
    private array $subscriptions = [];         // channel => [fd1, fd2, ...]

    // Statistics
    private int $totalConnections = 0;
    private int $totalMessages = 0;
    private float $startTime;

    // Redis pub/sub channels
    private const REDIS_CHANNEL_PREFIX = 'spot:ws:';

    public function __construct(string $host = '0.0.0.0', int $port = 9502)
    {
        $this->host = $host;
        $this->port = $port;
        $this->startTime = microtime(true);
    }

    /**
     * Start the WebSocket server.
     */
    public function start(): void
    {
        $this->server = new Server($this->host, $this->port);

        $this->server->set([
            'worker_num' => 4,
            'max_connection' => 10000,
            'heartbeat_check_interval' => 60,
            'heartbeat_idle_time' => 120,
            'open_websocket_ping_frame' => true,
            'open_websocket_pong_frame' => true,
        ]);

        $this->server->on('Start', [$this, 'onStart']);
        $this->server->on('Open', [$this, 'onOpen']);
        $this->server->on('Message', [$this, 'onMessage']);
        $this->server->on('Close', [$this, 'onClose']);
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);

        Log::info("SwooleWebSocketServer: Starting on {$this->host}:{$this->port}");
        $this->server->start();
    }

    /**
     * Server start callback.
     */
    public function onStart(Server $server): void
    {
        Log::info("SwooleWebSocketServer: Master started, PID: {$server->master_pid}");
    }

    /**
     * Worker start callback - set up Redis subscriber.
     */
    public function onWorkerStart(Server $server, int $workerId): void
    {
        if ($workerId === 0) {
            // Only first worker handles Redis pub/sub
            $this->startRedisSubscriber();
            $this->startStatsTimer();
        }
    }

    /**
     * New connection opened.
     */
    public function onOpen(Server $server, Request $request): void
    {
        $fd = $request->fd;
        $this->connections[$fd] = [
            'subscriptions' => [],
            'userId' => null,
            'connectedAt' => time(),
        ];
        $this->totalConnections++;

        Log::debug("SwooleWebSocketServer: Connection opened, fd: {$fd}");

        // Send welcome message
        $this->send($fd, [
            'type' => 'connected',
            'message' => 'Welcome to Spot WebSocket',
            'timestamp' => time(),
        ]);
    }

    /**
     * Message received from client.
     */
    public function onMessage(Server $server, Frame $frame): void
    {
        $fd = $frame->fd;
        $this->totalMessages++;

        try {
            $data = json_decode($frame->data, true);

            if (!$data || !isset($data['action'])) {
                $this->sendError($fd, 'Invalid message format');
                return;
            }

            switch ($data['action']) {
                case 'subscribe':
                    $this->handleSubscribe($fd, $data);
                    break;

                case 'unsubscribe':
                    $this->handleUnsubscribe($fd, $data);
                    break;

                case 'auth':
                    $this->handleAuth($fd, $data);
                    break;

                case 'ping':
                    $this->send($fd, ['type' => 'pong', 'timestamp' => time()]);
                    break;

                default:
                    $this->sendError($fd, 'Unknown action: ' . $data['action']);
            }
        } catch (\Exception $e) {
            Log::error("SwooleWebSocketServer: Error processing message", [
                'fd' => $fd,
                'error' => $e->getMessage(),
            ]);
            $this->sendError($fd, 'Internal error');
        }
    }

    /**
     * Connection closed.
     */
    public function onClose(Server $server, int $fd): void
    {
        // Remove from all subscriptions
        if (isset($this->connections[$fd])) {
            foreach ($this->connections[$fd]['subscriptions'] as $channel) {
                $this->removeFromSubscription($channel, $fd);
            }
            unset($this->connections[$fd]);
        }

        Log::debug("SwooleWebSocketServer: Connection closed, fd: {$fd}");
    }

    /**
     * Handle subscribe request.
     */
    private function handleSubscribe(int $fd, array $data): void
    {
        $channels = $data['channels'] ?? [];

        if (empty($channels)) {
            $this->sendError($fd, 'No channels specified');
            return;
        }

        $subscribed = [];
        foreach ($channels as $channel) {
            // Validate channel format
            if (!$this->isValidChannel($channel)) {
                continue;
            }

            // Check if user channel requires auth
            if (str_starts_with($channel, 'user:') && !$this->connections[$fd]['userId']) {
                $this->sendError($fd, 'Authentication required for user channels');
                continue;
            }

            $this->addToSubscription($channel, $fd);
            $this->connections[$fd]['subscriptions'][] = $channel;
            $subscribed[] = $channel;

            // Send initial data for the channel
            $this->sendInitialData($fd, $channel);
        }

        $this->send($fd, [
            'type' => 'subscribed',
            'channels' => $subscribed,
        ]);
    }

    /**
     * Handle unsubscribe request.
     */
    private function handleUnsubscribe(int $fd, array $data): void
    {
        $channels = $data['channels'] ?? [];

        $unsubscribed = [];
        foreach ($channels as $channel) {
            $this->removeFromSubscription($channel, $fd);
            $this->connections[$fd]['subscriptions'] = array_filter(
                $this->connections[$fd]['subscriptions'],
                fn($c) => $c !== $channel
            );
            $unsubscribed[] = $channel;
        }

        $this->send($fd, [
            'type' => 'unsubscribed',
            'channels' => $unsubscribed,
        ]);
    }

    /**
     * Handle authentication.
     */
    private function handleAuth(int $fd, array $data): void
    {
        $token = $data['token'] ?? null;

        if (!$token) {
            $this->sendError($fd, 'Token required');
            return;
        }

        // Validate token (simple implementation - should use proper JWT validation)
        try {
            $userId = $this->validateToken($token);

            if ($userId) {
                $this->connections[$fd]['userId'] = $userId;
                $this->send($fd, [
                    'type' => 'authenticated',
                    'userId' => $userId,
                ]);
            } else {
                $this->sendError($fd, 'Invalid token');
            }
        } catch (\Exception $e) {
            $this->sendError($fd, 'Authentication failed');
        }
    }

    /**
     * Validate authentication token.
     */
    private function validateToken(string $token): ?int
    {
        // Try to find user by API token
        try {
            $tokenRecord = \DB::table('oauth_access_tokens')
                ->where('id', $token)
                ->where('revoked', false)
                ->first();

            if ($tokenRecord) {
                return (int) $tokenRecord->user_id;
            }
        } catch (\Exception $e) {
            Log::error("Token validation error: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Check if channel name is valid.
     */
    private function isValidChannel(string $channel): bool
    {
        $validPrefixes = ['orderbook:', 'trades:', 'ticker:', 'user:', 'kline:'];

        foreach ($validPrefixes as $prefix) {
            if (str_starts_with($channel, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add connection to subscription.
     */
    private function addToSubscription(string $channel, int $fd): void
    {
        if (!isset($this->subscriptions[$channel])) {
            $this->subscriptions[$channel] = [];
        }

        if (!in_array($fd, $this->subscriptions[$channel])) {
            $this->subscriptions[$channel][] = $fd;
        }
    }

    /**
     * Remove connection from subscription.
     */
    private function removeFromSubscription(string $channel, int $fd): void
    {
        if (isset($this->subscriptions[$channel])) {
            $this->subscriptions[$channel] = array_filter(
                $this->subscriptions[$channel],
                fn($f) => $f !== $fd
            );

            if (empty($this->subscriptions[$channel])) {
                unset($this->subscriptions[$channel]);
            }
        }
    }

    /**
     * Send initial data when subscribing to a channel.
     */
    private function sendInitialData(int $fd, string $channel): void
    {
        try {
            if (str_starts_with($channel, 'orderbook:')) {
                $symbol = substr($channel, strlen('orderbook:'));
                $data = $this->getOrderBookSnapshot($symbol);
                if ($data) {
                    $this->send($fd, [
                        'type' => 'orderbook',
                        'channel' => $channel,
                        'data' => $data,
                    ]);
                }
            } elseif (str_starts_with($channel, 'ticker:')) {
                $symbol = substr($channel, strlen('ticker:'));
                $data = $this->getTickerData($symbol);
                if ($data) {
                    $this->send($fd, [
                        'type' => 'ticker',
                        'channel' => $channel,
                        'data' => $data,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Error sending initial data: " . $e->getMessage());
        }
    }

    /**
     * Get order book snapshot from Redis cache.
     */
    private function getOrderBookSnapshot(string $symbol): ?array
    {
        $key = "spot:orderbook:{$symbol}";
        $data = Redis::get($key);
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Get ticker data from Redis cache.
     */
    private function getTickerData(string $symbol): ?array
    {
        $key = "spot:ticker:{$symbol}";
        $data = Redis::get($key);
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Broadcast message to all subscribers of a channel.
     */
    public function broadcast(string $channel, array $data): void
    {
        if (!isset($this->subscriptions[$channel])) {
            return;
        }

        $message = json_encode([
            'type' => $this->getTypeFromChannel($channel),
            'channel' => $channel,
            'data' => $data,
            'timestamp' => time(),
        ]);

        foreach ($this->subscriptions[$channel] as $fd) {
            if ($this->server->isEstablished($fd)) {
                $this->server->push($fd, $message);
            }
        }
    }

    /**
     * Get message type from channel name.
     */
    private function getTypeFromChannel(string $channel): string
    {
        if (str_starts_with($channel, 'orderbook:')) return 'orderbook';
        if (str_starts_with($channel, 'trades:')) return 'trade';
        if (str_starts_with($channel, 'ticker:')) return 'ticker';
        if (str_starts_with($channel, 'user:')) return 'user';
        if (str_starts_with($channel, 'kline:')) return 'kline';
        return 'unknown';
    }

    /**
     * Send message to specific connection.
     */
    private function send(int $fd, array $data): void
    {
        if ($this->server->isEstablished($fd)) {
            $this->server->push($fd, json_encode($data));
        }
    }

    /**
     * Send error message.
     */
    private function sendError(int $fd, string $message): void
    {
        $this->send($fd, [
            'type' => 'error',
            'message' => $message,
            'timestamp' => time(),
        ]);
    }

    /**
     * Start Redis pub/sub subscriber in coroutine.
     */
    private function startRedisSubscriber(): void
    {
        go(function () {
            $redis = new \Redis();
            $redis->connect(
                config('database.redis.default.host'),
                config('database.redis.default.port')
            );

            if ($password = config('database.redis.default.password')) {
                $redis->auth($password);
            }

            $redis->setOption(\Redis::OPT_READ_TIMEOUT, -1);

            $redis->psubscribe([self::REDIS_CHANNEL_PREFIX . '*'], function ($redis, $pattern, $channel, $message) {
                try {
                    $data = json_decode($message, true);
                    $wsChannel = str_replace(self::REDIS_CHANNEL_PREFIX, '', $channel);
                    $this->broadcast($wsChannel, $data);
                } catch (\Exception $e) {
                    Log::error("Redis subscriber error: " . $e->getMessage());
                }
            });
        });
    }

    /**
     * Start stats logging timer.
     */
    private function startStatsTimer(): void
    {
        Timer::tick(60000, function () {
            $uptime = round(microtime(true) - $this->startTime);
            $connections = count($this->connections);
            $subscriptions = count($this->subscriptions);

            Log::info("SwooleWebSocketServer Stats", [
                'uptime' => $uptime . 's',
                'connections' => $connections,
                'channels' => $subscriptions,
                'total_connections' => $this->totalConnections,
                'total_messages' => $this->totalMessages,
            ]);
        });
    }

    /**
     * Get server instance.
     */
    public function getServer(): Server
    {
        return $this->server;
    }
}
