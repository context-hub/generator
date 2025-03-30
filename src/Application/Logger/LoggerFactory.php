<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Logger;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class LoggerFactory
{
    public static function create(
        ?OutputInterface $output = null,
        ?FormatterInterface $formatter = null,
        bool $loggingEnabled = true,
    ): LoggerInterface {
        // If logging is disabled, return a NullLogger
        if (!$loggingEnabled) {
            return new NullLogger();
        }

        // If no output is provided, return a NullLogger
        if ($output === null) {
            return new NullLogger();
        }

        // Create the output logger with the formatter
        return new ConsoleLogger(
            output: $output,
            formatter: $formatter ?? new SimpleFormatter(),
        );
    }
}
