<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Exclude;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Parser plugin for the 'exclude' section in configuration
 */
final readonly class ExcludeParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private ExcludeRegistryInterface $registry,
        #[LoggerPrefix(prefix: 'exclude-parser')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        return 'exclude';
    }

    public function supports(array $config): bool
    {
        return isset($config['exclude']) && \is_array($config['exclude']);
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports($config)) {
            return null;
        }

        \assert($this->registry instanceof RegistryInterface);
        $excludeConfig = $config['exclude'];

        // Parse patterns
        if (isset($excludeConfig['patterns']) && \is_array($excludeConfig['patterns'])) {
            $this->parsePatterns($excludeConfig['patterns']);
        }

        // Parse paths
        if (isset($excludeConfig['paths']) && \is_array($excludeConfig['paths'])) {
            $this->parsePaths($excludeConfig['paths']);
        }

        $this->logger?->info('Parsed exclusion configuration', [
            'patternCount' => \count($this->registry->getPatterns()),
        ]);

        return $this->registry;
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        // We don't need to modify the config, just return it as is
        return $config;
    }

    /**
     * Parse glob pattern exclusions
     */
    private function parsePatterns(array $patterns): void
    {
        foreach ($patterns as $pattern) {
            if (!\is_string($pattern) || empty($pattern)) {
                $this->logger?->warning('Invalid exclusion pattern, skipping', [
                    'pattern' => $pattern,
                ]);
                continue;
            }

            $this->registry->addPattern(new PatternExclusion($pattern));
        }
    }

    /**
     * Parse path exclusions
     */
    private function parsePaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (!\is_string($path) || empty($path)) {
                $this->logger?->warning('Invalid exclusion path, skipping', [
                    'path' => $path,
                ]);
                continue;
            }

            $this->registry->addPattern(new PathExclusion($path));
        }
    }
}
