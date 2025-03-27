<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

/**
 * @deprecated
 */
final readonly class ConfigurationProviderConfig
{
    public function __construct(
        public string $rootPath,
        public ?string $configPath = null,
        public ?string $inlineConfig = null,
        public array $parserPlugins = [],
    ) {}
}
