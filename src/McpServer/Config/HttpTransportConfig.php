<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Config;

/**
 * HTTP Transport configuration DTO
 */
final readonly class HttpTransportConfig
{
    public function __construct(
        public string $host = 'localhost',
        public int $port = 8080,
        public string $sessionStore = 'file',
        public ?string $sessionStorePath = null,
        public bool $corsEnabled = true,
        public array $corsOrigins = ['*'],
        public int $maxRequestSize = 10485760, // 10MB
    ) {}

    /**
     * Get the full server address
     */
    public function getAddress(): string
    {
        return "http://{$this->host}:{$this->port}";
    }

    /**
     * Get session store configuration array
     */
    public function getSessionStoreConfig(): array
    {
        return [
            'type' => $this->sessionStore,
            'path' => $this->sessionStorePath,
        ];
    }

    /**
     * Get HTTP options array for the runner
     */
    public function toHttpOptions(): array
    {
        return [
            'enable_sse' => true,
        ];
    }
}
