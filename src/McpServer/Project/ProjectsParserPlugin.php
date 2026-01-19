<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\McpServer\Project\Exception\ProjectPathException;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;

/**
 * Config parser plugin that handles the `projects` section in context.yaml.
 *
 * Resolution algorithm (two-pass):
 * 1. First pass: Process path-based projects, resolve and claim paths
 * 2. Second pass: Process alias-based projects, skip if path already claimed
 *
 * This ensures local path-based definitions take priority over global aliases.
 */
final readonly class ProjectsParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private ProjectWhitelistRegistry $registry,
        #[Proxy] private ProjectServiceInterface $projectService,
        private ProjectPathResolverInterface $pathResolver,
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
        return $config;
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        if (!$this->supports($config)) {
            return null;
        }

        $this->logger?->debug('Parsing projects configuration', [
            'projectCount' => \count($config['projects']),
            'rootPath' => $rootPath,
        ]);

        // Track claimed paths (resolvedPath => projectName)
        $claimedPaths = [];

        // First pass: Process path-based projects (local priority)
        $claimedPaths = $this->processPathBasedProjects(
            $config['projects'],
            $rootPath,
            $claimedPaths,
        );

        // Second pass: Process alias-based projects (skip if path claimed)
        $this->processAliasBasedProjects(
            $config['projects'],
            $claimedPaths,
        );

        return null;
    }

    /**
     * First pass: Process projects with 'path' field.
     *
     * @param array $projects Raw project configurations
     * @param string $rootPath Context file directory
     * @param array<string, string> $claimedPaths Already claimed paths
     * @return array<string, string> Updated claimed paths (path => name)
     */
    private function processPathBasedProjects(
        array $projects,
        string $rootPath,
        array $claimedPaths,
    ): array {
        foreach ($projects as $projectData) {
            if (!\is_array($projectData)) {
                continue;
            }

            // Skip if no path field (alias-based)
            if (!isset($projectData['path']) || $projectData['path'] === '') {
                continue;
            }

            $name = $projectData['name'] ?? null;
            if ($name === null || $name === '') {
                $this->logger?->warning('Path-based project missing name', [
                    'data' => $projectData,
                ]);
                continue;
            }

            try {
                // Resolve the path
                $resolvedPath = $this->pathResolver->resolve(
                    $projectData['path'],
                    $rootPath,
                );

                // Check if this path is already claimed
                if (isset($claimedPaths[$resolvedPath])) {
                    $this->logger?->warning('Path already claimed by another project', [
                        'path' => $resolvedPath,
                        'existingProject' => $claimedPaths[$resolvedPath],
                        'skippedProject' => $name,
                    ]);
                    continue;
                }

                // Create and register the project
                /** @var array{name: string, description?: string|null, path: string} $validatedData */
                $validatedData = [
                    'name' => $name,
                    'description' => isset($projectData['description']) && \is_string($projectData['description'])
                        ? $projectData['description']
                        : null,
                    'path' => (string) $projectData['path'],
                ];
                $projectConfig = ProjectConfig::fromArray($validatedData, $resolvedPath);

                if ($projectConfig === null) {
                    continue;
                }

                $this->registry->register($projectConfig);
                $claimedPaths[$resolvedPath] = $name;

                $this->logger?->info('Registered path-based project', [
                    'name' => $name,
                    'path' => $projectData['path'],
                    'resolvedPath' => $resolvedPath,
                ]);
            } catch (ProjectPathException $e) {
                $this->logger?->warning('Failed to resolve project path', [
                    'name' => $name,
                    'path' => $projectData['path'],
                    'error' => $e->getMessage(),
                    'reason' => $e->reason,
                ]);
            }
        }

        return $claimedPaths;
    }

    /**
     * Second pass: Process projects without 'path' field (alias-based).
     *
     * @param array $projects Raw project configurations
     * @param array<string, string> $claimedPaths Paths claimed by path-based projects
     */
    private function processAliasBasedProjects(
        array $projects,
        array $claimedPaths,
    ): void {
        // Get global aliases
        $globalAliases = $this->projectService->getAliases();

        foreach ($projects as $projectData) {
            if (!\is_array($projectData)) {
                continue;
            }

            // Skip if has path field (already processed)
            if (isset($projectData['path']) && $projectData['path'] !== '') {
                continue;
            }

            $name = $projectData['name'] ?? null;
            if ($name === null || $name === '') {
                $this->logger?->warning('Alias-based project missing name', [
                    'data' => $projectData,
                ]);
                continue;
            }

            // Check if alias exists in global registry
            if (!isset($globalAliases[$name])) {
                $this->logger?->debug('Alias not found in global registry', [
                    'name' => $name,
                ]);
                continue;
            }

            $resolvedPath = $globalAliases[$name];

            // Check if this path is already claimed by a path-based project
            if (isset($claimedPaths[$resolvedPath])) {
                $this->logger?->info('Skipping alias - path claimed by local project', [
                    'alias' => $name,
                    'resolvedPath' => $resolvedPath,
                    'claimedBy' => $claimedPaths[$resolvedPath],
                ]);
                continue;
            }

            // Create and register the project
            $projectConfig = ProjectConfig::fromArray($projectData, $resolvedPath);

            if ($projectConfig === null) {
                continue;
            }

            $this->registry->register($projectConfig);

            $this->logger?->info('Registered alias-based project', [
                'name' => $name,
                'resolvedPath' => $resolvedPath,
            ]);
        }
    }
}
