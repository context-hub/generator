<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server\Transport\Swoole;

use Psr\Log\LoggerInterface;

/**
 * Manages SSE connections with session awareness and lifecycle management
 */
final class SseConnectionManager
{
    /**
     * @var array<string, SseConnection>
     */
    private array $connections = [];

    /**
     * @var array<string, array<string>>
     */
    private array $sessionConnections = [];

    private readonly int $maxConnections;
    private readonly int $heartbeatInterval;
    private readonly int $connectionTimeout;
    private int $lastCleanup = 0;

    public function __construct(
        private readonly LoggerInterface $logger,
        array $config = [],
    ) {
        $this->maxConnections = $config['max_connections'] ?? 1000;
        $this->heartbeatInterval = $config['heartbeat_interval'] ?? 30;
        $this->connectionTimeout = $config['connection_timeout'] ?? 300;
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

        $connectionId = $connection->getId();
        $sessionId = $connection->getSessionId();

        // Store connection
        $this->connections[$connectionId] = $connection;

        // Map session to connection
        if (!isset($this->sessionConnections[$sessionId])) {
            $this->sessionConnections[$sessionId] = [];
        }
        $this->sessionConnections[$sessionId][] = $connectionId;

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
    public function removeConnection(string $connectionId): void
    {
        if (!isset($this->connections[$connectionId])) {
            return;
        }

        $connection = $this->connections[$connectionId];
        $sessionId = $connection->getSessionId();

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

            // Clean up empty session arrays
            if (empty($this->sessionConnections[$sessionId])) {
                unset($this->sessionConnections[$sessionId]);
            }
        }

        $this->logger->debug('SSE connection removed', [
            'connection_id' => $connectionId,
            'session_id' => $sessionId,
            'total_connections' => \count($this->connections),
        ]);
    }

    /**
     * Get connection by ID
     */
    public function getConnection(string $connectionId): ?SseConnection
    {
        return $this->connections[$connectionId] ?? null;
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

        foreach ($connections as $connection) {
            if ($connection->sendMessage($message, $messageId)) {
                $sent++;
            } else {
                // Connection failed, remove it
                $this->removeConnection($connection->getId());
            }
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

        foreach ($connections as $connection) {
            if ($connection->sendEvent($eventType, $data, $eventId)) {
                $sent++;
            } else {
                // Connection failed, remove it
                $this->removeConnection($connection->getId());
            }
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
            $this->removeConnection($connectionId);
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
            $this->removeConnection($connectionId);
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
            $this->removeConnection($connection->getId());
        }

        $this->logger->debug('Closed all SSE connections for session', [
            'session_id' => $sessionId,
            'closed_count' => \count($connections),
        ]);
    }

    /**
     * Get connection statistics
     */
    public function getStats(): array
    {
        $sessionCounts = [];
        foreach ($this->sessionConnections as $sessionId => $connections) {
            $sessionCounts[$sessionId] = \count($connections);
        }

        return [
            'total_connections' => \count($this->connections),
            'active_sessions' => \count($this->sessionConnections),
            'max_connections' => $this->maxConnections,
            'session_connections' => $sessionCounts,
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
     * Get total connection count
     */
    public function getConnectionCount(): int
    {
        return \count($this->connections);
    }

    /**
     * Cleanup all connections (for shutdown)
     */
    public function cleanup(): void
    {
        $connectionIds = \array_keys($this->connections);

        foreach ($connectionIds as $connectionId) {
            $this->removeConnection($connectionId);
        }

        $this->logger->info('SSE connection manager cleaned up', [
            'closed_connections' => \count($connectionIds),
        ]);
    }
}
