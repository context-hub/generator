<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console\McpConfig\Generator;

use Butschster\ContextGenerator\McpServer\Console\McpConfig\ConfigGeneratorInterface;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\OsInfo;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Template\ConfigTemplateInterface;

final readonly class McpConfigGenerator implements ConfigGeneratorInterface
{
    public function __construct(
        private ConfigTemplateInterface $windowsTemplate,
        private ConfigTemplateInterface $linuxTemplate,
        private ConfigTemplateInterface $wslTemplate,
        private ConfigTemplateInterface $macosTemplate,
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
