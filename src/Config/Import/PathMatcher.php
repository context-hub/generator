<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import;

/**
 * Utility class to convert glob patterns to regex for file path matching
 */
final readonly class PathMatcher
{
    private string $regex;

    /**
     * Create a new path matcher for the given glob pattern
     */
    public function __construct(private string $pattern)
    {
        $this->regex = $this->isRegex($this->pattern) ? $this->pattern : $this->globToRegex($this->pattern);
    }

    /**
     * Check if a path contains wildcard characters
     */
    public static function containsWildcard(string $path): bool
    {
        return \str_contains($path, '*') || \str_contains($path, '?') ||
            \str_contains($path, '[') || \str_contains($path, '{');
    }

    /**
     * Check if the given path matches the pattern
     */
    public function isMatch(string $path): bool
    {
        return (bool) \preg_match($this->regex, $path);
    }

    /**
     * Get the original glob pattern
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Get the regex pattern
     */
    public function getRegex(): string
    {
        return $this->regex;
    }

    /**
     * Convert a glob pattern to a regex pattern
     *
     * This supports:
     * - * (match any characters except /)
     * - ** (match any characters including /)
     * - ? (match any single character)
     * - [abc] (match a single character from the set)
     * - [!abc] or [^abc] (match a single character not in the set)
     * - {a,b,c} (match any of the comma-separated patterns)
     */
    private function globToRegex(string $pattern): string
    {
        $escaping = false;
        $inSquareBrackets = false;
        $inCurlyBraces = false;

        // Start at the beginning of the string
        $regex = '~^';

        $length = \strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            $nextChar = $i + 1 < $length ? $pattern[$i + 1] : null;

            // Handle escape character
            if ($char === '\\') {
                if ($escaping) {
                    $regex .= '\\\\';
                    $escaping = false;
                } else {
                    $escaping = true;
                }
                continue;
            }

            // If we are escaping, add the escaped character
            if ($escaping) {
                $regex .= \preg_quote($char, '~');
                $escaping = false;
                continue;
            }

            // Special handling inside character classes
            if ($inSquareBrackets) {
                if ($char === ']') {
                    $inSquareBrackets = false;
                    $regex .= ']';
                } else {
                    $regex .= $char;
                }
                continue;
            }

            // Special handling inside curly braces
            if ($inCurlyBraces) {
                if ($char === '}') {
                    $inCurlyBraces = false;
                    $regex .= ')';
                } elseif ($char === ',') {
                    $regex .= '|';
                } else {
                    $regex .= \preg_quote($char, '~');
                }
                continue;
            }

            // Handle normal characters
            switch ($char) {
                case '*':
                    if ($nextChar === '*') {
                        // ** matches anything including directory separators
                        $regex .= '.*';
                        $i++; // Skip the next *
                    } else {
                        // * matches anything except directory separators
                        $regex .= '[^/]*';
                    }
                    break;
                case '?':
                    // ? matches a single character
                    $regex .= '[^/]';
                    break;
                case '[':
                    // Start of a character class
                    $inSquareBrackets = true;
                    $regex .= '[';
                    break;
                case '{':
                    // Start of an alternation group
                    $inCurlyBraces = true;
                    $regex .= '(';
                    break;
                case '.':
                case '(':
                case ')':
                case '+':
                case '|':
                case '^':
                case '$':
                case '@':
                case '%':
                    // Escape special regex characters
                    $regex .= '\\' . $char;
                    break;
                default:
                    // Regular character
                    $regex .= $char;
            }
        }

        // End of the string
        $regex .= '$~';

        return $regex;
    }

    /**
     * Checks whether the string is a regex.
     */
    private function isRegex(string $str): bool
    {
        $availableModifiers = 'imsxuADUn';

        if (preg_match('/^(.{3,}?)[' . $availableModifiers . ']*$/', $str, $m)) {
            $start = substr($m[1], 0, 1);
            $end = substr($m[1], -1);

            if ($start === $end) {
                return !preg_match('/[*?[:alnum:] \\\\]/', $start);
            }

            foreach ([['{', '}'], ['(', ')'], ['[', ']'], ['<', '>']] as $delimiters) {
                if ($start === $delimiters[0] && $end === $delimiters[1]) {
                    return true;
                }
            }
        }

        return false;
    }
}
