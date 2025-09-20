<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Config;

/**
 * Enhanced configuration for Swoole-based HTTP transport with SSE support
 */
final readonly class SwooleTransportConfig extends HttpTransportConfig
{
    public function __construct(
        public string $host = 'localhost',
        public int $port = 8080,
        public string $sessionStore = 'file',
        public ?string $sessionStorePath = null,
        public bool $corsEnabled = true,
        public array $corsOrigins = ['*'],
        public int $maxRequestSize = 10485760, // 10MB

        // SSE-specific configuration
        public bool $sseEnabled = true,
        public int $maxSseConnections = 1000,
        public int $sseHeartbeatInterval = 30,
        public int $sseReconnectTimeout = 5,
        public int $sseBufferSize = 8192,

        // Swoole server configuration
        public int $workerNum = 4,
        public int $taskWorkerNum = 2,
        public int $maxConnections = 10000,
        public int $connectionTimeout = 300,
        public int $bufferSize = 32768,
        public int $packageMaxLength = 2097152,

        // Performance settings
        public bool $enableCoroutine = true,
        public bool $enableStaticHandler = false,
        public int $maxCoroutines = 100000,
        public int $socketBufferSize = 2097152,

        // Logging and monitoring
        public bool $enableStats = true,
        public int $statsInterval = 60,
        public bool $logRequests = false,
    ) {}

    /**
     * Get Swoole server configuration array
     */
    public function getSwooleSettings(): array
    {
        return [
            'worker_num' => $this->workerNum,
            'task_worker_num' => $this->taskWorkerNum,
            'max_connection' => $this->maxConnections,
            'socket_buffer_size' => $this->socketBufferSize,
            'package_max_length' => $this->packageMaxLength,
            'buffer_output_size' => $this->bufferSize,
            'enable_coroutine' => $this->enableCoroutine,
            'max_coroutine' => $this->maxCoroutines,
            'enable_static_handler' => $this->enableStaticHandler,
            'heartbeat_check_interval' => 60,
            'heartbeat_idle_time' => $this->connectionTimeout,
            'log_level' => SWOOLE_LOG_WARNING,
            'log_rotation' => SWOOLE_LOG_ROTATION_DAILY,
        ];
    }

    /**
     * Get SSE configuration array
     */
    public function getSseConfig(): array
    {
        return [
            'enabled' => $this->sseEnabled,
            'max_connections' => $this->maxSseConnections,
            'heartbeat_interval' => $this->sseHeartbeatInterval,
            'reconnect_timeout' => $this->sseReconnectTimeout,
            'buffer_size' => $this->sseBufferSize,
        ];
    }
}
