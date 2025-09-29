<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Logger;

use Butschster\ContextGenerator\Application\FSPath;
use Monolog\Level;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class LoggerFactory
{
    public static function create(
        FSPath $logsPath,
        ?OutputInterface $output = null,
        bool $loggingEnabled = true,
    ): LoggerInterface {
        // If logging is disabled, return a NullLogger
        if (!$loggingEnabled) {
            return new NullLogger();
        }

        // If no output is provided, return a NullLogger
        if ($output === null) {
            $output = new NullOutput();
        }

        // Create the output logger with the formatter
        return new FileLogger(
            name: 'ctx',
            filePath: (string) $logsPath->join('ctx.log'),
            level: match (true) {
                $output->isVeryVerbose() => Level::Debug,
                $output->isVerbose() => Level::Info,
                $output->isQuiet() => Level::Error,
                default => Level::Debug,
            },
        );
    }
}
