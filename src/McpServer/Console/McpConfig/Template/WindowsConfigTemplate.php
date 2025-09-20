<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console\McpConfig\Template;

use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\OsInfo;

final class WindowsConfigTemplate extends BaseConfigTemplate
{
    public function generate(
        string $client,
        OsInfo $osInfo,
        string $projectPath,
        array $options = [],
    ): McpConfig {
        return match ($client) {
            'claude' => $this->generateClaudeConfig($osInfo, $projectPath, $options),
            'generic' => $this->generateGenericConfig($osInfo, $projectPath, $options),
            default => throw new \InvalidArgumentException("Unsupported client: {$client}"),
        };
    }

    protected function getCommand(OsInfo $osInfo): string
    {
        // On Windows, we prefer the full path to ctx.exe or just ctx.exe if it's in PATH
        return 'ctx.exe';
    }

    protected function getArgs(OsInfo $osInfo, string $projectPath, array $options = []): array
    {
        $args = ['server'];

        // Only add -c option if project path is explicitly requested
        if (isset($options['use_project_path']) && $options['use_project_path']) {
            // Convert Unix-style paths to Windows paths if needed
            $windowsPath = $this->convertToWindowsPath($projectPath);
            $args[] = "-c{$windowsPath}";
        }

        return $args;
    }

    private function convertToWindowsPath(string $path): string
    {
        // Convert forward slashes to backslashes for Windows
        $windowsPath = \str_replace('/', '\\', $path);

        // Ensure we have a proper Windows path format
        if (!\preg_match('/^[A-Za-z]:/', $windowsPath)) {
            // If it doesn't start with a drive letter, assume it's a relative path
            // and keep it as-is since it will be resolved relative to current directory
            return $windowsPath;
        }

        return $windowsPath;
    }
}
