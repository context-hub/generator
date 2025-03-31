<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Registry;

/**
 * Common interface for all registries
 * @template TItem of mixed
 */
interface RegistryInterface extends \JsonSerializable
{
    /**
     * Get the registry type identifier
     */
    public function getType(): string;

    /**
     * Get all items in the registry
     *
     * @return array<TItem>
     */
    public function getItems(): array;
}
