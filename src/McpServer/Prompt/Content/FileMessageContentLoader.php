<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Content;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Prompt\Exception\FileMessageContentException;
use Butschster\ContextGenerator\McpServer\Prompt\Exception\PromptParsingException;
use Psr\Log\LoggerInterface;

/**
 * Loads message content from external files or URLs using the 'file' property.
 */
#[LoggerPrefix(prefix: 'prompt.file-loader')]
final readonly class FileMessageContentLoader extends MessageContentLoader
{
    /**
     * @param FileContentProvider[] $providers
     */
    public function __construct(
        private LoggerInterface $logger,
        private array $providers = [],
    ) {}

    public function canHandle(array $messageConfig): bool
    {
        return isset($messageConfig['file']) && \is_string($messageConfig['file']);
    }

    public function loadContent(array $messageConfig): string
    {
        $this->validateMessageConfig($messageConfig);

        if (!isset($messageConfig['file']) || !\is_string($messageConfig['file'])) {
            throw new PromptParsingException('Message must have a valid file property');
        }

        // Validate that both content and file are not specified
        if (isset($messageConfig['content'])) {
            throw new PromptParsingException('Message cannot have both content and file properties');
        }

        $filePath = $messageConfig['file'];

        if (empty(\trim($filePath))) {
            throw new PromptParsingException('File path cannot be empty');
        }

        // Find appropriate provider
        $provider = $this->findProvider($filePath);

        if ($provider === null) {
            throw new PromptParsingException(
                \sprintf('No suitable provider found for file source: %s', $filePath),
            );
        }

        try {
            $this->logger->debug('Loading message content from file', [
                'source' => $filePath,
                'provider' => $provider::class,
            ]);

            $content = $provider->load($filePath);

            $this->logger->debug('Successfully loaded message content', [
                'source' => $filePath,
                'contentLength' => \strlen($content),
            ]);

            return $content;
        } catch (FileMessageContentException $e) {
            $this->logger->error('Failed to load message content from file', [
                'source' => $filePath,
                'error' => $e->getMessage(),
            ]);

            throw new PromptParsingException(
                \sprintf('Failed to load content from "%s": %s', $filePath, $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * Finds the appropriate provider for the given file path.
     */
    private function findProvider(string $filePath): ?FileContentProvider
    {
        // Try providers in order
        foreach ($this->providers as $provider) {
            if ($provider->canHandle($filePath)) {
                return $provider;
            }
        }

        return null;
    }
}
