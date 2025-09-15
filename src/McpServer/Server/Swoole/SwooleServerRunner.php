<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server\Swoole;

use Butschster\ContextGenerator\McpServer\Config\SwooleTransportConfig;
use Butschster\ContextGenerator\McpServer\Server\Transport\Swoole\SwooleTransport;
use Mcp\Server\HttpServerRunner;
use Mcp\Server\HttpServerSession;
use Mcp\Server\InitializationOptions;
use Mcp\Server\Server as McpServer;
use Mcp\Server\Transport\Http\HttpMessage;
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
        LoggerInterface $logger,
    ) {
        // Create our enhanced transport
        $this->transport = new SwooleTransport($logger, $this->config);

        // Initialize stats
        $this->stats['start_time'] = \time();

        // Call parent constructor with our transport
        parent::__construct($server, $initOptions, [], $logger);
    }

    /**
     * Handle a Swoole HTTP request
     */
    public function handleSwooleRequest(Request $request, Response $response): void
    {
        $this->stats['requests_total']++;

        try {
            // Let the transport handle the Swoole-specific logic
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
        // For traditional HTTP requests, delegate to parent
        // but use our transport's session management
        if ($request === null) {
            $request = HttpMessage::fromGlobals();
        }

        // Create a temporary response to capture transport output
        $sessionId = $request->getHeader('Mcp-Session-Id');

        if ($sessionId !== null) {
            $session = $this->getOrCreateSession($sessionId);
            if ($session !== null) {
                $this->transport->attachSession($session);
            }
        }

        // Process through our enhanced transport logic
        // This is a simplified version - in practice, we'd need more integration
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
     * Broadcast event to all SSE connections
     */
    public function broadcastEvent(string $eventType, mixed $data, ?string $eventId = null): int
    {
        if (!$this->config->sseEnabled) {
            return 0;
        }

        return $this->transport->getSseManager()->broadcast($data, $eventId);
    }

    /**
     * Send event to specific session
     */
    public function sendEventToSession(string $sessionId, string $eventType, mixed $data, ?string $eventId = null): int
    {
        if (!$this->config->sseEnabled) {
            return 0;
        }

        return $this->transport->getSseManager()->sendEventToSession($sessionId, $eventType, $data, $eventId);
    }

    /**
     * Get active SSE connection count
     */
    public function getSseConnectionCount(): int
    {
        if (!$this->config->sseEnabled) {
            return 0;
        }

        return $this->transport->getSseManager()->getConnectionCount();
    }

    /**
     * Check if session has active SSE connections
     */
    public function hasSessionSseConnections(string $sessionId): bool
    {
        if (!$this->config->sseEnabled) {
            return false;
        }

        return $this->transport->getSseManager()->hasSessionConnections($sessionId);
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

    /**
     * Get or create session for the given session ID
     */
    private function getOrCreateSession(string $sessionId): ?HttpServerSession
    {
        // This would integrate with the existing session management
        // For now, return null to indicate we need to implement this
        return null;
    }
}
