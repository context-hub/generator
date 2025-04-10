<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP\Connection;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Source\MCP\Config\ServerConfig;
use Mcp\Client\Client;
use Mcp\Client\ClientSession;
use Psr\Log\LoggerInterface;

/**
 * Manager for MCP client connections
 */
final class ConnectionManager
{
    /** @var array<string, ClientSession> */
    private array $sessions = [];

    private ?Client $client = null;

    public function __construct(
        private readonly VariableResolver $variableResolver = new VariableResolver(),
        #[LoggerPrefix(prefix: 'mcp-connection')]
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Create or get an MCP client session for the given server configuration
     */
    public function getSession(ServerConfig $config): ClientSession
    {
        // Create a unique key for this server configuration
        $key = $this->createConfigKey($config);

        // If we already have a session for this configuration, return it
        if (isset($this->sessions[$key])) {
            $this->logger?->debug('Using existing MCP client session', [
                'key' => $key,
            ]);
            return $this->sessions[$key];
        }

        // Otherwise, create a new session
        $this->logger?->info('Creating new MCP client session', [
            'key' => $key,
            'config' => $config,
        ]);

        // Apply variable resolution
        $resolvedConfig = $config->withResolvedVariables($this->variableResolver);

        // Create the client if it doesn't exist
        if ($this->client === null) {
            $this->client = new Client($this->logger);
        }

        // Connect based on configuration type
        if ($resolvedConfig->isCommandBased()) {
            $this->logger?->debug('Connecting to command-based MCP server', [
                'command' => $resolvedConfig->command,
                'args' => $resolvedConfig->args,
                'envCount' => \count($resolvedConfig->env),
            ]);

            $session = $this->client->connect(
                commandOrUrl: $resolvedConfig->command,
                args: $resolvedConfig->args,
                env: $resolvedConfig->env,
            );
        } elseif ($resolvedConfig->isUrlBased()) {
            $this->logger?->debug('Connecting to URL-based MCP server', [
                'url' => $resolvedConfig->url,
                'headersCount' => \count($resolvedConfig->headers),
            ]);

            // TODO: Implement URL-based connection with headers
            $session = $this->client->connect($resolvedConfig->url);
        } else {
            throw new \InvalidArgumentException('Invalid server configuration');
        }

        // Store the session for reuse
        $this->sessions[$key] = $session;

        return $session;
    }

    /**
     * Close all sessions and the client
     */
    public function close(): void
    {
        $this->logger?->info('Closing all MCP client sessions');

        // Close each session (though the client close should handle this)
        $this->sessions = [];

        // Close the client
        if ($this->client !== null) {
            $this->client->close();
            $this->client = null;
        }
    }

    /**
     * Create a unique key for a server configuration
     *
     * @param ServerConfig $config Server configuration
     * @return string Unique key
     */
    private function createConfigKey(ServerConfig $config): string
    {
        if ($config->isCommandBased()) {
            return 'cmd:' . \md5($config->command . \json_encode($config->args) . \json_encode($config->env));
        }

        if ($config->isUrlBased()) {
            return 'url:' . \md5($config->url . \json_encode($config->headers));
        }

        return 'invalid:' . \spl_object_hash($config);
    }
}
