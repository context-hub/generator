<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server\Driver;

use Butschster\ContextGenerator\McpServer\Config\SwooleTransportConfig;
use Butschster\ContextGenerator\McpServer\Server\MessageParser;
use Butschster\ContextGenerator\McpServer\Server\Swoole\SwooleServerRunner;
use Mcp\Server\InitializationOptions;
use Mcp\Server\Server as McpServer;
use Psr\Log\LoggerInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

/**
 * Advanced Swoole HTTP server driver with SSE support and connection management
 */
final class SwooleServerDriver implements ServerDriverInterface
{
    private ?Server $server = null;
    private ?SwooleServerRunner $runner = null;
    private bool $isRunning = false;

    public function __construct(
        private readonly SwooleTransportConfig $config,
        private readonly LoggerInterface $logger,
        private readonly ExceptionReporterInterface $reporter,
        private readonly MessageParser $messageParser,
    ) {}

    public function run(McpServer $server, InitializationOptions $initOptions): void
    {
        if ($this->isRunning) {
            throw new \RuntimeException('Server is already running');
        }

        try {
            // Create enhanced server runner
            $this->runner = new SwooleServerRunner(
                $server,
                $initOptions,
                $this->config,
                $this->messageParser,
                $this->logger,
            );

            // Create Swoole HTTP server
            $this->server = new Server($this->config->host, $this->config->port);

            // Configure server settings
            $this->server->set($this->config->getSwooleSettings());

            // Register event handlers
            $this->registerEventHandlers();

            $this->logger->info('Starting Swoole HTTP server', [
                'host' => $this->config->host,
                'port' => $this->config->port,
                'worker_num' => $this->config->workerNum,
                'sse_enabled' => $this->config->sseEnabled,
            ]);

            // Start the server (this blocks)
            $this->isRunning = true;
            $this->server->start();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to start Swoole server', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->cleanup();
            throw $e;
        }
    }

    /**
     * Get server statistics
     */
    public function getStats(): array
    {
        if ($this->server === null) {
            return ['status' => 'not_started'];
        }

        $stats = [
            'server_status' => $this->isRunning ? 'running' : 'stopped',
            'swoole_version' => SWOOLE_VERSION,
            'host' => $this->config->host,
            'port' => $this->config->port,
        ];

        // Add Swoole server stats if available
        try {
            $swooleStats = $this->server->stats();
            $stats = \array_merge($stats, $swooleStats);
        } catch (\Throwable $e) {
            $this->logger->debug('Could not get Swoole stats', [
                'error' => $e->getMessage(),
            ]);
        }

        // Add runner stats if available
        if ($this->runner !== null) {
            try {
                $runnerStats = $this->runner->getStats();
                $stats = \array_merge($stats, $runnerStats);
            } catch (\Throwable $e) {
                $this->logger->debug('Could not get runner stats', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Gracefully shutdown the server
     */
    public function shutdown(): void
    {
        if ($this->server !== null && $this->isRunning) {
            $this->logger->info('Initiating graceful shutdown');
            $this->server->shutdown();
        }
    }

    /**
     * Reload server workers
     */
    public function reload(): void
    {
        if ($this->server !== null && $this->isRunning) {
            $this->logger->info('Reloading server workers');
            $this->server->reload();
        }
    }

    /**
     * Get the underlying Swoole server instance
     */
    public function getServer(): ?Server
    {
        return $this->server;
    }

    /**
     * Check if server is running
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * Get server configuration
     */
    public function getConfig(): SwooleTransportConfig
    {
        return $this->config;
    }

    /**
     * Send task to background worker
     */
    public function sendTask(mixed $data): bool
    {
        if ($this->server === null || !$this->isRunning) {
            return false;
        }

        try {
            $taskId = $this->server->task($data);
            return $taskId !== false;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send task', [
                'error' => $e->getMessage(),
                'data_type' => \gettype($data),
            ]);
            return false;
        }
    }

    /**
     * Handle system signals for graceful shutdown
     */
    public function handleSignal(int $signal): void
    {
        $signalName = match ($signal) {
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT',
            SIGUSR1 => 'SIGUSR1',
            SIGUSR2 => 'SIGUSR2',
            default => "Signal $signal",
        };

        $this->logger->info("Received signal: $signalName");

        match ($signal) {
            SIGTERM, SIGINT => $this->shutdown(),
            SIGUSR1 => $this->reload(),
            SIGUSR2 => $this->logger->info('Server stats', $this->getStats()),
            default => $this->logger->warning("Unhandled signal: $signalName"),
        };
    }

    /**
     * Register all event handlers for the Swoole server
     */
    private function registerEventHandlers(): void
    {
        // Main request handler
        $this->server->on('request', $this->handleRequest(...));

        // Worker lifecycle events
        $this->server->on('workerStart', $this->onWorkerStart(...));
        $this->server->on('workerStop', $this->onWorkerStop(...));
        $this->server->on('workerError', $this->onWorkerError(...));

        // Server lifecycle events
        $this->server->on('start', $this->onServerStart(...));
        $this->server->on('shutdown', $this->onServerShutdown(...));
        $this->server->on('managerStart', $this->onManagerStart(...));
        $this->server->on('managerStop', $this->onManagerStop(...));

        // Connection events
        $this->server->on('connect', $this->onConnect(...));
        $this->server->on('close', $this->onClose(...));

        // Task events (if task workers are enabled)
        if ($this->config->taskWorkerNum > 0) {
            $this->server->on('task', $this->onTask(...));
            $this->server->on('finish', $this->onTaskFinish(...));
        }
    }

    /**
     * Handle HTTP requests
     */
    private function handleRequest(Request $request, Response $response): void
    {
        try {
            // Add CORS headers if enabled
            if ($this->config->corsEnabled) {
                $this->addCorsHeaders($response);

                // Handle preflight OPTIONS requests
                if (\strtoupper($request->server['request_method'] ?? '') === 'OPTIONS') {
                    $response->setStatusCode(204);
                    $response->end();
                    return;
                }
            }

            // Log request if enabled
            if ($this->config->logRequests) {
                $this->logger->debug('Incoming request', [
                    'method' => $request->server['request_method'] ?? 'UNKNOWN',
                    'uri' => $request->server['request_uri'] ?? '/',
                    'remote_addr' => $request->server['remote_addr'] ?? 'unknown',
                    'user_agent' => $request->header['user-agent'] ?? 'unknown',
                ]);
            }

            // Delegate to the runner
            if ($this->runner !== null) {
                $this->runner->handleSwooleRequest($request, $response);
            } else {
                $this->sendErrorResponse($response, 'Server not properly initialized', 500);
            }
        } catch (\Throwable $e) {
            $this->reporter->report($e);
            $this->logger->error('Error handling request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'uri' => $request->server['request_uri'] ?? '/',
            ]);

            $this->sendErrorResponse($response, 'Internal server error', 500);
        }
    }

    /**
     * Add CORS headers to response
     */
    private function addCorsHeaders(Response $response): void
    {
        $origins = \implode(', ', $this->config->corsOrigins);

        $response->setHeader('Access-Control-Allow-Origin', $origins);
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Mcp-Session-Id, Accept');
        $response->setHeader('Access-Control-Max-Age', '86400');

        if ($this->config->sseEnabled) {
            $response->setHeader(
                'Access-Control-Allow-Headers',
                'Content-Type, Authorization, Mcp-Session-Id, Accept, Cache-Control',
            );
        }
    }

    /**
     * Send error response
     */
    private function sendErrorResponse(Response $response, string $message, int $code): void
    {
        $response->setStatusCode($code);
        $response->setHeader('Content-Type', 'application/json');
        $response->end(\json_encode([
            'error' => $message,
            'code' => $code,
            'timestamp' => \time(),
        ]));
    }

    /**
     * Server start event handler
     */
    private function onServerStart(Server $server): void
    {
        $this->logger->info('Swoole server started', [
            'master_pid' => $server->master_pid,
            'manager_pid' => $server->manager_pid,
            'host' => $this->config->host,
            'port' => $this->config->port,
        ]);

        // Set process title
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title('mcp-server: master');
        }
    }

    /**
     * Server shutdown event handler
     */
    private function onServerShutdown(Server $server): void
    {
        $this->logger->info('Swoole server shutting down', [
            'master_pid' => $server->master_pid,
        ]);

        $this->isRunning = false;
        $this->cleanup();
    }

    /**
     * Manager start event handler
     */
    private function onManagerStart(Server $server): void
    {
        $this->logger->info('Swoole manager started', [
            'manager_pid' => $server->manager_pid,
        ]);

        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title('mcp-server: manager');
        }
    }

    /**
     * Manager stop event handler
     */
    private function onManagerStop(Server $server): void
    {
        $this->logger->info('Swoole manager stopped', [
            'manager_pid' => $server->manager_pid,
        ]);
    }

    /**
     * Worker start event handler
     */
    private function onWorkerStart(Server $server, int $workerId): void
    {
        $this->logger->info('Swoole worker started', [
            'worker_id' => $workerId,
            'process_id' => \getmypid(),
        ]);

        // Set process title
        if (\function_exists('cli_set_process_title')) {
            $title = $workerId >= $server->setting['worker_num']
                ? 'mcp-server: task worker'
                : 'mcp-server: worker';
            \cli_set_process_title($title);
        }

        // Initialize the runner for this worker
        if ($this->runner !== null && $workerId < $server->setting['worker_num']) {
            $this->runner->onWorkerStart($workerId);
        }
    }

    /**
     * Worker stop event handler
     */
    private function onWorkerStop(Server $server, int $workerId): void
    {
        $this->logger->info('Swoole worker stopped', [
            'worker_id' => $workerId,
            'process_id' => \getmypid(),
        ]);

        // Cleanup the runner for this worker
        if ($this->runner !== null && $workerId < $server->setting['worker_num']) {
            $this->runner->onWorkerStop($workerId);
        }
    }

    /**
     * Worker error event handler
     */
    private function onWorkerError(Server $server, int $workerId, int $workerPid, int $exitCode, int $signal): void
    {
        $this->logger->error('Swoole worker error', [
            'worker_id' => $workerId,
            'worker_pid' => $workerPid,
            'exit_code' => $exitCode,
            'signal' => $signal,
        ]);
    }

    /**
     * Connection open event handler
     */
    private function onConnect(Server $server, int $fd): void
    {
        $this->logger->debug('Connection opened', [
            'fd' => $fd,
            'reactor_id' => $server->getClientInfo($fd)['reactor_id'] ?? null,
        ]);
    }

    /**
     * Connection close event handler
     */
    private function onClose(Server $server, int $fd): void
    {
        $this->logger->debug('Connection closed', [
            'fd' => $fd,
        ]);

        // Notify runner about connection close
        if ($this->runner !== null) {
            $this->runner->onClose($fd);
        }
    }

    /**
     * Task event handler
     */
    private function onTask(Server $server, int $taskId, int $srcWorkerId, mixed $data): mixed
    {
        $this->logger->debug('Task received', [
            'task_id' => $taskId,
            'src_worker_id' => $srcWorkerId,
            'data_type' => \gettype($data),
        ]);

        try {
            // Process the task (this would be customizable)
            $result = $this->processTask($data);

            $this->logger->debug('Task completed', [
                'task_id' => $taskId,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Task error', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Task finish event handler
     */
    private function onTaskFinish(Server $server, int $taskId, mixed $data): void
    {
        $this->logger->debug('Task finished', [
            'task_id' => $taskId,
            'result_type' => \gettype($data),
        ]);
    }

    /**
     * Process a background task
     */
    private function processTask(mixed $data): mixed
    {
        // This could be extended to handle various background tasks
        // such as sending notifications, cleanup operations, etc.
        return ['status' => 'processed', 'timestamp' => \time()];
    }

    /**
     * Cleanup server resources
     */
    private function cleanup(): void
    {
        if ($this->runner !== null) {
            try {
                $this->runner->stop();
            } catch (\Throwable $e) {
                $this->reporter->report($e);
                $this->logger->error('Error stopping runner', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->isRunning = false;
        $this->server = null;
        $this->runner = null;
    }
}
