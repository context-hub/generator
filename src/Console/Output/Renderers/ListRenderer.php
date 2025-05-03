<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Output\Renderers;

use Butschster\ContextGenerator\Console\Output\Style\StyleInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Specialized renderer for list outputs
 */
final readonly class ListRenderer
{
    public function __construct(
        private OutputInterface $output,
        private StyleInterface $style,
    ) {}

    /**
     * Render a simple bulleted list
     *
     * @param array<int, string> $items List items
     * @param string $bullet Bullet character
     */
    public function renderBulletList(
        array $items,
        string $bullet = '•',
        int $indentation = 2,
    ): void {
        $indent = \str_repeat(' ', $indentation);
        $coloredBullet = $this->style->colorize($bullet, $this->style->getInfoColor());

        foreach ($items as $item) {
            $this->output->writeln(\sprintf('%s%s %s', $indent, $coloredBullet, $item));
        }
    }

    /**
     * Render a numbered list
     *
     * @param array<int, string> $items List items
     */
    public function renderNumberedList(array $items, int $indentation = 2): void
    {
        $indent = \str_repeat(' ', $indentation);

        foreach ($items as $index => $item) {
            $number = $index + 1;
            $formattedNumber = $this->style->colorize((string) $number . '.', $this->style->getCountColor());
            $this->output->writeln(\sprintf('%s%s %s', $indent, $formattedNumber, $item));
        }
    }

    /**
     * Render a definition list (term: definition)
     *
     * @param array<string, string> $items Key-value pairs where key is the term and value is the definition
     */
    public function renderDefinitionList(
        array $items,
        int $indentation = 2,
        bool $alignDefinitions = true,
        int $width = 90,
    ): void {
        $indent = \str_repeat(' ', $indentation);

        // Find the longest term if we need to align
        $maxTermLength = 0;
        if ($alignDefinitions) {
            foreach (\array_keys($items) as $term) {
                $maxTermLength = \max($maxTermLength, \strlen($term));
            }
        }

        foreach ($items as $term => $definition) {
            $formattedTerm = $this->style->colorize($term, $this->style->getPropertyColor());

            if ($alignDefinitions) {
                $padding = \str_repeat(' ', \max(0, $maxTermLength - \strlen($term)));
                $this->output->writeln(\sprintf('%s%s%s: %s', $indent, $formattedTerm, $padding, $definition));
            } else {
                // Use dots to connect term and definition
                $dots = \str_repeat('.', \max(0, $width - \strlen($term) - \strlen($definition) - 2));
                $formattedDots = $this->style->colorize($dots, $this->style->getDotsColor());
                $this->output->writeln(\sprintf('%s%s:%s%s', $indent, $formattedTerm, $formattedDots, $definition));
            }
        }
    }

    /**
     * Render a list of key-value pairs with optional grouping
     *
     * @param array<string, array<string, mixed>> $groups Groups of key-value pairs
     */
    public function renderGroupedProperties(array $groups, int $indentation = 2): void
    {
        foreach ($groups as $groupName => $properties) {
            // Output group name as a section header
            $this->output->writeln($this->style->colorize($groupName, $this->style->getSectionColor(), true));
            $this->output->writeln('');

            // Output properties as a definition list
            $this->renderDefinitionList($properties, $indentation);
            $this->output->writeln('');
        }
    }
}
