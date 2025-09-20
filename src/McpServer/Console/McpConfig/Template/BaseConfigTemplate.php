<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console\McpConfig\Template;

use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\OsInfo;

abstract class BaseConfigTemplate implements ConfigTemplateInterface
{
    public function getSupportedClients(): array
    {
        return ['claude', 'generic'];
    }

    protected function generateClaudeConfig(
        OsInfo $osInfo,
        string $projectPath,
        array $options = [],
    ): McpConfig {
        $command = $this->getCommand($osInfo);
        $args = $this->getArgs($osInfo, $projectPath, $options);
        $env = $this->getEnvironmentVariables($options);

        $configData = [
            'mcpServers' => [
                'ctx' => [
                    'command' => $command,
                    'args' => $args,
                ],
            ],
        ];

        // Add environment variables if present
        if (!empty($env)) {
            $configData['mcpServers']['ctx']['env'] = $env;
        }

        return new McpConfig(
            clientType: 'claude',
            osType: $osInfo->getConfigType(),
            configData: $configData,
            command: $command,
            args: $args,
            env: $env,
            metadata: [
                'os_name' => $osInfo->osName,
                'project_path' => $projectPath,
            ],
        );
    }

    protected function generateGenericConfig(
        OsInfo $osInfo,
        string $projectPath,
        array $options = [],
    ): McpConfig {
        $command = $this->getCommand($osInfo);
        $args = $this->getArgs($osInfo, $projectPath, $options);
        $env = $this->getEnvironmentVariables($options);

        $configData = [
            'command' => $command,
            'args' => $args,
        ];

        if (!empty($env)) {
            $configData['env'] = $env;
        }

        return new McpConfig(
            clientType: 'generic',
            osType: $osInfo->getConfigType(),
            configData: $configData,
            command: $command,
            args: $args,
            env: $env,
            metadata: [
                'os_name' => $osInfo->osName,
                'project_path' => $projectPath,
            ],
        );
    }

    abstract protected function getCommand(OsInfo $osInfo): string;

    abstract protected function getArgs(OsInfo $osInfo, string $projectPath, array $options = []): array;

    protected function getEnvironmentVariables(array $options = []): array
    {
        $env = [];

        // Add commonly needed environment variables
        if (isset($options['github_token'])) {
            $env['GITHUB_PAT'] = $options['github_token'];
        }

        if (isset($options['enable_file_operations'])) {
            $env['MCP_FILE_OPERATIONS'] = $options['enable_file_operations'] ? 'true' : 'false';
        }

        return $env;
    }
}
