<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Actions;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Project\ProjectWhitelistRegistryInterface;
use Butschster\ContextGenerator\McpServer\Projects\Actions\Dto\CurrentProjectResponse;
use Butschster\ContextGenerator\McpServer\Projects\Actions\Dto\ProjectInfoResponse;
use Butschster\ContextGenerator\McpServer\Projects\Actions\Dto\ProjectsListResponse;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;

#[Tool(
    name: 'projects-list',
    description: 'List all registered projects with their paths, aliases, and configuration details. Also shows whitelisted projects available for the "project" parameter in tools.',
    title: 'Projects List',
)]
final readonly class ProjectsListToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        #[Proxy] private ProjectServiceInterface $projectService,
        private ProjectWhitelistRegistryInterface $whitelistRegistry,
    ) {}

    #[Post(path: '/tools/call/projects-list', name: 'tools.projects-list')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing projects-list tool');

        try {
            $projects = $this->projectService->getProjects();
            $aliases = $this->projectService->getAliases();
            $currentProject = $this->projectService->getCurrentProject();

            // Get whitelisted projects from context.yaml configuration
            $whitelistedProjects = $this->whitelistRegistry->getProjects();

            if (empty($projects)) {
                $response = new ProjectsListResponse(
                    projects: [],
                    currentProject: null,
                    totalProjects: 0,
                    whitelistedProjects: $whitelistedProjects,
                    message: 'No projects registered. Use project:add command to add projects.',
                );

                return ToolResult::success($response);
            }

            // Create inverse alias map for quick lookups
            $pathToAliases = [];
            foreach ($aliases as $alias => $path) {
                if (!isset($pathToAliases[$path])) {
                    $pathToAliases[$path] = [];
                }
                $pathToAliases[$path][] = $alias;
            }

            // Build project info responses
            $projectInfos = [];
            foreach ($projects as $path => $info) {
                $projectInfos[] = new ProjectInfoResponse(
                    path: $path,
                    configFile: $info->configFile,
                    envFile: $info->envFile,
                    addedAt: $info->addedAt,
                    aliases: $pathToAliases[$path] ?? [],
                    isCurrent: $currentProject && $currentProject->path === $path,
                );
            }

            // Build current project response
            $currentProjectResponse = null;
            if ($currentProject !== null) {
                $currentProjectResponse = new CurrentProjectResponse(
                    path: $currentProject->path,
                    configFile: $currentProject->hasConfigFile() ? $currentProject->getConfigFile() : null,
                    envFile: $currentProject->hasEnvFile() ? $currentProject->getEnvFile() : null,
                    aliases: $this->projectService->getAliasesForPath($currentProject->path),
                );
            }

            $response = new ProjectsListResponse(
                projects: [],
                currentProject: $currentProjectResponse,
                totalProjects: \count($projects),
                whitelistedProjects: $whitelistedProjects,
            );

            return ToolResult::success($response);
        } catch (\Throwable $e) {
            $this->logger->error('Error listing projects', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ToolResult::error($e->getMessage());
        }
    }
}
