<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Registry;

use Butschster\ContextGenerator\Config\Import\ImportRegistry;
use Butschster\ContextGenerator\Document\DocumentRegistry;
use Butschster\ContextGenerator\McpServer\Prompt\PromptRegistry;
use Butschster\ContextGenerator\McpServer\Tool\ToolRegistry;

/**
 * Utility class for accessing specific registry types from ConfigRegistry
 */
final readonly class ConfigRegistryAccessor
{
    public function __construct(
        private ConfigRegistry $registry,
    ) {}

    /**
     * Get document registry
     */
    public function getDocuments(): ?DocumentRegistry
    {
        return $this->getRegistry(type: 'documents', className: DocumentRegistry::class);
    }

    /**
     * Get prompt registry
     */
    public function getPrompts(): ?PromptRegistry
    {
        return $this->getRegistry(type: 'prompts', className: PromptRegistry::class);
    }

    /**
     * Get tool registry
     */
    public function getTools(): ?ToolRegistry
    {
        return $this->getRegistry(type: 'tools', className: ToolRegistry::class);
    }

    /**
     * Get import registry
     */
    public function getImports(): ?ImportRegistry
    {
        return $this->getRegistry(type: 'import', className: ImportRegistry::class);
    }

    /**
     * Get a specific registry by type and class
     *
     * @template T of RegistryInterface
     * @param string $type Registry type identifier
     * @param class-string<T> $className Expected registry class
     * @return T|null The registry or null if not found
     */
    private function getRegistry(string $type, string $className): ?RegistryInterface
    {
        if (!$this->registry->has(type: $type)) {
            return null;
        }

        try {
            return $this->registry->get(type: $type, className: $className);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
