<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Git;

/**
 * A value object that represents a Git command to be executed.
 */
final readonly class Command implements \Stringable
{
    /**
     * @param string $repository Path to the Git repository
     * @param array<string>|string $command Git command to execute (without 'git' prefix)
     */
    public function __construct(
        public string $repository,
        private array|string $command,
    ) {}

    /**
     * Get the command as an array.
     *
     * @return array<string>
     */
    public function getCommandParts(): array
    {
        if (\is_array($this->command)) {
            return $this->command;
        }

        $command = \trim($this->command);

        // If the command already starts with 'git', remove it
        if (\str_starts_with($command, 'git ')) {
            $command = \substr($command, 4);
        }

        return \array_filter(\explode(' ', $command));
    }

    public function __toString(): string
    {
        if (\is_string($this->command)) {
            $command = \trim($this->command);

            // If the command already starts with 'git', use it as is
            if (\str_starts_with($command, 'git ')) {
                $command = \substr($command, 4);
            }

            return $command;
        }

        return \implode(' ', $this->command);
    }
}
