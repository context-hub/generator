<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Output\Style;

/**
 * Default implementation of StyleInterface with the application's standard styling
 */
final readonly class DefaultStyle implements StyleInterface
{
    // Symbols for different status types
    private const string SUCCESS_SYMBOL = '✓';
    private const string WARNING_SYMBOL = '!';
    private const string ERROR_SYMBOL = '✗';
    private const string INFO_SYMBOL = 'ℹ';

    // Default separator character
    private const string SEPARATOR_CHAR = '-';

    // Colors for different elements
    private const string TITLE_COLOR = 'bright-blue';
    private const string SECTION_COLOR = 'blue';
    private const string SUCCESS_COLOR = 'green';
    private const string ERROR_COLOR = 'red';
    private const string WARNING_COLOR = 'yellow';
    private const string INFO_COLOR = 'cyan';
    private const string PROPERTY_COLOR = 'bright-cyan';
    private const string LABEL_COLOR = 'yellow';
    private const string COUNT_COLOR = 'bright-magenta';
    private const string DOTS_COLOR = 'gray';

    public function getSuccessSymbol(): string
    {
        return self::SUCCESS_SYMBOL;
    }

    public function getWarningSymbol(): string
    {
        return self::WARNING_SYMBOL;
    }

    public function getErrorSymbol(): string
    {
        return self::ERROR_SYMBOL;
    }

    public function getInfoSymbol(): string
    {
        return self::INFO_SYMBOL;
    }

    public function getSeparatorChar(): string
    {
        return self::SEPARATOR_CHAR;
    }

    public function getTitleColor(): string
    {
        return self::TITLE_COLOR;
    }

    public function getSectionColor(): string
    {
        return self::SECTION_COLOR;
    }

    public function getSuccessColor(): string
    {
        return self::SUCCESS_COLOR;
    }

    public function getErrorColor(): string
    {
        return self::ERROR_COLOR;
    }

    public function getWarningColor(): string
    {
        return self::WARNING_COLOR;
    }

    public function getInfoColor(): string
    {
        return self::INFO_COLOR;
    }

    public function getPropertyColor(): string
    {
        return self::PROPERTY_COLOR;
    }

    public function getLabelColor(): string
    {
        return self::LABEL_COLOR;
    }

    public function getCountColor(): string
    {
        return self::COUNT_COLOR;
    }

    public function getDotsColor(): string
    {
        return self::DOTS_COLOR;
    }

    public function colorize(string $text, string $color, bool $bold = false): string
    {
        return \sprintf(
            '<fg=%s%s>%s</>',
            $color,
            $bold ? ';options=bold' : '',
            $text,
        );
    }

    public function bgColorize(string $label, string $color, bool $bold = true): string
    {
        return \sprintf(
            '<bg=%s%s>%s</>',
            $color,
            $bold ? ';options=bold' : '',
            $label,
        );
    }
}
