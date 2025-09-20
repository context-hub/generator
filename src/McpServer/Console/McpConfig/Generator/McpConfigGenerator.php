<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console\McpConfig\Generator;

use Butschster\ContextGenerator\McpServer\Console\McpConfig\ConfigGeneratorInterface;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\OsInfo;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Template\ConfigTemplateInterface;

final class McpConfigGenerator implements ConfigGeneratorInterface
{
    public function __construct(
        private readonly ConfigTemplateInterface $windowsTemplate,
        private readonly ConfigTemplateInterface $linuxTemplate,
        private readonly ConfigTemplateInterface $wslTemplate,
        private readonly ConfigTemplateInterface $macosTemplate,
    ) {}

    public function generate(
        string $client,
        OsInfo $osInfo,
        string $projectPath,
        array $options = [],
    ): McpConfig {
        $template = $this->selectTemplate($osInfo);

        return $template->generate(
            client: $client,
            osInfo: $osInfo,
            projectPath: $projectPath,
            options: $options,
        );
    }

    public function getSupportedClients(): array
    {
        return ['claude', 'generic'];
    }

    private function selectTemplate(OsInfo $osInfo): ConfigTemplateInterface
    {
        return match (true) {
            $osInfo->isWsl => $this->wslTemplate,
            $osInfo->isWindows => $this->windowsTemplate,
            $osInfo->isLinux => $this->linuxTemplate,
            $osInfo->isMacOs => $this->macosTemplate,
            default => $this->linuxTemplate, // fallback to Linux template
        };
    }
}
