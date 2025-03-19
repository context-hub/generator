<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable\Provider;

/**
 * Provider that aggregates multiple providers with prioritization
 */
final class CompositeVariableProvider implements VariableProviderInterface
{
    /**
     * @var array<VariableProviderInterface> List of providers in priority order
     */
    private array $providers = [];

    /**
     * @param array<VariableProviderInterface> $providers
     */
    public function __construct(
        VariableProviderInterface ...$providers
    ) {
        foreach ($providers as $provider) {
            $this->addProvider($provider);
        }
    }

    /**
     * Add a provider with the lowest priority
     */
    public function addProvider(VariableProviderInterface $provider): self
    {
        $this->providers[] = $provider;
        return $this;
    }

    /**
     * Add a provider with the highest priority
     */
    public function addProviderWithHighPriority(VariableProviderInterface $provider): self
    {
        array_unshift($this->providers, $provider);
        return $this;
    }

    public function has(string $name): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->has($name)) {
                return true;
            }
        }

        return false;
    }

    public function get(string $name): ?string
    {
        foreach ($this->providers as $provider) {
            if ($provider->has($name)) {
                return $provider->get($name);
            }
        }

        return null;
    }

    /**
     * @return array<VariableProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
