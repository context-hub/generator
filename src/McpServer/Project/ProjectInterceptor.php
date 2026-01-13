<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Interceptor\ToolInterceptorInterface;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;

/**
 * Interceptor that handles project context switching for tool requests.
 *
 * When a request specifies a project via the `project` parameter, this interceptor:
 * 1. Validates the project is in the whitelist (from context.yaml)
 * 2. Resolves the project alias to a path (from .project-state.json)
 * 3. Creates a new scope with DirectoriesInterface pointing to the project
 * 4. Executes the tool action within that scope
 */
final readonly class ProjectInterceptor implements ToolInterceptorInterface
{
    public function __construct(
        private ProjectWhitelistRegistryInterface $whitelist,
        #[Proxy] private ProjectServiceInterface $projectService,
        private DirectoriesInterface $dirs,
        private ScopeInterface $scope,
        private ?LoggerInterface $logger = null,
    ) {}

    public function intercept(object $request, callable $next): mixed
    {
        // Skip if request doesn't support project parameter
        if (!$request instanceof ProjectAwareRequest) {
            return $next();
        }

        $projectName = $request->getProject();

        // null project means use current project - no scope change needed
        if ($projectName === null) {
            return $next();
        }

        $this->logger?->debug('Processing project-aware request', [
            'project' => $projectName,
            'requestClass' => $request::class,
        ]);

        // Validate against whitelist
        if (!$this->whitelist->isAllowed($projectName)) {
            $availableNames = \array_map(
                static fn(ProjectConfig $p) => $p->name,
                $this->whitelist->getProjects(),
            );

            $this->logger?->warning('Project not in whitelist', [
                'project' => $projectName,
                'available' => $availableNames,
            ]);

            $message = \sprintf(
                "Project '%s' is not available.",
                $projectName,
            );

            if (!empty($availableNames)) {
                $message .= \sprintf(
                    ' Use projects-list to see available projects. Available: %s',
                    \implode(', ', $availableNames),
                );
            } else {
                $message .= ' Use projects-list to see available projects.';
            }

            return ToolResult::error($message);
        }

        // Resolve alias to path (lazy load ProjectServiceInterface to allow --state-dir to work)
        $projectPath = $this->projectService->resolvePathOrAlias($projectName);

        $this->logger?->info('Switching to project context', [
            'project' => $projectName,
            'path' => $projectPath,
        ]);

        // Create new DirectoriesInterface with project path
        $projectDirs = $this->dirs->withRootPath($projectPath);

        // Run in new scope with replaced DirectoriesInterface
        return $this->scope->runScope(
            bindings: new Scope(
                bindings: [
                    DirectoriesInterface::class => $projectDirs,
                ],
            ),
            scope: $next,
        );
    }
}
