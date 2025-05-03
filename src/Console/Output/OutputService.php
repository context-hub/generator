<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Output;

use Butschster\ContextGenerator\Console\Output\Style\StyleInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function PHPUnit\Framework\isTrue;

/**
 * Service for standardized console output generation
 * @method bool isSilent()
 */
final readonly class OutputService implements OutputServiceInterface
{
    /**
     * Maximum line width for consistent display
     */
    private const int MAX_LINE_WIDTH = 100;

    public function __construct(
        private OutputInterface $output,
        private StyleInterface $style,
    ) {}

    public function title(string $title): void
    {
        \assert($this->output instanceof SymfonyStyle);
        $this->output->title($title);
    }

    public function section(string $title): void
    {
        \assert($this->output instanceof SymfonyStyle);
        $this->output->section($title);
    }

    public function success(string $message): void
    {
        \assert($this->output instanceof SymfonyStyle);
        $this->block('OK', $message, $this->style->getSuccessColor());
    }

    public function error(string $message): void
    {
        \assert($this->output instanceof SymfonyStyle);
        $this->block('ERROR', $message, $this->style->getErrorColor());
    }

    public function warning(string $message): void
    {
        \assert($this->output instanceof SymfonyStyle);
        $this->block('WARNING', $message, $this->style->getWarningColor());
    }

    public function info(string $message): void
    {
        \assert($this->output instanceof SymfonyStyle);
        $this->block('INFO', $message, $this->style->getInfoColor());
    }

    public function note(string|array $messages): void
    {
        \assert($this->output instanceof SymfonyStyle);
        $this->block('NOTE', $messages, $this->style->getCountColor());
    }

    public function keyValue(string $key, string $value, int $width = 90): void
    {
        $dots = $this->calculatePadding($key, $value);

        $formattedKey = $this->style->colorize($key, $this->style->getLabelColor(), true);
        $formattedDots = $this->style->colorize($dots, $this->style->getDotsColor());

        $this->output->writeln(
            \sprintf(
                '%s:%s%s',
                $formattedKey,
                $formattedDots,
                $value,
            ),
        );
    }

    public function table(array $headers, array $rows): void
    {
        \assert($this->output instanceof SymfonyStyle);
        $table = new Table($this->output);
        $table->setHeaders($headers);

        foreach ($rows as $row) {
            $table->addRow($row);
        }

        $table->render();
    }

    public function statusList(array $items): void
    {
        foreach ($items as $description => $item) {
            $symbol = match ($item['status']) {
                'success' => $this->style->colorize($this->style->getSuccessSymbol(), $this->style->getSuccessColor()),
                'warning' => $this->style->colorize($this->style->getWarningSymbol(), $this->style->getWarningColor()),
                'error' => $this->style->colorize($this->style->getErrorSymbol(), $this->style->getErrorColor()),
                default => $this->style->colorize($this->style->getInfoSymbol(), $this->style->getInfoColor()),
            };

            $padding = $this->calculatePadding($description, $item['message'], 8);

            $this->output->writeln(
                \sprintf(
                    ' %s %s %s%s',
                    $symbol,
                    $this->style->colorize($description, 'bright-blue'),
                    $this->style->colorize($padding, 'gray'),
                    $item['message'],
                ),
            );
        }
    }

    public function separator(int $length = 80): void
    {
        $separator = \str_repeat($this->style->getSeparatorChar(), $length);
        $this->output->writeln($this->style->colorize($separator, $this->style->getSectionColor()));
    }

    public function highlight(string $text, string $color = 'bright-green', bool $bold = false): string
    {
        return $this->style->colorize($text, $color, $bold);
    }

    public function formatProperty(string $text): string
    {
        return $this->style->colorize($text, $this->style->getPropertyColor());
    }

    public function formatLabel(string $text): string
    {
        return $this->style->colorize($text, $this->style->getLabelColor());
    }

    public function formatCount(int $count): string
    {
        return $this->style->colorize((string) $count, $this->style->getCountColor());
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function block(string $label, string|array $message, string $color): void
    {
        $label = ' ' . $label . ' ';

        if (\is_string($message)) {
            $this->output->writeln(' ' . $this->style->bgColorize($label, $color, true) . ' ' . $message);
        } else {
            $this->output->writeln(' ' . $this->style->bgColorize($label, $color, true));
            foreach ($message as $line) {
                $this->output->writeln($this->style->colorize('  - ', $color) . $line);
            }
        }
        $this->output->newLine();
    }

    /**
     * Calculate padding to align the description and message
     */
    private function calculatePadding(string $description, string $message, int $additional = 5): string
    {
        $totalLength = \strlen($description) + \strlen($message) + $additional;
        $padding = \max(0, self::MAX_LINE_WIDTH - $totalLength);

        return \str_repeat('.', $padding);
    }
}
