<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Command;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolCommand;
use Butschster\ContextGenerator\McpServer\Tool\Exception\ToolExecutionException;
use Psr\Log\LoggerInterface;
use Spiral\Boot\EnvironmentInterface;
use Symfony\Component\Process\Process;

/**
 * Executes commands using Symfony Process component.
 */
final readonly class CommandExecutor implements CommandExecutorInterface
{
    public function __construct(
        private EnvironmentInterface $envs,
        private string $projectRoot,
        private int $timeout = 30,
        #[LoggerPrefix(prefix: 'tool.command.executor')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function execute(ToolCommand $command, array $envs = []): array
    {
        $this->logger?->info('Executing command', [
            'command' => $command->cmd,
            'args' => $command->args,
        ]);

        // Determine working directory
        $workingDir = FSPath::create($this->projectRoot);

        if ($command->workingDir !== null) {
            $workingDir = $workingDir->join($command->workingDir);
        }

        // Create the process
        $process = new Process(
            command: \array_merge([$command->cmd], $command->args),
            cwd: (string) $workingDir,
            env: \array_merge($this->envs->getAll(), $envs, $command->env),
            timeout: $this->timeout,
        );

        try {
            $process->run();

            $output = $process->getOutput() . $process->getErrorOutput();
            $exitCode = $process->getExitCode() ?? -1;

            if ($exitCode !== 0) {
                $this->logger?->warning('Command exited with non-zero code', [
                    'command' => $command->cmd,
                    'args' => $command->args,
                    'exitCode' => $exitCode,
                    'output' => $output,
                ]);
            } else {
                $this->logger?->info('Command executed successfully', [
                    'command' => $command->cmd,
                    'args' => $command->args,
                    'exitCode' => $exitCode,
                ]);
            }

            return [
                'output' => $output,
                'exitCode' => $exitCode,
            ];
        } catch (\Throwable $e) {
            $this->logger?->error('Command execution failed', [
                'command' => $command->cmd,
                'args' => $command->args,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw ToolExecutionException::fromCommand(
                command: $command->cmd,
                args: $command->args,
                reason: $e->getMessage(),
                previous: $e,
            );
        }
    }
}
