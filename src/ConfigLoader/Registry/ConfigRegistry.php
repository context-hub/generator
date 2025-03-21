<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\ConfigLoader\Registry;

/**
 * Container for all registries
 */
final class ConfigRegistry
{
    /** @var array<string, RegistryInterface> */
    private array $registries = [];

    /**
     * Register a new registry
     */
    public function register(RegistryInterface $registry): self
    {
        $this->registries[$registry->getType()] = $registry;

        return $this;
    }

    /**
     * Check if a registry with the given type exists
     */
    public function has(string $type): bool
    {
        return isset($this->registries[$type]);
    }

    /**
     * Get a registry by type
     *
     * @template T of RegistryInterface
     * @param class-string<T> $className Optional class name to validate the registry type
     * @return T The requested registry
     *
     * @throws \InvalidArgumentException If the registry does not exist or is not of the expected type
     */
    public function get(string $type, string $className = RegistryInterface::class): RegistryInterface
    {
        if (!$this->has($type)) {
            throw new \InvalidArgumentException(\sprintf('Registry of type "%s" does not exist', $type));
        }

        $registry = $this->registries[$type];

        if (!$registry instanceof $className) {
            throw new \InvalidArgumentException(
                \sprintf('Registry of type "%s" is not an instance of "%s"', $type, $className),
            );
        }

        return $registry;
    }

    /**
     * Get all registered registries
     *
     * @return array<string, RegistryInterface>
     */
    public function all(): array
    {
        return $this->registries;
    }
}
