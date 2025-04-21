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
     * Create highlighted text
     */
    public static function highlight(string $text): string
    {
        return \sprintf('<fg=bright-green>%s</>', $text);
    }
}
