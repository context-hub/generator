<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Reader;

use Butschster\ContextGenerator\ConfigLoader\Exception\ReaderException;
use Psr\Log\LoggerInterface;

/**
 * Reader for JSON configuration provided as a string
 */
final readonly class StringJsonReader implements ReaderInterface
{
    private string $jsonContent;

    public function __construct(
        string $jsonContent,
        private ?LoggerInterface $logger = null,
    ) {
        $this->jsonContent = $jsonContent;
    }

    public function read(string $path): array
    {
        $this->logger?->debug('Reading JSON configuration from string', [
            'contentLength' => \strlen($this->jsonContent),
        ]);

        try {
            $config = \json_decode($this->jsonContent, true, flags: JSON_THROW_ON_ERROR);

            if (!\is_array($config)) {
                throw new ReaderException('JSON configuration must decode to an array');
            }

            $this->logger?->debug('JSON string successfully parsed');
            return $config;
        } catch (\JsonException $e) {
            $errorMessage = 'Invalid JSON in configuration string';
            $this->logger?->error($errorMessage, [
                'error' => $e->getMessage(),
            ]);
            throw new ReaderException($errorMessage, previous: $e);
        }
    }

    public function supports(string $path): bool
    {
        // This reader doesn't care about the path - it always supports reading from its string
        return true;
    }
}
