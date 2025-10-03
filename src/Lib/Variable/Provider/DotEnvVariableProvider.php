<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable\Provider;

use Dotenv\Dotenv;
use Dotenv\Repository\RepositoryInterface;

final readonly class DotEnvVariableProvider implements VariableProviderInterface
{
    private RepositoryInterface $repository;

    public function __construct(
        RepositoryInterface $repository,
        private ?string $rootPath = null,
        private ?string $envFileName = null,
    ) {
        if ($this->rootPath) {
            $dotenv = Dotenv::create(repository: $repository, paths: $this->rootPath, names: $this->envFileName);
            $dotenv->load();
        }

        $this->repository = $repository;
    }

    public function has(string $name): bool
    {
        return $this->repository->has($name);
    }

    public function get(string $name): ?string
    {
        return $this->repository->get($name);
    }
}
