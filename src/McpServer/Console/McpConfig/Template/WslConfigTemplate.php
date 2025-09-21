<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console\McpConfig\Template;

use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\OsInfo;

final class WslConfigTemplate extends BaseConfigTemplate
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
        return 'bash.exe';
    }

    protected function getArgs(OsInfo $osInfo, string $projectPath, array $options = []): array
    {
        // Build the base command
        $bashCommand = 'ctx server';

        // Only add -c option if project path is explicitly requested
        if (isset($options['use_project_path']) && $options['use_project_path']) {
            $bashCommand .= " -c {$projectPath}";
        }

        // Handle environment variables by exporting them in the bash command
        $env = $this->getEnvironmentVariables($options);
        if (!empty($env)) {
            $exports = [];
            foreach ($env as $key => $value) {
                $exports[] = "export {$key}={$value}";
            }
            $exportString = \implode(' && ', $exports);
            $bashCommand = "{$exportString} && {$bashCommand}";
        }

        return [
            '-c',
            $bashCommand,
        ];
    }

    #[\Override]
    protected function generateClaudeConfig(
        OsInfo $osInfo,
        string $projectPath,
        array $options = [],
    ): McpConfig {
        $command = $this->getCommand($osInfo);
        $args = $this->getArgs($osInfo, $projectPath, $options);

        // For WSL, we don't use the env property in Claude config since
        // environment variables are handled within the bash command
        $configData = [
            'mcpServers' => [
                'ctx' => [
                    'command' => $command,
                    'args' => $args,
                ],
            ],
        ];

        $metadata = [
            'os_name' => $osInfo->osName,
            'wsl_note' => 'Environment variables are exported within the bash command',
        ];

        if (isset($options['use_project_path']) && $options['use_project_path']) {
            $metadata['project_path'] = $projectPath;
        }

        return new McpConfig(
            clientType: 'claude',
            osType: $osInfo->getConfigType(),
            configData: $configData,
            command: $command,
            args: $args,
            env: $this->getEnvironmentVariables($options),
            metadata: $metadata,
        );
    }
}
