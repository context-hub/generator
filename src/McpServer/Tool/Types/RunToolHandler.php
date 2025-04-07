<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Types;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutorInterface;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolCommand;
use Butschster\ContextGenerator\McpServer\Tool\Exception\ToolExecutionException;
use Butschster\ContextGenerator\McpServer\Tool\Provider\ToolArgumentsProvider;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Psr\Log\LoggerInterface;

#[LoggerPrefix(prefix: 'tool.run')]
final readonly class RunToolHandler extends AbstractToolHandler
{
    public function __construct(
        private CommandExecutorInterface $commandExecutor,
        private bool $executionEnabled = true,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
    }

    public function supports(string $type): bool
    {
        return true; // Default handler for all tool types
    }

    protected function doExecute(ToolDefinition $tool, array $arguments = []): array
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

        return $this->executeCommands($tool, $tool->commands, $arguments);
    }

    /**
     * Execute commands with optional arguments.
     *
     * @param ToolDefinition $tool The tool being executed
     * @param array<ToolCommand> $commands Commands to execute
     * @param array<string, mixed> $arguments Arguments for variable replacement
     * @return array<string, mixed> Execution result
     */
    private function executeCommands(ToolDefinition $tool, array $commands, array $arguments = []): array
    {
        $results = [];
        $success = true;
        $allOutput = '';

        foreach ($commands as $index => $command) {
            $this->logger?->info('Executing command', [
                'index' => $index,
                'command' => $command->cmd,
                'args' => $command->args,
            ]);

            try {
                $processedCommand = $this->processCommandWithArguments($tool, $command, $arguments);

                $result = $this->commandExecutor->execute($processedCommand, $tool->env);
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

    /**
     * Process a command by replacing argument placeholders.
     *
     * @param ToolDefinition $tool The tool definition with schema information
     * @param ToolCommand $command The command to process
     * @param array<string, mixed> $arguments The arguments to use for replacement
     * @return ToolCommand The processed command
     */
    private function processCommandWithArguments(
        ToolDefinition $tool,
        ToolCommand $command,
        array $arguments,
    ): ToolCommand {
        // Create a processor for the command with arguments, including schema for type casting
        $processor = new VariableReplacementProcessor(
            new ToolArgumentsProvider($arguments, $tool->schema),
        );

        // Process each argument
        $processedArgs = [];
        foreach ($command->args as $arg) {
            $processedArgs[] = $processor->process($arg);
        }

        // Return a new command with processed values
        return new ToolCommand(
            $command->cmd,
            $processedArgs,
            $command->workingDir,
            $command->env,
        );
    }
}
