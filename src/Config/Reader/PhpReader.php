<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Reader;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Exception\ReaderException;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;

/**
 * Reader for PHP configuration files
 */
#[LoggerPrefix(prefix: 'php-reader')]
final readonly class PhpReader extends AbstractReader
{
    #[\Override]
    public function read(string $path): array
    {
        $this->logger?->debug('Reading PHP config file', [
            'path' => $path,
        ]);

        if (!$this->supports($path)) {
            throw new ReaderException(\sprintf('Unsupported configuration file: %s', $path));
        }

        // PHP files are special since they can return Registry objects directly
        $result = require $path;

        // If it's a Registry, extract its JSON representation
        if ($result instanceof RegistryInterface) {
            return $result->jsonSerialize();
        }

        // If it's an array, return it directly
        if (\is_array($result)) {
            return $result;
        }

        throw new ReaderException(
            \sprintf(
                'PHP configuration file must return an array or a RegistryInterface instance, got %s',
                \gettype($result),
            ),
        );
    }

    public function getSupportedExtensions(): array
    {
        return ['php'];
    }

    protected function parseContent(string $content): array
    {
        // Not used for PHP files since we use require directly
        throw new \LogicException('PHP files are not parsed from content, this method should not be called');
    }
}
