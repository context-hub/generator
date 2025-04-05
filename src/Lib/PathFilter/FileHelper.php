<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\PathFilter;

final readonly class FileHelper
{
    public static function isRegex(string $str): bool
    {
        $availableModifiers = 'imsxuADUn';

        if (\preg_match('/^(.{3,}?)[' . $availableModifiers . ']*$/', $str, $m)) {
            $start = \substr($m[1], 0, 1);
            $end = \substr($m[1], -1);

            if ($start === $end) {
                return !\preg_match('/[*?[:alnum:] \\\\]/', $start);
            }

            foreach ([['{', '}'], ['(', ')'], ['[', ']'], ['<', '>']] as $delimiters) {
                if ($start === $delimiters[0] && $end === $delimiters[1]) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function toRegex(
        string $glob,
        bool $strictLeadingDot = true,
        bool $strictWildcardSlash = true,
        string $delimiter = '#',
    ): string {
        $firstByte = true;
        $escaping = false;
        $inCurlies = 0;
        $regex = '';
        $sizeGlob = \strlen($glob);
        /** @psalm-suppress InvalidOperand */
        for ($i = 0; $i < $sizeGlob; ++$i) {
            /** @psalm-suppress InvalidArrayOffset */
            $car = $glob[$i];
            if ($firstByte && $strictLeadingDot && $car !== '.') {
                $regex .= '(?=[^\.])';
            }

            $firstByte = $car === '/';

            /**
             * @psalm-suppress InvalidArrayOffset
             * @psalm-suppress InvalidOperand
             */
            if ($firstByte && $strictWildcardSlash && isset($glob[$i + 2]) && '**' === $glob[$i + 1] . $glob[$i + 2] && (!isset($glob[$i + 3]) || $glob[$i + 3] === '/')) {
                $car = '[^/]++/';
                /**
                 * @psalm-suppress InvalidArrayOffset
                 * @psalm-suppress InvalidOperand
                 */
                if (!isset($glob[$i + 3])) {
                    $car .= '?';
                }

                if ($strictLeadingDot) {
                    $car = '(?=[^\.])' . $car;
                }

                $car = '/(?:' . $car . ')*';
                /** @psalm-suppress InvalidOperand */
                $i += 2 + isset($glob[$i + 3]);

                if ($delimiter === '/') {
                    $car = \str_replace('/', '\\/', $car);
                }
            }

            if ($delimiter === $car || $car === '.' || $car === '(' || $car === ')' || $car === '|' || $car === '+' || $car === '^' || $car === '$') {
                $regex .= "\\$car";
            } elseif ($car === '*') {
                $regex .= $escaping ? '\\*' : ($strictWildcardSlash ? '[^/]*' : '.*');
            } elseif ($car === '?') {
                $regex .= $escaping ? '\\?' : ($strictWildcardSlash ? '[^/]' : '.');
            } elseif ($car === '{') {
                $regex .= $escaping ? '\\{' : '(';
                if (!$escaping) {
                    ++$inCurlies;
                }
            } elseif ($car === '}' && $inCurlies) {
                $regex .= $escaping ? '}' : ')';
                if (!$escaping) {
                    --$inCurlies;
                }
            } elseif ($car === ',' && $inCurlies) {
                $regex .= $escaping ? ',' : '|';
            } elseif ($car === '\\') {
                if ($escaping) {
                    $regex .= '\\\\';
                    $escaping = false;
                } else {
                    $escaping = true;
                }

                continue;
            } else {
                $regex .= $car;
            }
            $escaping = false;
        }

        return $delimiter . '^' . $regex . '$' . $delimiter;
    }
}
