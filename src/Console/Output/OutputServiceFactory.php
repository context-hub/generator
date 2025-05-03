<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Output;

use Butschster\ContextGenerator\Console\Output\Renderers\ListRenderer;
use Butschster\ContextGenerator\Console\Output\Renderers\StatusRenderer;
use Butschster\ContextGenerator\Console\Output\Renderers\SummaryRenderer;
use Butschster\ContextGenerator\Console\Output\Renderers\TableRenderer;
use Butschster\ContextGenerator\Console\Output\Style\DefaultStyle;
use Butschster\ContextGenerator\Console\Output\Style\StyleInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Factory for creating OutputService instances
 */
final readonly class OutputServiceFactory
{
    /**
     * Create an OutputService with the specified Style
     */
    public static function create(
        OutputInterface $output,
        ?StyleInterface $style = null,
    ): OutputServiceInterface {
        $style ??= new DefaultStyle();

        return new OutputService($output, $style);
    }

    /**
     * Create an OutputService with all renderers
     */
    public static function createWithRenderers(
        OutputInterface $output,
        ?StyleInterface $style = null,
    ): OutputServiceInterface {
        $style ??= new DefaultStyle();

        $outputService = new OutputService($output, $style);

        $tableRenderer = new TableRenderer($output, $style);
        $statusRenderer = new StatusRenderer($output, $style);
        $listRenderer = new ListRenderer($output, $style);
        $summaryRenderer = new SummaryRenderer($output, $style);

        return new OutputServiceWithRenderers(
            outputService: $outputService,
            tableRenderer: $tableRenderer,
            statusRenderer: $statusRenderer,
            listRenderer: $listRenderer,
            summaryRenderer: $summaryRenderer,
        );
    }
}
