<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Renderer;

/**
 * Console output styling utilities
 */
final class Style
{
    /**
     * Create a header styled text
     */
    public static function header(string $text): string
    {
        return \sprintf('<fg=bright-blue;options=bold>%s</>', $text);
    }

    /**
     * Create a section header styled text
     */
    public static function section(string $text): string
    {
        return \sprintf('<fg=bright-blue;options=bold>%s</>', $text);
    }

    /**
     * Create a separator line
     */
    public static function separator(string $char = '-', int $length = 80): string
    {
        return \sprintf('<fg=blue>%s</>', \str_repeat($char, $length));
    }

    /**
     * Create a property name styled text
     */
    public static function property(string $text): string
    {
        return \sprintf('<fg=bright-cyan>%s</>', $text);
    }

    /**
     * Create a label styled text
     */
    public static function label(string $text): string
    {
        return \sprintf('<fg=yellow>%s</>', $text);
    }

    /**
     * Create a value styled text
     */
    public static function value(string $text): string
    {
        return \sprintf('<fg=bright-white>%s</>', $text);
    }

    /**
     * Create a count styled text
     */
    public static function count(int $count): string
    {
        return \sprintf('<fg=bright-magenta>%d</>', $count);
    }

    /**
     * Create an item number styled text
     */
    public static function itemNumber(int $current, int $total): string
    {
        return \sprintf('<fg=bright-yellow>%d/%d</>', $current, $total);
    }

    /**
     * Create indented text
     */
    public static function indent(string $text, int $spaces = 2): string
    {
        $indent = \str_repeat(' ', $spaces);
        return $indent . \str_replace("\n", "\n" . $indent, $text);
    }

    /**
     * Create success styled text
     */
    public static function success(string $text): string
    {
        return \sprintf('<fg=green>%s</>', $text);
    }

    /**
     * Create error styled text
     */
    public static function error(string $text): string
    {
        return \sprintf('<fg=red>%s</>', $text);
    }

    /**
     * Create warning styled text
     */
    public static function warning(string $text): string
    {
        return \sprintf('<fg=yellow>%s</>', $text);
    }

    /**
     * Create info styled text
     */
    public static function info(string $text): string
    {
        return \sprintf('<fg=cyan>%s</>', $text);
    }

    /**
     * Create highlighted text
     */
    public static function highlight(string $text): string
    {
        return \sprintf('<fg=bright-green>%s</>', $text);
    }

    /**
     * Create muted/dimmed text
     */
    public static function muted(string $text): string
    {
        return \sprintf('<fg=gray>%s</>', $text);
    }

    /**
     * Create command styled text (for showing command names)
     */
    public static function command(string $text): string
    {
        return \sprintf('<fg=bright-cyan;options=bold>%s</>', $text);
    }

    /**
     * Create a comment styled text
     */
    public static function comment(string $text): string
    {
        return \sprintf('<fg=gray>%s</>', $text);
    }

    /**
     * Create a question styled text
     */
    public static function question(string $text): string
    {
        return \sprintf('<fg=bright-yellow>%s</>', $text);
    }

    /**
     * Create a note styled text
     */
    public static function note(string $text): string
    {
        return \sprintf('<fg=blue>%s</>', $text);
    }

    /**
     * Create a caution styled text
     */
    public static function caution(string $text): string
    {
        return \sprintf('<fg=bright-red>%s</>', $text);
    }

    /**
     * Create bold text
     */
    public static function bold(string $text): string
    {
        return \sprintf('<options=bold>%s</>', $text);
    }

    /**
     * Create italic text
     */
    public static function italic(string $text): string
    {
        return \sprintf('<options=italic>%s</>', $text);
    }

    /**
     * Create underlined text
     */
    public static function underline(string $text): string
    {
        return \sprintf('<options=underscore>%s</>', $text);
    }

    /**
     * Create a table-style key-value pair
     */
    public static function keyValue(string $key, string $value, int $keyWidth = 15): string
    {
        return \sprintf(
            '%s: %s',
            self::property(\str_pad($key, $keyWidth)),
            self::value($value),
        );
    }

    /**
     * Create a bulleted list item
     */
    public static function bullet(string $text, string $bullet = '•'): string
    {
        return \sprintf('%s %s', self::muted($bullet), $text);
    }

    /**
     * Create a numbered list item
     */
    public static function numbered(string $text, int $number): string
    {
        return \sprintf('%s. %s', self::count($number), $text);
    }

    /**
     * Create a path styled text
     */
    public static function path(string $path): string
    {
        return \sprintf('<fg=bright-blue>%s</>', $path);
    }

    /**
     * Create a file styled text
     */
    public static function file(string $filename): string
    {
        return \sprintf('<fg=bright-yellow>%s</>', $filename);
    }

    /**
     * Create a URL styled text
     */
    public static function url(string $url): string
    {
        return \sprintf('<fg=blue;options=underscore>%s</>', $url);
    }
}
