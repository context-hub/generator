<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Client;

use Butschster\ContextGenerator\McpServer\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\McpConfig\Model\OsInfo;
use Butschster\ContextGenerator\McpServer\McpConfig\Renderer\McpConfigRenderer;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractClientStrategy implements ClientStrategyInterface
{
    public function renderConfiguration(
        McpConfigRenderer $renderer,
        McpConfig $config,
        OsInfo $osInfo,
        array $options,
        SymfonyStyle $output,
    ): void {
        // Default rendering delegates to shared renderer
        $renderer->renderConfiguration($config, $osInfo, $options);
    }

    public function renderExplanation(
        McpConfigRenderer $renderer,
        McpConfig $config,
        OsInfo $osInfo,
        array $options,
        SymfonyStyle $output,
    ): void {
        // Default explanation delegates to shared renderer
        $renderer->renderExplanation($config, $osInfo, $options);

        $this->renderAdditionalNotes($output, $osInfo, $options);
    }

    protected function renderAdditionalNotes(SymfonyStyle $output, OsInfo $osInfo, array $options): void {}
}

