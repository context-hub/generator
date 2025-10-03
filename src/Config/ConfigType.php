<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config;

enum ConfigType: string
{
    case Json = 'json';
    case Yaml = 'yaml';
    case PHP = 'php';

    /**
     * Get the file extension for the config type
     * @return non-empty-string[]
     */
    public static function types(): array
    {
        return \array_map(
            callback: static fn(self $type) => $type->value,
            array: self::cases(),
        );
    }

    public static function fromExtension(string $ext): self
    {
        return match ($ext) {
            'json' => self::Json,
            'yaml', 'yml' => self::Yaml,
            'php' => self::PHP,
            default => throw new \ValueError(message: \sprintf('Unsupported config type: %s', $ext)),
        };
    }
}
