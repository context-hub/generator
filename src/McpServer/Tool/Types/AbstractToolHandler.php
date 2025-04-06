<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Types;

use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for tool type handlers.
 */
abstract readonly class AbstractToolHandler implements ToolHandlerInterface
{
    public function __construct(
        protected ?LoggerInterface $logger = null,
    ) {}

    public function execute(ToolDefinition $tool): array
    {
        $this->logger?->info('Executing tool', [
            'id' => $tool->id,
        ]);

        try {
            $result = $this->doExecute($tool);

            $this->logger?->info('Tool execution completed', [
                'id' => $tool->id,
                'success' => true,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger?->error('Tool execution failed', [
                'id' => $tool->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    /**
     * Performs the actual tool execution.
     *
     * @param ToolDefinition $tool The tool to execute
     * @return array<string, mixed> Execution result
     * @throws \Throwable If execution fails
     */
    abstract protected function doExecute(ToolDefinition $tool): array;
}
