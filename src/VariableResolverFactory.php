<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Lib\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Lib\Variable\Provider\CompositeVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\DotEnvVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\PredefinedVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Dotenv\Repository\RepositoryBuilder;

final readonly class VariableResolverFactory
{
    public function __construct(
        private HasPrefixLoggerInterface $logger,
        private ?string $defaultEnvFilePath = null,
        private ?string $defaultEnvFileName = null,
    ) {}

    public function create(
        Directories $dirs,
    ): VariableResolver {
        $envFilePath = $this->defaultEnvFilePath;
        $envFileName = $this->defaultEnvFileName;

        if ($dirs->envFilePath !== null) {
            $envFilePath = \dirname($dirs->envFilePath);
            $envFileName = \pathinfo($dirs->envFilePath, PATHINFO_BASENAME);
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
                logger: $this->logger->withPrefix('variable-resolver'),
            ),
        );
    }
}
