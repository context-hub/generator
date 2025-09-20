<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server\Transport\Swoole;

use Swoole\Http\Response;

/**
 * Manages SSE connections for real-time event streaming
 */
final class SseConnection
{
    private bool $active = true;
    private int $lastPing = 0;
    private array $metadata = [];
    private readonly int $createdAt;

    public function __construct(
        public readonly string $id,
        private readonly Response $response,
        public readonly string $sessionId,
        ?int $createdAt = null,
    ) {
        $this->createdAt = $createdAt ?: \time();
        $this->lastPing = $this->createdAt;

        $this->initializeConnection();
    }

    /**
     * Send an SSE event
     */
    public function sendEvent(string $type, mixed $data, ?string $id = null): bool
    {
        if (!$this->active) {
            return false;
        }

        try {
            $event = '';

            if ($id !== null) {
                $event .= "id: {$id}\n";
            }

            $event .= "event: {$type}\n";

            if ($data !== null) {
                $jsonData = \json_encode($data);
                $event .= "data: {$jsonData}\n";
            }

            $event .= "\n";

            $result = $this->response->write($event);

            if ($result === false) {
                $this->close();
                return false;
            }

            return true;
        } catch (\Throwable) {
            $this->close();
            return false;
        }
    }

    /**
     * Send a heartbeat ping
     */
    public function ping(): bool
    {
        if (!$this->active) {
            return false;
        }

        $this->lastPing = \time();
        return $this->sendEvent('ping', ['timestamp' => $this->lastPing]);
    }

    /**
     * Send JSON-RPC message as SSE event
     */
    public function sendMessage(mixed $message, ?string $id = null): bool
    {
        return $this->sendEvent('message', $message, $id);
    }

    /**
     * Send error event
     */
    public function sendError(string $message, ?array $details = null): bool
    {
        return $this->sendEvent('error', [
            'message' => $message,
            'details' => $details,
            'timestamp' => \time(),
        ]);
    }

    /**
     * Close the connection
     */
    public function close(): void
    {
        if (!$this->active) {
            return;
        }

        $this->active = false;

        try {
            $this->sendEvent('close', ['reason' => 'Connection closed']);
            $this->response->end();
        } catch (\Throwable) {
            // Ignore errors during close
        }
    }

    /**
     * Check if connection is still active
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Check if connection has timed out
     */
    public function hasTimedOut(int $timeout): bool
    {
        return (\time() - $this->lastPing) > $timeout;
    }

    /**
     * Set connection metadata
     */
    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    /**
     * Get connection metadata
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Initialize SSE connection with proper headers
     */
    private function initializeConnection(): void
    {
        $this->response->header('Content-Type', 'text/event-stream');
        $this->response->header('Cache-Control', 'no-cache');
        $this->response->header('Connection', 'keep-alive');
        $this->response->header('Access-Control-Allow-Origin', '*');
        $this->response->header('X-Accel-Buffering', 'no'); // Nginx fix

        // Send initial connection event
        $this->sendEvent('connection', [
            'id' => $this->id,
            'session_id' => $this->sessionId,
            'timestamp' => \time(),
        ]);
    }
}
