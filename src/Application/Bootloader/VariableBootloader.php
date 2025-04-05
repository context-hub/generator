<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Config\Parser\VariablesParserPlugin;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\Variable\Provider\CompositeVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\ConfigVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\DotEnvVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\PredefinedVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Dotenv\Repository\RepositoryBuilder;
use Spiral\Boot\Bootloader\Bootloader;

final class VariableBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            // Singleton provider for variables from config
            ConfigVariableProvider::class => static fn() => new ConfigVariableProvider(),

            // Parser plugin for extracting variables from config
            VariablesParserPlugin::class => static fn(
                ConfigVariableProvider $variableProvider,
                HasPrefixLoggerInterface $logger,
            ) => new VariablesParserPlugin(
                variableProvider: $variableProvider,
                logger: $logger->withPrefix('variables-parser'),
            ),

            VariableResolver::class => static function (
                DirectoriesInterface $dirs,
                HasPrefixLoggerInterface $logger,
                ConfigVariableProvider $configVariableProvider,
            ) {
                $envFilePath = null;
                $envFileName = null;

                if ($dirs->getEnvFilePath() !== null) {
                    $envFilePath = (string) $dirs->getEnvFilePath();
                    $envFileName = $dirs->getEnvFilePath()->name();
                }

                return new VariableResolver(
                    processor: new VariableReplacementProcessor(
                        provider: new CompositeVariableProvider(
                            $configVariableProvider,

                            // Environment variables have middle priority
                            new DotEnvVariableProvider(
                                repository: RepositoryBuilder::createWithDefaultAdapters()->make(),
                                rootPath: $envFilePath,
                                envFileName: $envFileName,
                            ),

                            // Predefined system variables have lowest priority
                            new PredefinedVariableProvider(),
                        ),
                        logger: $logger->withPrefix('variable-resolver'),
                    ),
                );
            },
        ];
    }

    public function boot(
        ConfigLoaderBootloader $configLoaderBootloader,
        VariablesParserPlugin $variablesParserPlugin,
    ): void {
        // Register the variables parser plugin with the config loader
        $configLoaderBootloader->registerParserPlugin($variablesParserPlugin);
    }
}
