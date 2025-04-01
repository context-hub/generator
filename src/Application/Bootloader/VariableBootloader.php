<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\Variable\Provider\CompositeVariableProvider;
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
            VariableResolver::class => static function (
                DirectoriesInterface $dirs,
                HasPrefixLoggerInterface $logger,
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
                            envProvider: new DotEnvVariableProvider(
                                repository: RepositoryBuilder::createWithDefaultAdapters()->make(),
                                rootPath: $envFilePath,
                                envFileName: $envFileName,
                            ),
                            predefinedProvider: new PredefinedVariableProvider(),
                        ),
                        logger: $logger->withPrefix('variable-resolver'),
                    ),
                );
            },
        ];
    }
}
