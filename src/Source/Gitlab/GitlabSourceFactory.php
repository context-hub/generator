<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Gitlab;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Source\Gitlab\Config\ServerConfig;
use Butschster\ContextGenerator\Source\Registry\AbstractSourceFactory;
use Butschster\ContextGenerator\Source\SourceInterface;
use Butschster\ContextGenerator\Source\Gitlab\Config\ServerRegistry;
use Psr\Log\LoggerInterface;

#[LoggerPrefix(prefix: 'gitlab-source-factory')]
final readonly class GitlabSourceFactory extends AbstractSourceFactory
{
    public function __construct(
        private ServerRegistry $serverRegistry,
        DirectoriesInterface $dirs,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct(dirs: $dirs, logger: $logger);
    }

    #[\Override]
    public function getType(): string
    {
        return 'gitlab';
    }

    #[\Override]
    public function create(array $config): SourceInterface
    {
        $this->logger?->debug('Creating GitLab source', [
            'path' => (string) $this->dirs->getRootPath(),
            'config' => $config,
        ]);

        if (!isset($config['repository'])) {
            throw new \RuntimeException('GitLab source must have a "repository" property');
        }

        // Determine source paths (required)
        if (!isset($config['sourcePaths'])) {
            throw new \RuntimeException('GitLab source must have a "sourcePaths" property');
        }
        $sourcePaths = $config['sourcePaths'];
        if (!\is_string($sourcePaths) && !\is_array($sourcePaths)) {
            throw new \RuntimeException('"sourcePaths" must be a string or array in source');
        }

        // Validate filePattern if present
        if (isset($config['filePattern'])) {
            if (!\is_string($config['filePattern']) && !\is_array($config['filePattern'])) {
                throw new \RuntimeException('filePattern must be a string or an array of strings');
            }
            // If it's an array, make sure all elements are strings
            if (\is_array($config['filePattern'])) {
                foreach ($config['filePattern'] as $pattern) {
                    if (!\is_string($pattern)) {
                        throw new \RuntimeException('All elements in filePattern must be strings');
                    }
                }
            }
        }

        // Convert notPath to match Symfony Finder's naming convention
        $notPath = $config['excludePatterns'] ?? $config['notPath'] ?? [];

        $server = $config['server'] ?? null;

        // Validate server configuration
        if ($server !== null) {
            $server = match (true) {
                \is_string($server) => $this->serverRegistry->get($server),
                \is_array($server) => ServerConfig::fromArray($server),
                default => throw new \RuntimeException('GitLab server must be provided'),
            };
        }

        return new GitlabSource(
            repository: $config['repository'],
            sourcePaths: $sourcePaths,
            branch: $config['branch'] ?? 'main',
            description: $config['description'] ?? '',
            filePattern: $config['filePattern'] ?? '*.*',
            notPath: $notPath,
            path: $config['path'] ?? null,
            contains: $config['contains'] ?? null,
            notContains: $config['notContains'] ?? null,
            showTreeView: $config['showTreeView'] ?? true,
            server: $server,
            modifiers: $config['modifiers'] ?? [],
            tags: $config['tags'] ?? [],
        );
    }
}
