<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console\McpConfig;

use Butschster\ContextGenerator\McpServer\Console\McpConfig\Generator\McpConfigGenerator;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Service\OsDetectionService;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Template\ConfigTemplateInterface;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Template\LinuxConfigTemplate;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Template\MacOsConfigTemplate;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Template\WindowsConfigTemplate;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Template\WslConfigTemplate;
use Spiral\Boot\Bootloader\Bootloader;

final class McpConfigBootloader extends Bootloader
{
    public function defineSingletons(): array
    {
        return [
            OsDetectionService::class => OsDetectionService::class,
            ConfigGeneratorInterface::class => static function (
                LinuxConfigTemplate $linuxTemplate,
                WindowsConfigTemplate $windowsTemplate,
                WslConfigTemplate $wslTemplate,
                MacOsConfigTemplate $macosTemplate,
            ): McpConfigGenerator {
                return new McpConfigGenerator(
                    windowsTemplate: $windowsTemplate,
                    linuxTemplate: $linuxTemplate,
                    wslTemplate: $wslTemplate,
                    macosTemplate: $macosTemplate,
                );
            },
            LinuxConfigTemplate::class => LinuxConfigTemplate::class,
            WindowsConfigTemplate::class => WindowsConfigTemplate::class,
            WslConfigTemplate::class => WslConfigTemplate::class,
            MacOsConfigTemplate::class => MacOsConfigTemplate::class,
        ];
    }

    public function defineBindings(): array
    {
        return [
            ConfigTemplateInterface::class => LinuxConfigTemplate::class,
        ];
    }
}
