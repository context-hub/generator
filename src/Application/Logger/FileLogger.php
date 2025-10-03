<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Logger;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\TagProcessor;

/**
 * @psalm-suppress InvalidExtendClass
 */
final class FileLogger extends Logger implements HasPrefixLoggerInterface
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $filePath
     * @psalm-suppress ImplementedParamTypeMismatch
     * @psalm-suppress ConstructorSignatureMismatch
     * @psalm-suppress ParamNameMismatch
     * @psalm-suppress MethodSignatureMismatch
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function __construct(string $name, string $filePath, Level $level)
    {
        parent::__construct($name, [
            new RotatingFileHandler(filename: $filePath, level: $level),
        ], [
            new TagProcessor(),
        ]);
    }

    public function withPrefix(string $prefix): self
    {
        if ($this->getProcessors() !== []) {
            $this->popProcessor();
        }

        $this->pushProcessor(
            callback: (new TagProcessor())->addTags(tags: [$prefix]),
        );

        return $this;
    }

    public function getPrefix(): string
    {
        return '';
    }
}
