<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Types;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutorInterface;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\Exception\ToolExecutionException;
use Psr\Log\LoggerInterface;

/**
 * Handler for 'run' type tools that execute commands.
 */
#[LoggerPrefix(prefix: 'tool.run')]
final readonly class RunToolHandler extends AbstractToolHandler
{
    /**
     * @param CommandExecutorInterface $commandExecutor The command executor
     * @param bool $executionEnabled Whether command execution is enabled
     * @param LoggerInterface|null $logger Optional logger
     */
    public function __construct(
        private CommandExecutorInterface $commandExecutor,
        private bool $executionEnabled = true,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
    }

    public function supports(string $type): bool
    {
        return $type === 'run';
    }

    protected function doExecute(ToolDefinition $tool): array
    {
        if (!$this->executionEnabled) {
            $this->logger?->warning('Command execution is disabled', [
                'id' => $tool->id,
            ]);

            throw new ToolExecutionException(
                'Command execution is disabled by configuration. Enable it by setting MCP_TOOL_COMMAND_EXECUTION=true',
            );
        }

        if (empty($tool->commands)) {
            throw new ToolExecutionException('Tool has no commands to execute');
        }

        $results = [];
        $success = true;
        $allOutput = '';

        foreach ($tool->commands as $index => $command) {
            $this->logger?->info('Executing command', [
                'index' => $index,
                'command' => $command->cmd,
                'args' => $command->args,
            ]);

            try {
                $result = $this->commandExecutor->execute($command, $tool->env);
                $allOutput .= $result['output'] . PHP_EOL;

                $results[] = [
                    'command' => $command->cmd . ' ' . \implode(' ', $command->args),
                    'output' => $result['output'],
                    'exitCode' => $result['exitCode'],
                    'success' => $result['exitCode'] === 0,
                ];

                if ($result['exitCode'] !== 0) {
                    $success = false;
                }
            } catch (ToolExecutionException $e) {
                $this->logger?->error('Command execution failed', [
                    'index' => $index,
                    'command' => $command->cmd,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'command' => $command->cmd . ' ' . \implode(' ', $command->args),
                    'output' => $e->getMessage(),
                    'exitCode' => -1,
                    'success' => false,
                ];

                $success = false;
                break;
            }
        }

        return [
            'success' => $success,
            'output' => $allOutput,
            'commands' => $results,
        ];
    }
}
