<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Actions;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Project\ProjectWhitelistRegistryInterface;
use Butschster\ContextGenerator\McpServer\Projects\Actions\Dto\CurrentProjectResponse;
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
            $currentProject = $this->projectService->getCurrentProject();
            $whitelistedProjects = $this->whitelistRegistry->getProjects();

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
                projects: $whitelistedProjects,
                currentProject: $currentProjectResponse,
                totalProjects: \count($whitelistedProjects),
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
