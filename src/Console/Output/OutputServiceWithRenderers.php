<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Output;

use Butschster\ContextGenerator\Console\Output\Renderers\ListRenderer;
use Butschster\ContextGenerator\Console\Output\Renderers\StatusRenderer;
use Butschster\ContextGenerator\Console\Output\Renderers\SummaryRenderer;
use Butschster\ContextGenerator\Console\Output\Renderers\TableRenderer;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extended OutputService with access to specialized renderers
 * @method bool isSilent()
 */
final readonly class OutputServiceWithRenderers implements OutputServiceInterface
{
    public function __construct(
        private OutputServiceInterface $outputService,
        private TableRenderer $tableRenderer,
        private StatusRenderer $statusRenderer,
        private ListRenderer $listRenderer,
        private SummaryRenderer $summaryRenderer,
    ) {}

    public function title(string $title): void
    {
        $this->outputService->title($title);
    }

    public function section(string $title): void
    {
        $this->outputService->section($title);
    }

    public function success(string $message): void
    {
        $this->outputService->success($message);
    }

    public function error(string $message): void
    {
        $this->outputService->error($message);
    }

    public function warning(string $message): void
    {
        $this->outputService->warning($message);
    }

    public function info(string $message): void
    {
        $this->outputService->info($message);
    }

    public function note(string|array $messages): void
    {
        $this->outputService->note($messages);
    }

    public function keyValue(string $key, string $value, int $width = 90): void
    {
        $this->outputService->keyValue($key, $value, $width);
    }

    public function table(array $headers, array $rows): void
    {
        $this->outputService->table($headers, $rows);
    }

    public function statusList(array $items): void
    {
        $this->outputService->statusList($items);
    }

    public function separator(int $length = 80): void
    {
        $this->outputService->separator($length);
    }

    public function highlight(string $text, string $color = 'bright-green', bool $bold = false): string
    {
        return $this->outputService->highlight($text, $color, $bold);
    }

    public function formatProperty(string $text): string
    {
        return $this->outputService->formatProperty($text);
    }

    public function formatLabel(string $text): string
    {
        return $this->outputService->formatLabel($text);
    }

    public function formatCount(int $count): string
    {
        return $this->outputService->formatCount($count);
    }

    public function getOutput(): OutputInterface
    {
        return $this->outputService->getOutput();
    }

    /**
     * Get the table renderer
     */
    public function getTableRenderer(): TableRenderer
    {
        return $this->tableRenderer;
    }

    /**
     * Get the status renderer
     */
    public function getStatusRenderer(): StatusRenderer
    {
        return $this->statusRenderer;
    }

    /**
     * Get the list renderer
     */
    public function getListRenderer(): ListRenderer
    {
        return $this->listRenderer;
    }

    /**
     * Get the summary renderer
     */
    public function getSummaryRenderer(): SummaryRenderer
    {
        return $this->summaryRenderer;
    }
}
