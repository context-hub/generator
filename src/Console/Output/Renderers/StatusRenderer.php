<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Output\Renderers;

use Butschster\ContextGenerator\Console\Output\Style\StyleInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Specialized renderer for status indicators
 */
final readonly class StatusRenderer
{
    /**
     * Maximum line width for consistent display
     */
    private const int MAX_LINE_WIDTH = 100;

    public function __construct(
        private OutputInterface $output,
        private StyleInterface $style,
    ) {}

    /**
     * Render a success status item
     */
    public function renderSuccess(string $description, string $message = '', int $width = self::MAX_LINE_WIDTH): void
    {
        $this->renderStatusItem(
            $description,
            $message,
            $this->style->getSuccessSymbol(),
            $this->style->getSuccessColor(),
            $width,
        );
    }

    /**
     * Render a warning status item
     */
    public function renderWarning(string $description, string $message = '', int $width = self::MAX_LINE_WIDTH): void
    {
        $this->renderStatusItem(
            $description,
            $message,
            $this->style->getWarningSymbol(),
            $this->style->getWarningColor(),
            $width,
        );
    }

    /**
     * Render an error status item
     */
    public function renderError(string $description, string $message = '', int $width = self::MAX_LINE_WIDTH): void
    {
        $this->renderStatusItem(
            $description,
            $message,
            $this->style->getErrorSymbol(),
            $this->style->getErrorColor(),
            $width,
        );
    }

    /**
     * Render an info status item
     */
    public function renderInfo(string $description, string $message = '', int $width = self::MAX_LINE_WIDTH): void
    {
        $this->renderStatusItem(
            $description,
            $message,
            $this->style->getInfoSymbol(),
            $this->style->getInfoColor(),
            $width,
        );
    }

    /**
     * Render a status item with custom styling
     */
    public function renderStatusItem(
        string $description,
        string $message,
        string $symbol,
        string $symbolColor,
        int $width = self::MAX_LINE_WIDTH,
    ): void {
        // If description is too long, trim it
        if (\strlen($description) > $width - 40) {
            $halfLength = (int) (($width - 40) / 2);
            $description = \substr($description, 0, $halfLength) . '...' . \substr($description, -$halfLength);
        }

        $padding = $this->calculatePadding($description, $message, 8);
        $coloredSymbol = $this->style->colorize($symbol, $symbolColor);
        $coloredDescription = $this->style->colorize($description, 'bright-blue');
        $dots = $this->style->colorize($padding, 'gray');

        $this->output->writeln(
            \sprintf(
                ' %s %s %s%s',
                $coloredSymbol,
                $coloredDescription,
                $dots,
                $message,
            ),
        );
    }

    /**
     * Render a list of status items
     *
     * @param array<string, array{status: string, message: string}> $items
     */
    public function renderStatusList(array $items, int $width = self::MAX_LINE_WIDTH): void
    {
        foreach ($items as $description => $item) {
            match ($item['status']) {
                'success' => $this->renderSuccess($description, $item['message'], $width),
                'warning' => $this->renderWarning($description, $item['message'], $width),
                'error' => $this->renderError($description, $item['message'], $width),
                default => $this->renderInfo($description, $item['message'], $width),
            };
        }
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
