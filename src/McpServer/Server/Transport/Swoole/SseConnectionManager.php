<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server\Transport\Swoole;

use Psr\Log\LoggerInterface;

/**
 * Manages SSE connections with session awareness and lifecycle management
 */
final class SseConnectionManager
{
    /** @var array<string, SseConnection> */
    private array $connections = [];

    /** @var array<string, array<string>> */
    private array $sessionConnections = [];
    private int $lastCleanup = 0;

    /** @var array<string, callable[]> */
    private array $eventListeners = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $maxConnections,
        private readonly int $heartbeatInterval,
        private readonly int $connectionTimeout,
    ) {}

    /**
     * Add event listener
     */
    public function on(string $event, callable $listener): void
    {
        if (!isset($this->eventListeners[$event])) {
            $this->eventListeners[$event] = [];
        }
        $this->eventListeners[$event][] = $listener;
    }

    /**
     * Emit event to listeners
     */
    public function emit(string $event, array $arguments = []): void
    {
        if (!isset($this->eventListeners[$event])) {
            return;
        }

        foreach ($this->eventListeners[$event] as $listener) {
            try {
                \call_user_func_array($listener, $arguments);
            } catch (\Throwable $e) {
                $this->logger->error('Error in SSE manager event listener', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Add a new SSE connection
     */
    public function addConnection(SseConnection $connection): bool
    {
        // Check connection limits
        if (\count($this->connections) >= $this->maxConnections) {
            $this->logger->warning('SSE connection limit reached', [
                'current_connections' => \count($this->connections),
                'max_connections' => $this->maxConnections,
            ]);
            return false;
        }

        $connectionId = $connection->id;
        $sessionId = $connection->sessionId;

        // Store connection
        $this->connections[$connectionId] = $connection;

        // Map session to connection
        if (!isset($this->sessionConnections[$sessionId])) {
            $this->sessionConnections[$sessionId] = [];
        }
        $this->sessionConnections[$sessionId][] = $connectionId;

        // Set up connection close handler
        $this->setupConnectionCloseHandler($connection);

        $this->logger->debug('SSE connection added', [
            'connection_id' => $connectionId,
            'session_id' => $sessionId,
            'total_connections' => \count($this->connections),
        ]);

        return true;
    }

    /**
     * Remove a connection
     */
    public function removeConnection(string $connectionId, string $reason = 'Unknown'): void
    {
        if (!isset($this->connections[$connectionId])) {
            return;
        }

        $connection = $this->connections[$connectionId];
        $sessionId = $connection->sessionId;

        // Close connection if still active
        if ($connection->isActive()) {
            $connection->close();
        }

        // Remove from connections
        unset($this->connections[$connectionId]);

        // Remove from session mapping
        if (isset($this->sessionConnections[$sessionId])) {
            $this->sessionConnections[$sessionId] = \array_filter(
                $this->sessionConnections[$sessionId],
                static fn($id) => $id !== $connectionId,
            );

            // If no more connections for this session, emit client_disconnected
            if (empty($this->sessionConnections[$sessionId])) {
                unset($this->sessionConnections[$sessionId]);
                $this->emit('client_disconnected', [$sessionId, $reason]);
            }
        }

        $this->logger->debug('SSE connection removed', [
            'connection_id' => $connectionId,
            'session_id' => $sessionId,
            'reason' => $reason,
            'total_connections' => \count($this->connections),
        ]);
    }

    /**
     * Get connections for a session
     */
    public function getSessionConnections(string $sessionId): array
    {
        if (!isset($this->sessionConnections[$sessionId])) {
            return [];
        }

        $connections = [];
        foreach ($this->sessionConnections[$sessionId] as $connectionId) {
            if (isset($this->connections[$connectionId])) {
                $connections[] = $this->connections[$connectionId];
            }
        }

        return $connections;
    }

    /**
     * Send message to all connections of a session
     */
    public function sendToSession(string $sessionId, mixed $message, ?string $messageId = null): int
    {
        $connections = $this->getSessionConnections($sessionId);
        $sent = 0;
        $failed = [];

        foreach ($connections as $connection) {
            if ($connection->sendMessage($message, $messageId)) {
                $sent++;
            } else {
                // Connection failed, mark for removal
                $failed[] = $connection->getId();
            }
        }

        // Remove failed connections
        foreach ($failed as $connectionId) {
            $this->removeConnection($connectionId, 'Send message failed');
        }

        return $sent;
    }

    /**
     * Send event to all connections of a session
     */
    public function sendEventToSession(string $sessionId, string $eventType, mixed $data, ?string $eventId = null): int
    {
        $connections = $this->getSessionConnections($sessionId);
        $sent = 0;
        $failed = [];

        foreach ($connections as $connection) {
            if ($connection->sendEvent($eventType, $data, $eventId)) {
                $sent++;
            } else {
                // Connection failed, mark for removal
                $failed[] = $connection->getId();
            }
        }

        // Remove failed connections
        foreach ($failed as $connectionId) {
            $this->removeConnection($connectionId, 'Send event failed');
        }

        return $sent;
    }

    /**
     * Broadcast message to all connections
     */
    public function broadcast(mixed $message, ?string $messageId = null): int
    {
        $sent = 0;
        $failed = [];

        foreach ($this->connections as $connectionId => $connection) {
            if ($connection->sendMessage($message, $messageId)) {
                $sent++;
            } else {
                $failed[] = $connectionId;
            }
        }

        // Remove failed connections
        foreach ($failed as $connectionId) {
            $this->removeConnection($connectionId, 'Broadcast failed');
        }

        return $sent;
    }

    /**
     * Send heartbeat to all connections
     */
    public function sendHeartbeat(): void
    {
        $now = \time();
        if ($now - $this->lastCleanup < $this->heartbeatInterval) {
            return;
        }

        $this->lastCleanup = $now;
        $failed = [];

        foreach ($this->connections as $connectionId => $connection) {
            // Check for timeout
            if ($connection->hasTimedOut($this->connectionTimeout)) {
                $failed[] = $connectionId;
                continue;
            }

            // Send ping
            if (!$connection->ping()) {
                $failed[] = $connectionId;
            }
        }

        // Remove failed/timed out connections
        foreach ($failed as $connectionId) {
            $this->removeConnection($connectionId, 'Heartbeat timeout');
        }

        if (!empty($failed)) {
            $this->logger->info('Removed timed out SSE connections', [
                'removed_count' => \count($failed),
                'remaining_connections' => \count($this->connections),
            ]);
        }
    }

    /**
     * Close all connections for a session
     */
    public function closeSessionConnections(string $sessionId): void
    {
        $connections = $this->getSessionConnections($sessionId);

        foreach ($connections as $connection) {
            $this->removeConnection($connection->getId(), 'Session closed');
        }

        $this->logger->debug('Closed all SSE connections for session', [
            'session_id' => $sessionId,
            'closed_count' => \count($connections),
        ]);
    }

    /**
     * Get connection statistics
     *
     * @return array{
     *     total_connections: int,
     *     active_sessions: int,
     *     max_connections: int,
     *     session_connections: array<string, int>,
     *     last_cleanup: int,
     * }
     */
    public function getStats(): array
    {
        return [
            'total_connections' => \count($this->connections),
            'active_sessions' => \count($this->sessionConnections),
            'max_connections' => $this->maxConnections,
            'session_connections' => \array_map(\count(...), $this->sessionConnections),
            'last_cleanup' => $this->lastCleanup,
        ];
    }

    /**
     * Check if session has active connections
     */
    public function hasSessionConnections(string $sessionId): bool
    {
        return !empty($this->getSessionConnections($sessionId));
    }

    /**
     * Cleanup all connections (for shutdown)
     */
    public function cleanup(): void
    {
        $connectionIds = \array_keys($this->connections);

        foreach ($connectionIds as $connectionId) {
            $this->removeConnection($connectionId, 'Transport stopped');
        }

        $this->logger->info('SSE connection manager cleaned up', [
            'closed_connections' => \count($connectionIds),
        ]);
    }

    /**
     * Setup connection close handler to detect when Swoole closes the connection
     */
    private function setupConnectionCloseHandler(SseConnection $connection): void
    {
        // This is a bit of a workaround since Swoole doesn't have built-in
        // connection close detection for SSE. We can set up periodic checks
        // or use the heartbeat mechanism to detect closed connections.

        // For now, we rely on the heartbeat mechanism in sendHeartbeat()
        // to detect and clean up closed connections
    }
}
