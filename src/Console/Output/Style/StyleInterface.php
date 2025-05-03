<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Output\Style;

/**
 * Interface for console output styling configuration
 */
interface StyleInterface
{
    /**
     * Get the success symbol
     */
    public function getSuccessSymbol(): string;

    /**
     * Get the warning symbol
     */
    public function getWarningSymbol(): string;

    /**
     * Get the error symbol
     */
    public function getErrorSymbol(): string;

    /**
     * Get the info symbol
     */
    public function getInfoSymbol(): string;

    /**
     * Get the separator character
     */
    public function getSeparatorChar(): string;

    /**
     * Get the foreground color for titles
     */
    public function getTitleColor(): string;

    /**
     * Get the foreground color for section headers
     */
    public function getSectionColor(): string;

    /**
     * Get the foreground color for success messages
     */
    public function getSuccessColor(): string;

    /**
     * Get the foreground color for error messages
     */
    public function getErrorColor(): string;

    /**
     * Get the foreground color for warning messages
     */
    public function getWarningColor(): string;

    /**
     * Get the foreground color for info messages
     */
    public function getInfoColor(): string;

    /**
     * Get the foreground color for property names
     */
    public function getPropertyColor(): string;

    /**
     * Get the foreground color for labels
     */
    public function getLabelColor(): string;

    /**
     * Get the foreground color for numeric values
     */
    public function getCountColor(): string;

    /**
     * Get the color for dots in key-value pairs
     */
    public function getDotsColor(): string;

    /**
     * Format text with specified color
     */
    public function colorize(string $text, string $color, bool $bold = false): string;

    public function bgColorize(string $label, string $color, bool $bold = true): string;
}
