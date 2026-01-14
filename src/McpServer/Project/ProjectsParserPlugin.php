<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;

/**
 * Config parser plugin that handles the `projects` section in context.yaml.
 *
 * This plugin:
 * 1. Reads the `projects` array from configuration
 * 2. Validates each project's alias exists in .project-state.json
 * 3. Registers valid projects in the ProjectWhitelistRegistry
 *
 * Projects that reference non-existent aliases are silently skipped.
 */
final readonly class ProjectsParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private ProjectWhitelistRegistry $registry,
        #[Proxy] private ProjectServiceInterface $projectService,
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        return 'projects';
    }

    public function supports(array $config): bool
    {
        return isset($config['projects']) && \is_array($config['projects']);
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        // No config transformation needed
        return $config;
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports($config)) {
            return null;
        }

        // Get all available aliases from .project-state.json
        $availableAliases = $this->projectService->getAliases();

        $this->logger?->debug('Parsing projects configuration', [
            'projectCount' => \count($config['projects']),
            'availableAliases' => \array_keys($availableAliases),
        ]);

        foreach ($config['projects'] as $projectData) {
            if (!\is_array($projectData)) {
                continue;
            }

            $projectConfig = ProjectConfig::fromArray($projectData);

            if ($projectConfig === null) {
                $this->logger?->warning('Invalid project configuration - missing name', [
                    'data' => $projectData,
                ]);
                continue;
            }

            // Only register if alias exists in .project-state.json
            if (!isset($availableAliases[$projectConfig->name])) {
                $this->logger?->debug('Skipping project - alias not found in .project-state.json', [
                    'name' => $projectConfig->name,
                ]);
                continue;
            }

            $this->logger?->info('Registering whitelisted project', [
                'name' => $projectConfig->name,
                'description' => $projectConfig->description,
                'path' => $availableAliases[$projectConfig->name],
            ]);

            $this->registry->register($projectConfig);
        }

        // Return null - we populate the registry directly rather than returning one
        return null;
    }
}
