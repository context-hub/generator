<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import;

use Butschster\ContextGenerator\Config\Import\Source\Config\SourceConfigInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Spiral\Core\Attribute\Singleton;

/**
 * @template TImport of SourceConfigInterface
 * @implements RegistryInterface<TImport>
 */
#[Singleton]
final class ImportRegistry implements RegistryInterface
{
    /** @var list<TImport> */
    private array $imports = [];

    /**
     * Register an import in the registry
     * @param TImport $import
     */
    public function register(SourceConfigInterface $import): self
    {
        $this->imports[] = $import;

        return $this;
    }

    public function getType(): string
    {
        return 'import';
    }

    public function getItems(): array
    {
        return $this->imports;
    }

    public function jsonSerialize(): array
    {
        return $this->imports;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->getItems());
    }
}
