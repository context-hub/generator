<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Reader;

use Butschster\ContextGenerator\Config\Exception\ReaderException;
use Psr\Log\LoggerInterface;

/**
 * Reader for JSON configuration provided as a string
 */
final readonly class StringJsonReader implements ReaderInterface
{
    public function __construct(private string $jsonContent, private ?LoggerInterface $logger = null) {}

    public function read(string $path): array
    {
        $this->logger?->debug('Reading JSON configuration from string', [
            'contentLength' => \strlen(string: $this->jsonContent),
        ]);

        try {
            $config = \json_decode($this->jsonContent, true, flags: JSON_THROW_ON_ERROR);

            if (!\is_array(value: $config)) {
                throw new ReaderException(message: 'JSON configuration must decode to an array');
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

    public function getSupportedExtensions(): array
    {
        return [];
    }
}
