<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Output\Renderers;

use Butschster\ContextGenerator\Console\Output\Style\StyleInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Specialized renderer for summary outputs
 */
final readonly class SummaryRenderer
{
    public function __construct(
        private OutputInterface $output,
        private StyleInterface $style,
    ) {}

    /**
     * Render a summary section with statistics
     *
     * @param string $title Summary title
     * @param array<string, int|string> $stats Key-value statistics
     */
    public function renderStatsSummary(string $title, array $stats): void
    {
        $this->output->writeln('');
        $this->output->writeln($this->style->colorize($title, $this->style->getSectionColor(), true));
        $this->output->writeln(
            $this->style->colorize(\str_repeat('-', \strlen($title)), $this->style->getSectionColor()),
        );
        $this->output->writeln('');

        $maxKeyLength = 0;
        foreach (\array_keys($stats) as $key) {
            $maxKeyLength = \max($maxKeyLength, \strlen($key));
        }

        foreach ($stats as $key => $value) {
            $padding = \str_repeat(' ', \max(0, $maxKeyLength - \strlen($key)));
            $formattedKey = $this->style->colorize($key, $this->style->getPropertyColor());

            // Format numeric values with the count style
            $formattedValue = \is_int($value)
                ? $this->style->colorize((string) $value, $this->style->getCountColor())
                : $value;

            $this->output->writeln(\sprintf('  %s:%s %s', $formattedKey, $padding, $formattedValue));
        }

        $this->output->writeln('');
    }

    /**
     * Render a completion summary with counts
     *
     * @param array<string, int> $counts Map of categories to counts
     * @param int $total Total count (if not provided, will be calculated from counts)
     */
    public function renderCompletionSummary(array $counts, ?int $total = null): void
    {
        if ($total === null) {
            $total = \array_sum($counts);
        }

        $this->output->writeln('');

        foreach ($counts as $label => $count) {
            $percentage = $total > 0 ? \round(($count / $total) * 100) : 0;
            $formattedLabel = $this->style->colorize($label, $this->style->getLabelColor());
            $formattedCount = $this->style->colorize((string) $count, $this->style->getCountColor());
            $formattedPercentage = $this->style->colorize($percentage . '%', $this->style->getInfoColor());

            $this->output->writeln(\sprintf('  %s: %s (%s)', $formattedLabel, $formattedCount, $formattedPercentage));
        }

        $formattedTotal = $this->style->colorize((string) $total, $this->style->getCountColor(), true);
        $this->output->writeln(
            \sprintf('  %s: %s', $this->style->colorize('Total', $this->style->getLabelColor(), true), $formattedTotal),
        );
        $this->output->writeln('');
    }

    /**
     * Render a progress summary (e.g., tasks completed/total)
     */
    public function renderProgressSummary(int $completed, int $total, string $label = 'Completed'): void
    {
        $percentage = $total > 0 ? \round(($completed / $total) * 100) : 0;

        $formattedLabel = $this->style->colorize($label, $this->style->getLabelColor());
        $formattedCompleted = $this->style->colorize((string) $completed, $this->style->getCountColor());
        $formattedTotal = $this->style->colorize((string) $total, $this->style->getCountColor());
        $formattedPercentage = $this->style->colorize($percentage . '%', $this->style->getInfoColor());

        $this->output->writeln(
            \sprintf(
                '%s: %s/%s (%s)',
                $formattedLabel,
                $formattedCompleted,
                $formattedTotal,
                $formattedPercentage,
            ),
        );
    }

    /**
     * Render a time summary (e.g., execution time)
     */
    public function renderTimeSummary(float $timeInSeconds, string $label = 'Execution time'): void
    {
        $formattedLabel = $this->style->colorize($label, $this->style->getLabelColor());
        $formattedTime = $this->style->colorize(
            \sprintf('%.2f', $timeInSeconds) . ' sec',
            $this->style->getCountColor(),
        );

        $this->output->writeln(\sprintf('%s: %s', $formattedLabel, $formattedTime));
    }
}
