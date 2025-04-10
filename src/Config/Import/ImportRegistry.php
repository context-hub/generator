<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import;

use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Spiral\Core\Attribute\Singleton;

/**
 * @implements RegistryInterface<SourceConfigInterface>
 */
#[Singleton]
final class ImportRegistry implements RegistryInterface
{
    /** @var array<SourceConfigInterface> */
    private array $imports = [];

    /**
     * Register an import in the registry
     */
    public function register(SourceConfigInterface $import): self
    {
        $this->imports[] = $import;

        return $this;
    }

    /**
     * Gets the type of the registry.
     */
    public function getType(): string
    {
        return 'import';
    }

    /**
     * Gets all items in the registry.
     *
     * @return array<SourceConfigInterface>
     */
    public function getItems(): array
    {
        return $this->imports;
    }

    public function jsonSerialize(): array
    {
        return [
            'imports' => $this->imports,
        ];
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->getItems());
    }
}
