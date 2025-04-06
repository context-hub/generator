<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Command;

use Butschster\ContextGenerator\McpServer\Tool\Config\ToolCommand;
use Butschster\ContextGenerator\McpServer\Tool\Exception\ToolExecutionException;

/**
 * Interface for command executors.
 */
interface CommandExecutorInterface
{
    /**
     * Executes a command and returns its output.
     *
     * @param ToolCommand $command The command to execute
     * @return array{output: string, exitCode: int} Command execution result containing output and exit code
     * @throws ToolExecutionException If the command execution fails
     */
    public function execute(ToolCommand $command, array $envs = []): array;
}
