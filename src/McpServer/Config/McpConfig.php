<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Config;

use Spiral\Core\InjectableConfig;

final class McpConfig extends InjectableConfig
{
    public const string CONFIG = 'mcp';

    protected array $config = [
        'document_name_format' => '[{path}] {description}',
        'transport' => [
            'type' => 'stdio',
            'http' => [
                'host' => 'localhost',
                'port' => 8080,
                'session_store' => 'file',
                'session_store_path' => null,
                'cors_enabled' => true,
                'cors_origins' => ['*'],
                'max_request_size' => 10485760,
            ],
            'swoole' => [
                'host' => 'localhost',
                'port' => 8080,
                'session_store' => 'file',
                'session_store_path' => null,
                'cors_enabled' => true,
                'cors_origins' => ['*'],
                'max_request_size' => 10485760,

                // SSE-specific configuration
                'sse_enabled' => true,
                'max_sse_connections' => 1000,
                'sse_heartbeat_interval' => 30,
                'sse_reconnect_timeout' => 5,
                'sse_buffer_size' => 8192,

                // Swoole server configuration
                'worker_num' => 4,
                'task_worker_num' => 2,
                'max_connections' => 10000,
                'connection_timeout' => 300,
                'buffer_size' => 32768,
                'package_max_length' => 2097152,

                // Performance settings
                'enable_coroutine' => true,
                'enable_static_handler' => false,
                'max_coroutines' => 100000,
                'socket_buffer_size' => 2097152,

                // Logging and monitoring
                'enable_stats' => true,
                'stats_interval' => 60,
                'log_requests' => false,
            ],
        ],
        'common_prompts' => [
            'enable' => true,
        ],
        'file_operations' => [
            'enable' => true,
            'write' => true,
            'apply-patch' => false,
            'directories-list' => true,
        ],
        'context_operations' => [
            'enable' => true,
        ],
        'prompt_operations' => [
            'enable' => true,
        ],
        'docs_tools' => [
            'enable' => false,
        ],
        'custom_tools' => [
            'enable' => true,
            'max_runtime' => 30,
        ],
        'git_operations' => [
            'enable' => true,
        ],
        'project_operations' => [
            'enable' => true,
        ],
    ];

    public function getDocumentNameFormat(string $path, string $description, string $tags): string
    {
        return \str_replace(
            ['{path}', '{description}', '{tags}'],
            [$path, $description, $tags],
            (string) $this->config['document_name_format'] ?: '',
        );
    }

    public function getTransportType(): string
    {
        return $this->config['transport']['type'] ?? 'stdio';
    }

    public function isHttpTransport(): bool
    {
        return $this->getTransportType() === 'http';
    }

    public function isSwooleTransport(): bool
    {
        return $this->getTransportType() === 'swoole';
    }

    public function getHttpTransportConfig(): HttpTransportConfig
    {
        $http = $this->config['transport']['http'] ?? [];

        return new HttpTransportConfig(
            host: $http['host'] ?? 'localhost',
            port: (int) ($http['port'] ?? 8080),
            sessionStore: $http['session_store'] ?? 'file',
            sessionStorePath: $http['session_store_path'] ?? null,
            corsEnabled: (bool) ($http['cors_enabled'] ?? true),
            corsOrigins: $http['cors_origins'] ?? ['*'],
            maxRequestSize: (int) ($http['max_request_size'] ?? 10485760),
        );
    }

    public function getSwooleTransportConfig(): SwooleTransportConfig
    {
        $swoole = $this->config['transport']['swoole'] ?? [];

        return new SwooleTransportConfig(
            // Base HTTP configuration
            host: $swoole['host'] ?? 'localhost',
            port: (int) ($swoole['port'] ?? 8080),
            sessionStore: $swoole['session_store'] ?? 'file',
            sessionStorePath: $swoole['session_store_path'] ?? null,
            corsEnabled: (bool) ($swoole['cors_enabled'] ?? true),
            corsOrigins: $swoole['cors_origins'] ?? ['*'],
            maxRequestSize: (int) ($swoole['max_request_size'] ?? 10485760),

            // SSE-specific configuration
            sseEnabled: (bool) ($swoole['sse_enabled'] ?? true),
            maxSseConnections: (int) ($swoole['max_sse_connections'] ?? 1000),
            sseHeartbeatInterval: (int) ($swoole['sse_heartbeat_interval'] ?? 30),
            sseReconnectTimeout: (int) ($swoole['sse_reconnect_timeout'] ?? 5),
            sseBufferSize: (int) ($swoole['sse_buffer_size'] ?? 8192),

            // Swoole server configuration
            workerNum: (int) ($swoole['worker_num'] ?? 4),
            taskWorkerNum: (int) ($swoole['task_worker_num'] ?? 2),
            maxConnections: (int) ($swoole['max_connections'] ?? 10000),
            connectionTimeout: (int) ($swoole['connection_timeout'] ?? 300),
            bufferSize: (int) ($swoole['buffer_size'] ?? 32768),
            packageMaxLength: (int) ($swoole['package_max_length'] ?? 2097152),

            // Performance settings
            enableCoroutine: (bool) ($swoole['enable_coroutine'] ?? true),
            enableStaticHandler: (bool) ($swoole['enable_static_handler'] ?? false),
            maxCoroutines: (int) ($swoole['max_coroutines'] ?? 100000),
            socketBufferSize: (int) ($swoole['socket_buffer_size'] ?? 2097152),

            // Logging and monitoring
            enableStats: (bool) ($swoole['enable_stats'] ?? true),
            statsInterval: (int) ($swoole['stats_interval'] ?? 60),
            logRequests: (bool) ($swoole['log_requests'] ?? false),
        );
    }

    public function isFileOperationsEnabled(): bool
    {
        return $this->config['file_operations']['enable'] ?? false;
    }

    public function isFileWriteEnabled(): bool
    {
        return $this->config['file_operations']['write'] ?? false;
    }

    public function isFileApplyPatchEnabled(): bool
    {
        return $this->config['file_operations']['apply-patch'] ?? false;
    }

    public function isFileDirectoriesListEnabled(): bool
    {
        return $this->config['file_operations']['directories-list'] ?? false;
    }

    public function isContextOperationsEnabled(): bool
    {
        return $this->config['context_operations']['enable'] ?? false;
    }

    public function isPromptOperationsEnabled(): bool
    {
        return $this->config['prompt_operations']['enable'] ?? false;
    }

    public function isDocsToolsEnabled(): bool
    {
        return $this->config['docs_tools']['enable'] ?? true;
    }

    public function commonPromptsEnabled(): bool
    {
        return $this->config['common_prompts']['enable'] ?? true;
    }

    public function isCustomToolsEnabled(): bool
    {
        return $this->config['custom_tools']['enable'] ?? true;
    }

    public function isGitOperationsEnabled(): bool
    {
        return $this->config['git_operations']['enable'] ?? true;
    }

    public function isProjectOperationsEnabled(): bool
    {
        return $this->config['project_operations']['enable'] ?? true;
    }
}
