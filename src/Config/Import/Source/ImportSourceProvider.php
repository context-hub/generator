<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source;

use Butschster\ContextGenerator\Config\Reader\ConfigReaderRegistry;
use Butschster\ContextGenerator\Lib\GithubClient\GithubClientInterface;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Source\Composer\Client\ComposerClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spiral\Files\FilesInterface;

/**
 * Service provider for registering import sources
 */
final readonly class ImportSourceProvider
{
    public function __construct(
        private ConfigReaderRegistry $readers,
        private FilesInterface $files,
        private HttpClientInterface $httpClient,
        private GithubClientInterface $githubClient,
        private ComposerClientInterface $composerClient,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Get a logger with a source-specific prefix
     */
    private function getSourceLogger(string $sourceName): LoggerInterface
    {
        if ($this->logger === null) {
            return new NullLogger();
        }

        // Check if logger supports prefixing
        if (\method_exists($this->logger, 'withPrefix')) {
            return $this->logger->withPrefix("import-{$sourceName}");
        }

        return $this->logger;
    }
}
