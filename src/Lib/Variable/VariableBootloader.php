<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable;

use Butschster\ContextGenerator\Config\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Config\Parser\VariablesParserPlugin;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\Variable\Provider\CompositeVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\ConfigVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\DotEnvVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\PredefinedVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\VariableProviderInterface;
use Dotenv\Repository\RepositoryBuilder;
use Spiral\Boot\Bootloader\Bootloader;

final class VariableBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ConfigVariableProvider::class => ConfigVariableProvider::class,
            VariablesParserPlugin::class => VariablesParserPlugin::class,

            VariableProviderInterface::class => static function (
                ConfigVariableProvider $configVariableProvider,
                DirectoriesInterface $dirs,
            ) {
                $envFilePath = null;
                $envFileName = null;

                if ($dirs->getEnvFilePath() !== null) {
                    $envFilePath = (string) ($dirs->getEnvFilePath()->isFile() ?
                        $dirs->getEnvFilePath()->parent() :
                        $dirs->getEnvFilePath());
                    $envFileName = $dirs->getEnvFilePath()->name();
                }

                return new CompositeVariableProvider(
                    $configVariableProvider,

                    // Environment variables have middle priority
                    new DotEnvVariableProvider(
                        repository: RepositoryBuilder::createWithDefaultAdapters()->make(),
                        rootPath: $envFilePath,
                        envFileName: $envFileName,
                    ),

                    // Predefined system variables have lowest priority
                    new PredefinedVariableProvider(),
                );
            },

            VariableReplacementProcessorInterface::class => static fn(
                VariableReplacementProcessor $replacementProcessor,
            ) => new CompositeProcessor(processors: [
                $replacementProcessor,
            ]),

            VariableResolver::class => VariableResolver::class,
        ];
    }

    public function boot(
        ConfigLoaderBootloader $configLoaderBootloader,
        VariablesParserPlugin $variablesParserPlugin,
    ): void {
        // Register the variables parser plugin with the config loader
        $configLoaderBootloader->registerParserPlugin(plugin: $variablesParserPlugin);
    }
}
