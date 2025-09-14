<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Actions;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Projects\Actions\Dto\AliasResolutionResponse;
use Butschster\ContextGenerator\McpServer\Projects\Actions\Dto\CurrentProjectResponse;
use Butschster\ContextGenerator\McpServer\Projects\Actions\Dto\ProjectSwitchRequest;
use Butschster\ContextGenerator\McpServer\Projects\Actions\Dto\ProjectSwitchResponse;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;

#[Tool(
    name: 'project-switch',
    description: 'Switch to a different project by path or alias',
    title: 'Project Switch',
)]
#[InputSchema(class: ProjectSwitchRequest::class)]
final readonly class ProjectSwitchToolAction
{
    public function __construct(
        private LoggerInterface $logger,
        private ProjectServiceInterface $projectService,
    ) {}

    #[Post(path: '/tools/call/project-switch', name: 'tools.project-switch')]
    public function __invoke(ProjectSwitchRequest $request): CallToolResult
    {
        $this->logger->info('Processing project-switch tool', [
            'pathOrAlias' => $request->alias,
        ]);

        try {
            $pathOrAlias = $request->alias;

            if (empty($pathOrAlias)) {
                return new CallToolResult([
                    new TextContent(text: 'Error: Missing pathOrAlias parameter'),
                ], isError: true);
            }

            // Handle using an alias as the path
            $resolvedPath = $this->projectService->resolvePathOrAlias($pathOrAlias);
            $wasAlias = $resolvedPath !== $pathOrAlias;

            // Normalize path to absolute path
            $projectPath = $this->normalizePath($resolvedPath);

            // Check if the project exists in our registry
            $projects = $this->projectService->getProjects();
            if (!isset($projects[$projectPath])) {
                $availableProjects = \array_keys($projects);
                $availableAliases = \array_keys($this->projectService->getAliases());

                $suggestions = [];
                if (!empty($availableProjects)) {
                    $suggestions[] = 'Available project paths: ' . \implode(', ', $availableProjects);
                }
                if (!empty($availableAliases)) {
                    $suggestions[] = 'Available aliases: ' . \implode(', ', $availableAliases);
                }

                return new CallToolResult([
                    new TextContent(
                        text: \sprintf(
                            "Error: Project '%s' is not registered.\n%s",
                            $projectPath,
                            \implode("\n", $suggestions),
                        ),
                    ),
                ], isError: true);
            }

            // Try to switch to this project
            if ($this->projectService->switchToProject($projectPath)) {
                $currentProject = $this->projectService->getCurrentProject();
                $aliases = $this->projectService->getAliasesForPath($projectPath);

                $currentProjectResponse = new CurrentProjectResponse(
                    path: $currentProject->path,
                    configFile: $currentProject->hasConfigFile() ? $currentProject->getConfigFile() : null,
                    envFile: $currentProject->hasEnvFile() ? $currentProject->getEnvFile() : null,
                    aliases: $aliases,
                );

                $aliasResolution = null;
                if ($wasAlias) {
                    $aliasResolution = new AliasResolutionResponse(
                        originalAlias: $pathOrAlias,
                        resolvedPath: $projectPath,
                    );
                }

                $response = new ProjectSwitchResponse(
                    success: true,
                    message: \sprintf('Successfully switched to project: %s', $projectPath),
                    currentProject: $currentProjectResponse,
                    resolvedFromAlias: $aliasResolution,
                );

                return new CallToolResult([
                    new TextContent(text: \json_encode($response, JSON_PRETTY_PRINT)),
                ]);
            }

            $response = new ProjectSwitchResponse(
                success: false,
                message: \sprintf("Failed to switch to project '%s'", $projectPath),
            );

            return new CallToolResult([
                new TextContent(text: \json_encode($response, JSON_PRETTY_PRINT)),
            ], isError: true);
        } catch (\Throwable $e) {
            $this->logger->error('Error switching project', [
                'pathOrAlias' => $request->alias,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new CallToolResult([
                new TextContent(text: 'Error: ' . $e->getMessage()),
            ], isError: true);
        }
    }

    /**
     * Normalize a path to an absolute path
     */
    private function normalizePath(string $path): string
    {
        // Handle special case for current directory
        if ($path === '.') {
            return (string) FSPath::cwd();
        }

        $pathObj = FSPath::create($path);

        // If path is relative, make it absolute from the current directory
        if ($pathObj->isRelative()) {
            $pathObj = $pathObj->absolute();
        }

        return $pathObj->toString();
    }
}
