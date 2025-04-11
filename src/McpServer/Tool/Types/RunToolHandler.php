<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Types;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutorInterface;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolCommand;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolArg;
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
        return $type === 'run'; // Only supports 'run' type tools
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
                'args' => \array_map(static fn(ToolArg $arg) => (string) $arg, $command->args),
            ]);

            try {
                $processedCommand = $this->processCommandWithArguments($tool, $command, $arguments);

                $result = $this->commandExecutor->execute($processedCommand, $tool->env);
                $allOutput .= $result['output'] . PHP_EOL;

                // Create a readable command string for reporting
                $commandStr = $command->cmd . ' ' . $this->formatArgsForDisplay($command->args);

                $results[] = [
                    'command' => $commandStr,
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

                // Create a readable command string for reporting
                $commandStr = $command->cmd . ' ' . $this->formatArgsForDisplay($command->args);

                $results[] = [
                    'command' => $commandStr,
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
        // Create arguments provider
        $argsProvider = new ToolArgumentsProvider($arguments, $tool->schema);

        // Create a processor for variable replacement
        $processor = new VariableReplacementProcessor($argsProvider);

        // Process each argument, evaluating conditions
        $processedArgs = [];
        foreach ($command->args as $arg) {
            // If there's a condition, evaluate it
            if ($arg->when !== null) {
                $condition = $processor->process($arg->when);
                // Skip the argument if the condition evaluates to false
                if (!$this->evaluateCondition($condition)) {
                    $this->logger?->debug('Skipping conditional argument', [
                        'argument' => $arg->name,
                        'condition' => $arg->when,
                    ]);
                    continue;
                }
            }

            // Process the argument name (replacing variables)
            $processedArgs[] = new ToolArg(
                name: $processor->process($arg->name),
            );
        }

        // Return a new command with processed values
        return new ToolCommand(
            $command->cmd,
            $processedArgs,
            $command->workingDir,
            $command->env,
        );
    }

    /**
     * Formats an array of arguments for display in logs and results.
     *
     * @param array<ToolArg> $args The arguments to format
     * @return string The formatted arguments string
     */
    private function formatArgsForDisplay(array $args): string
    {
        return \implode(' ', \array_map(static fn(ToolArg $arg) => $arg->name, $args));
    }

    /**
     * Evaluates a condition string to determine if an argument should be included.
     *
     * @param string $condition The condition string to evaluate
     * @return bool Whether the condition evaluates to true
     */
    private function evaluateCondition(string $condition): bool
    {
        // Normalize the condition string to evaluate as boolean
        $normalizedCondition = \strtolower(\trim($condition));

        // Common boolean string representations
        if ($normalizedCondition === '' ||
            $normalizedCondition === 'false' ||
            $normalizedCondition === '0' ||
            $normalizedCondition === 'no' ||
            $normalizedCondition === 'n' ||
            $normalizedCondition === 'null' ||
            $normalizedCondition === 'undefined') {
            return false;
        }

        // Everything else is considered true
        return true;
    }
}
