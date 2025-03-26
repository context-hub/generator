<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Lib\Variable\Provider\CompositeVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\DotEnvVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\PredefinedVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Dotenv\Repository\RepositoryBuilder;
use Psr\Log\LoggerInterface;

final readonly class VariableResolverFactory
{
    public function __construct(
        private ?string $defaultEnvFilePath = null,
        private ?string $defaultEnvFileName = null,
    ) {}

    public function create(
        LoggerInterface $logger,
        ?string $envFilePath = null,
        ?string $envFileName = null,
    ): VariableResolver {
        return new VariableResolver(
            processor: new VariableReplacementProcessor(
                provider: new CompositeVariableProvider(
                    envProvider: new DotEnvVariableProvider(
                        repository: RepositoryBuilder::createWithDefaultAdapters()->make(),
                        rootPath: $envFilePath ?? $this->defaultEnvFilePath,
                        envFileName: $envFileName ?? $this->defaultEnvFileName,
                    ),
                    predefinedProvider: new PredefinedVariableProvider(),
                ),
                logger: $logger->withPrefix('variable-resolver'),
            ),
        );
    }
}
