<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Registry;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Source\SourceInterface;
use Psr\Log\LoggerInterface;

/**
 * Registry for source factories
 */
final class SourceRegistry implements SourceRegistryInterface, SourceProviderInterface
{
    /**
     * @var array<string, SourceFactoryInterface>
     */
    private array $factories = [];

    public function __construct(
        #[LoggerPrefix(prefix: 'source-registry')]
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function register(SourceFactoryInterface $factory): self
    {
        $this->factories[$factory->getType()] = $factory;

        $this->logger?->debug(\sprintf('Registered source factory for type "%s"', $factory->getType()), [
            'factoryClass' => $factory::class,
        ]);

        return $this;
    }

    public function create(string $type, array $config): SourceInterface
    {
        if (!isset($this->factories[$type])) {
            throw new \RuntimeException(\sprintf('Source factory for type "%s" not found', $type));
        }

        $factory = $this->factories[$type];

        return $factory->create($config);
    }

    public function has(string $type): bool
    {
        return isset($this->factories[$type]);
    }
}
