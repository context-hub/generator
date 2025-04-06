<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Git\Exception;

/**
 * Exception thrown when a Git command fails
 */
final class GitClientException extends \RuntimeException
{
    /**
     * @param string $command The git command that failed
     * @param int $exitCode The exit code returned by the command
     * @param array<string> $errorOutput The error output from the command
     */
    public function __construct(
        private readonly string $command,
        private readonly int $exitCode,
        private readonly array $errorOutput = [],
    ) {
        $message = \sprintf(
            'Git command "%s" failed with exit code %d: %s',
            $command,
            $exitCode,
            \implode("\n", $errorOutput),
        );

        parent::__construct($message, $exitCode);
    }

    /**
     * Get the Git command that failed
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * Get the exit code returned by the command
     */
    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    /**
     * Get the error output from the command
     *
     * @return array<string>
     */
    public function getErrorOutput(): array
    {
        return $this->errorOutput;
    }
}
