<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Client;

use Butschster\ContextGenerator\McpServer\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\McpConfig\Model\OsInfo;
use Butschster\ContextGenerator\McpServer\McpConfig\Renderer\McpConfigRenderer;
use Symfony\Component\Console\Style\SymfonyStyle;

interface ClientStrategyInterface
{
    public function getKey(): string;

    public function getLabel(): string;

    /**
     * Returns the client key supported by the underlying generator (e.g. "claude" or "generic").
     */
    public function getGeneratorClientKey(): string;

    public function renderConfiguration(
        McpConfigRenderer $renderer,
        McpConfig $config,
        OsInfo $osInfo,
        array $options,
        SymfonyStyle $output,
    ): void;

    public function renderExplanation(
        McpConfigRenderer $renderer,
        McpConfig $config,
        OsInfo $osInfo,
        array $options,
        SymfonyStyle $output,
    ): void;
}

