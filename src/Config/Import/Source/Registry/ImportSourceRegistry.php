<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source\Registry;

use Butschster\ContextGenerator\Config\Import\Source\ImportSourceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Registry for import sources
 */
final class ImportSourceRegistry
{
    /**
     * @var array<string, ImportSourceInterface> Import sources indexed by name
     */
    private array $sources = [];

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Register an import source
     */
    public function register(ImportSourceInterface $source): self
    {
        $name = $source->getName();

        $this->sources[$name] = $source;
        $this->logger->debug('Registered import source', ['name' => $name]);

        return $this;
    }

    /**
     * Check if a source with the given name exists
     */
    public function has(string $name): bool
    {
        return isset($this->sources[$name]);
    }

    /**
     * Get a source by name
     *
     * @throws \InvalidArgumentException If no source with the given name exists
     */
    public function get(string $name): ImportSourceInterface
    {
        if (!$this->has(name: $name)) {
            throw new \InvalidArgumentException(message: "Import source not found: {$name}");
        }

        return $this->sources[$name];
    }

    /**
     * Get all registered sources
     *
     * @return array<string, ImportSourceInterface>
     */
    public function all(): array
    {
        return $this->sources;
    }
}
