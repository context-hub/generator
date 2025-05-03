<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Output;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface for standardized console output generation
 */
interface OutputServiceInterface
{
    /**
     * Write a title block with consistent formatting
     */
    public function title(string $title): void;

    /**
     * Write a section header with consistent formatting
     */
    public function section(string $title): void;

    /**
     * Write a success message with consistent formatting
     */
    public function success(string $message): void;

    /**
     * Write an error message with consistent formatting
     */
    public function error(string $message): void;

    /**
     * Write a warning message with consistent formatting
     */
    public function warning(string $message): void;

    /**
     * Write an info message with consistent formatting
     */
    public function info(string $message): void;

    /**
     * Write a note message with consistent formatting
     */
    public function note(string|array $messages): void;

    /**
     * Write a key-value line with consistent formatting and alignment
     */
    public function keyValue(string $key, string $value, int $width = 90): void;

    /**
     * Render a table with consistent styling
     *
     * @param array<int, string> $headers Table headers
     * @param array<int, array<int, mixed>> $rows Table rows
     */
    public function table(array $headers, array $rows): void;

    /**
     * Render a list of items with their status
     *
     * @param array<string, array{status: string, message: string}> $items
     */
    public function statusList(array $items): void;

    /**
     * Write a separator line
     */
    public function separator(int $length = 80): void;

    /**
     * Format text with highlight styling
     */
    public function highlight(string $text, string $color = 'bright-green', bool $bold = false): string;

    /**
     * Format text as a property name
     */
    public function formatProperty(string $text): string;

    /**
     * Format text as a label
     */
    public function formatLabel(string $text): string;

    /**
     * Format numeric value (count, etc.)
     */
    public function formatCount(int $count): string;

    /**
     * Get the underlying output interface
     */
    public function getOutput(): OutputInterface;
}
