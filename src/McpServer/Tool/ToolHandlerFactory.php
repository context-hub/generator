<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool;

use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\Types\HttpToolHandler;
use Butschster\ContextGenerator\McpServer\Tool\Types\RunToolHandler;
use Butschster\ContextGenerator\McpServer\Tool\Types\ToolHandlerInterface;
use Psr\Container\ContainerInterface;
use Spiral\Core\Attribute\Singleton;

/**
 * Factory for creating tool handlers based on tool type.
 */
#[Singleton]
final readonly class ToolHandlerFactory
{
    /**
     * @param array<string, class-string<ToolHandlerInterface>> $handlers Mapping of tool types to handler classes
     */
    public function __construct(
        private ContainerInterface $container,
        private array $handlers = [
            'run' => RunToolHandler::class,
            'http' => HttpToolHandler::class,
        ],
    ) {}

    /**
     * Creates a handler for the given tool.
     */
    public function createHandlerForTool(ToolDefinition $tool): ToolHandlerInterface
    {
        // First try to find a handler that explicitly supports this tool type
        foreach ($this->getHandlerInstances() as $handler) {
            if ($handler->supports($tool->type)) {
                return $handler;
            }
        }

        // If no handler declares explicit support, use the class mapping
        if (isset($this->handlers[$tool->type])) {
            return $this->container->get($this->handlers[$tool->type]);
        }

        // Fallback to the default run handler
        return $this->container->get(RunToolHandler::class);
    }

    /**
     * Get all registered handler instances.
     *
     * @return array<ToolHandlerInterface>
     */
    private function getHandlerInstances(): array
    {
        $instances = [];
        foreach ($this->handlers as $handlerClass) {
            $instances[] = $this->container->get($handlerClass);
        }
        return $instances;
    }
}
