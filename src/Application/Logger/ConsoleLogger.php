<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel as PsrLogLevel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * PSR-3 compatible console logger that outputs to a Symfony Console OutputInterface.
 * Respects the verbosity levels of the Console component.
 */
final class ConsoleLogger extends AbstractLogger implements HasPrefixLoggerInterface
{
    public function __construct(
        private readonly OutputInterface $output,
        private readonly FormatterInterface $formatter = new SimpleFormatter(),
    ) {}

    public function withPrefix(string $prefix): static
    {
        return new self($this->output, $this->formatter->withPrefix($prefix));
    }

    public function getPrefix(): string
    {
        return $this->formatter->getPrefix();
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $logLevel = $this->normalizeLogLevel(level: $level);

        // Only output if verbosity of the output is sufficient for this log level
        if (!$this->isLevelEnabled(level: $logLevel)) {
            return;
        }

        $formattedMessage = $this->formatter->format($logLevel, $message, $context);

        // Apply color formatting based on log level
        $colorizedMessage = $this->colorize(message: $formattedMessage, level: $logLevel);

        $this->output->writeln($colorizedMessage);
    }

    /**
     * Check if the log level is enabled for the current output verbosity.
     */
    private function isLevelEnabled(string $level): bool
    {
        $verbosity = $this->output->getVerbosity();

        return match ($level) {
            PsrLogLevel::EMERGENCY,
            PsrLogLevel::ALERT,
            PsrLogLevel::CRITICAL,
            PsrLogLevel::ERROR => true, // Always show errors

            PsrLogLevel::WARNING,
            PsrLogLevel::NOTICE => $verbosity >= OutputInterface::VERBOSITY_NORMAL,

            PsrLogLevel::INFO => $verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE,

            PsrLogLevel::DEBUG => $verbosity >= OutputInterface::VERBOSITY_DEBUG,

            default => false,
        };
    }

    /**
     * Apply color formatting based on log level.
     */
    private function colorize(string $message, string $level): string
    {
        $color = LogLevel::fromString(level: $level)->getAnsiColor();

        if (empty($color)) {
            return $message;
        }

        return "{$color}{$message}\033[0m";
    }

    /**
     * Convert any valid log level to a normalized string.
     */
    private function normalizeLogLevel(mixed $level): string
    {
        if (\is_string(value: $level)) {
            return \strtolower(string: $level);
        }

        if ($level instanceof \Stringable) {
            return \strtolower(string: (string) $level);
        }

        // Default to 'info' for invalid levels
        return PsrLogLevel::INFO;
    }
}
