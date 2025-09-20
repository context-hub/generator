<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server\Swoole;

use Butschster\ContextGenerator\McpServer\Config\SwooleTransportConfig;
use Butschster\ContextGenerator\McpServer\Server\MessageParser;
use Butschster\ContextGenerator\McpServer\Server\Transport\Swoole\SseConnectionManager;
use Butschster\ContextGenerator\McpServer\Server\Transport\Swoole\SwooleTransport;
use Mcp\Server\HttpServerRunner;
use Mcp\Server\InitializationOptions;
use Mcp\Server\Server as McpServer;
use Mcp\Server\Transport\Http\HttpMessage;
use Mcp\Server\Transport\Http\InMemorySessionStore;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Timer;

/**
 * Enhanced Swoole-based server runner with SSE and advanced lifecycle management
 */
final class SwooleServerRunner extends HttpServerRunner
{
    private readonly SwooleTransport $transport;
    private ?int $heartbeatTimer = null;
    private ?int $statsTimer = null;
    private array $stats = [
        'requests_total' => 0,
        'sse_connections' => 0,
        'sessions_active' => 0,
        'start_time' => 0,
    ];

    public function __construct(
        McpServer $server,
        InitializationOptions $initOptions,
        private readonly SwooleTransportConfig $config,
        MessageParser $messageParser,
        LoggerInterface $logger,
    ) {
        // Create our enhanced transport
        $this->transport = new SwooleTransport(
            logger: $logger,
            messageParser: $messageParser,
            config: $config,
            sessionStore: new InMemorySessionStore(),
            sseManager: new SseConnectionManager(
                logger: $logger,
                maxConnections: $config->maxSseConnections,
                heartbeatInterval: $config->sseHeartbeatInterval,
                connectionTimeout: $config->connectionTimeout,
            ),
        );

        // Initialize stats
        $this->stats['start_time'] = \time();

        // Set up event forwarding from transport to server
        $this->transport->on('client_connected', function (string $sessionId) use ($server): void {
            $this->logger->info('Client connected via SSE', ['session_id' => $sessionId]);
            // Forward to server's event emitter if available
            if (\method_exists($server, 'emit')) {
                $server->emit('client_connected', [$sessionId]);
            }
        });

        $this->transport->on('client_disconnected', function (string $sessionId, string $reason) use ($server): void {
            $this->logger->info('Client disconnected', [
                'session_id' => $sessionId,
                'reason' => $reason,
            ]);
            // Forward to server's event emitter if available
            if (\method_exists($server, 'emit')) {
                $server->emit('client_disconnected', [$sessionId, $reason]);
            }
        });

        $this->transport->on(
            'message',
            static function (mixed $message, string $sessionId, array $context) use ($server): void {
                // Forward message events to server handlers
                if (\method_exists($server, 'emit')) {
                    $server->emit('message', [$message, $sessionId, $context]);
                }
            },
        );

        // Set up SSE manager event forwarding
        $sseManager = $this->transport->getSseManager();
        $sseManager->on('client_disconnected', function (string $sessionId, string $reason): void {
            // Forward SSE manager events to transport
            $this->transport->emit('client_disconnected', [$sessionId, $reason]);
        });

        // Call parent constructor with empty httpOptions array since we handle everything
        parent::__construct($server, $initOptions, [], $logger);
    }

    /**
     * Handle a Swoole HTTP request
     */
    public function handleSwooleRequest(Request $request, Response $response): void
    {
        $this->stats['requests_total']++;

        try {
            // Delegate to our enhanced transport
            $this->transport->handleSwooleRequest($request, $response);
        } catch (\Throwable $e) {
            $this->logger->error('Error in Swoole request handler', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_uri' => $request->server['request_uri'] ?? '/',
                'method' => $request->server['request_method'] ?? 'GET',
            ]);

            // Send error response
            $response->setStatusCode(500);
            $response->setHeader('Content-Type', 'application/json');
            $response->end(\json_encode([
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Override parent handleRequest to use our enhanced transport
     */
    #[\Override]
    public function handleRequest(?HttpMessage $request = null): HttpMessage
    {
        // For traditional HTTP requests, create a simple response
        // The actual request handling is done via handleSwooleRequest
        if ($request === null) {
            $request = HttpMessage::fromGlobals();
        }

        return HttpMessage::createJsonResponse(['status' => 'processed']);
    }

    /**
     * Start the enhanced server runner
     */
    public function start(): void
    {
        if (!$this->transport->isStarted()) {
            $this->transport->start();
        }

        // Start heartbeat timer for SSE connections
        if ($this->config->sseEnabled) {
            $this->startHeartbeatTimer();
        }

        // Start stats timer if enabled
        if ($this->config->enableStats) {
            $this->startStatsTimer();
        }

        $this->logger->info('Swoole server runner started', [
            'sse_enabled' => $this->config->sseEnabled,
            'worker_num' => $this->config->workerNum,
            'max_connections' => $this->config->maxConnections,
        ]);
    }

    /**
     * Stop the server runner
     */
    #[\Override]
    public function stop(): void
    {
        // Clear timers
        if ($this->heartbeatTimer !== null) {
            Timer::clear($this->heartbeatTimer);
            $this->heartbeatTimer = null;
        }

        if ($this->statsTimer !== null) {
            Timer::clear($this->statsTimer);
            $this->statsTimer = null;
        }

        // Stop transport
        $this->transport->stop();

        $this->logger->info('Swoole server runner stopped', $this->getStats());
    }

    /**
     * Get server statistics
     */
    public function getStats(): array
    {
        $uptime = \time() - $this->stats['start_time'];

        $stats = [
            'uptime_seconds' => $uptime,
            'requests_total' => $this->stats['requests_total'],
            'requests_per_second' => $uptime > 0 ? \round($this->stats['requests_total'] / $uptime, 2) : 0,
        ];

        if ($this->config->sseEnabled) {
            $sseStats = $this->transport->getSseManager()->getStats();
            $stats = \array_merge($stats, [
                'sse_connections' => $sseStats['total_connections'],
                'sse_sessions' => $sseStats['active_sessions'],
                'sse_max_connections' => $sseStats['max_connections'],
            ]);
        }

        // Add Swoole-specific stats if available
        if (\function_exists('swoole_get_local_ip')) {
            $stats['swoole_version'] = SWOOLE_VERSION;
        }

        return $stats;
    }

    /**
     * Handle worker start event
     */
    public function onWorkerStart(int $workerId): void
    {
        $this->logger->info('Swoole worker started', [
            'worker_id' => $workerId,
            'process_id' => \getmypid(),
        ]);

        // Initialize worker-specific resources if needed
        if ($workerId === 0) {
            // Master worker can handle additional responsibilities
            $this->start();
        }
    }

    /**
     * Handle worker stop event
     */
    public function onWorkerStop(int $workerId): void
    {
        $this->logger->info('Swoole worker stopping', [
            'worker_id' => $workerId,
            'process_id' => \getmypid(),
        ]);

        $this->stop();
    }

    /**
     * Handle connection close event
     */
    public function onClose(int $fd): void
    {
        // This would be called when a connection is closed
        // We can use this to clean up any associated SSE connections
        $this->logger->debug('Connection closed', ['fd' => $fd]);
    }

    /**
     * Start heartbeat timer for SSE connections
     */
    private function startHeartbeatTimer(): void
    {
        $interval = $this->config->sseHeartbeatInterval * 1000; // Convert to milliseconds

        $this->heartbeatTimer = Timer::tick($interval, function (): void {
            try {
                $this->transport->flush();
                $this->updateSseStats();
            } catch (\Throwable $e) {
                $this->logger->error('Error in heartbeat timer', [
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->logger->debug('SSE heartbeat timer started', [
            'interval_seconds' => $this->config->sseHeartbeatInterval,
        ]);
    }

    /**
     * Start statistics timer
     */
    private function startStatsTimer(): void
    {
        $interval = $this->config->statsInterval * 1000; // Convert to milliseconds

        $this->statsTimer = Timer::tick($interval, function (): void {
            try {
                $stats = $this->getStats();
                $this->logger->info('Server statistics', $stats);
            } catch (\Throwable $e) {
                $this->logger->error('Error in stats timer', [
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->logger->debug('Statistics timer started', [
            'interval_seconds' => $this->config->statsInterval,
        ]);
    }

    /**
     * Update SSE connection statistics
     */
    private function updateSseStats(): void
    {
        if ($this->config->sseEnabled) {
            $sseStats = $this->transport->getSseManager()->getStats();
            $this->stats['sse_connections'] = $sseStats['total_connections'];
            $this->stats['sessions_active'] = $sseStats['active_sessions'];
        }
    }
}
