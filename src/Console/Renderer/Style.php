<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Renderer;

/**
 * @deprecated Should be completely redesigned
 */
final class Style
{
    /**
     * Format a section header (large titles)
     */
    public static function header(string $text): string
    {
        return "\033[1;33m" . $text . "\033[0m";
    }

    /**
     * Format a title (section names)
     */
    public static function title(string $text): string
    {
        return "\033[1;36m" . $text . "\033[0m";
    }

    /**
     * Format a subtitle (smaller section names)
     */
    public static function subtitle(string $text): string
    {
        return "\033[1;34m" . $text . "\033[0m";
    }

    /**
     * Format a property name
     */
    public static function property(string $text): string
    {
        return "\033[1;32m" . $text . "\033[0m";
    }

    /**
     * Format a value
     */
    public static function value(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? "\033[0;32mtrue\033[0m" : "\033[0;31mfalse\033[0m";
        }

        if (\is_string($value)) {
            return "\033[0;33m\"" . $value . "\"\033[0m";
        }

        if (\is_null($value)) {
            return "\033[0;90mnull\033[0m";
        }

        if (\is_numeric($value)) {
            return "\033[0;36m" . $value . "\033[0m";
        }

        return (string) $value;
    }

    /**
     * Format array values
     */
    public static function array(array $values): string
    {
        if (empty($values)) {
            return "\033[0;90m[]\033[0m";
        }

        if (\array_keys($values) === \range(0, \count($values) - 1)) {
            // Sequential array
            $formattedItems = \array_map(
                static fn($item) => \is_array($item) ? self::array($item) : self::value($item),
                $values,
            );
            return "\033[0;33m[" . \implode(", ", $formattedItems) . "]\033[0m";
        }

        // Associative array
        $result = "{\n";
        foreach ($values as $key => $value) {
            $formattedValue = \is_array($value) ? self::array($value) : self::value($value);
            $result .= self::indent(self::property('"' . $key . '"') . ": " . $formattedValue) . "\n";
        }
        $result .= '}';
        return $result;
    }

    /**
     * Format a key-value pair
     */
    public static function keyValue(string $key, mixed $value): string
    {
        $formattedValue = \is_array($value) ? self::array($value) : self::value($value);
        return self::property($key) . ": " . $formattedValue;
    }

    /**
     * Format a count indicator
     */
    public static function count(int $count): string
    {
        return "\033[1;35m" . $count . "\033[0m";
    }

    /**
     * Format an item number in a list
     */
    public static function itemNumber(int $number, int $total = 10): string
    {
        return "\033[1;35m" . $number . '/' . $total . "\033[0m";
    }

    /**
     * Format path text
     */
    public static function path(string $path): string
    {
        return "\033[0;36m" . $path . "\033[0m";
    }

    /**
     * Format a preview of content
     */
    public static function contentPreview(string $content): string
    {
        $preview = \substr($content, 0, 100);
        if (\strlen($content) > 100) {
            $preview .= '...';
        }
        return "\033[0;90m\"" . \str_replace(["\r", "\n"], ["\\r", "\\n"], $preview) . "\"\033[0m";
    }

    /**
     * Indent a text block
     */
    public static function indent(string $text, int $level = 1): string
    {
        $indent = \str_repeat('  ', $level);
        return $indent . \str_replace("\n", "\n" . $indent, $text);
    }

    /**
     * Create a separator line
     */
    public static function separator(string $char = '-', int $length = 30): string
    {
        return "\033[0;90m" . \str_repeat($char, $length) . "\033[0m";
    }
}
