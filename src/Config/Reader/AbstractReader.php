<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Reader;

use Butschster\ContextGenerator\Config\Exception\ReaderException;
use Psr\Log\LoggerInterface;
use Spiral\Files\Exception\FilesException;
use Spiral\Files\FilesInterface;

/**
 * Base class for configuration readers
 */
abstract readonly class AbstractReader implements ReaderInterface
{
    public function __construct(
        protected FilesInterface $files,
        protected ?LoggerInterface $logger = null,
    ) {}

    public function read(string $path): array
    {
        $this->logger?->debug('Reading config file', [
            'path' => $path,
            'reader' => static::class,
        ]);

        try {
            $content = $this->files->read($path);
        } catch (FilesException) {
            $errorMessage = \sprintf('Unable to read configuration file: %s', $path);
            $this->logger?->error($errorMessage);
            throw new ReaderException(message: $errorMessage);
        }

        $this->logger?->debug('Parsing content', [
            'contentLength' => \strlen(string: $content),
            'reader' => static::class,
        ]);

        try {
            $config = $this->parseContent(content: $content);
            $this->logger?->debug('Content successfully parsed', [
                'reader' => static::class,
            ]);

            return $config;
        } catch (\Throwable $e) {
            $errorMessage = \sprintf('Failed to parse configuration file: %s', $path);
            $this->logger?->error($errorMessage, [
                'error' => $e->getMessage(),
                'reader' => static::class,
            ]);
            throw new ReaderException($errorMessage, previous: $e);
        }
    }

    public function supports(string $path): bool
    {
        if (!$this->files->isFile($path)) {
            return false;
        }

        $extension = \pathinfo(path: $path, flags: PATHINFO_EXTENSION);
        $isSupported = \in_array(needle: $extension, haystack: $this->getSupportedExtensions(), strict: true);

        $this->logger?->debug('Checking if config file is supported', [
            'path' => $path,
            'extension' => $extension,
            'isSupported' => $isSupported,
            'reader' => static::class,
        ]);

        return $isSupported;
    }

    /**
     * Parse the raw content into a configuration array
     *
     * @param string $content Raw configuration content
     * @return array<mixed> Parsed configuration data
     * @throws \Throwable If parsing fails
     */
    abstract protected function parseContent(string $content): array;
}
