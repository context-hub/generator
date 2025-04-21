<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Console;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Projects\ProjectServiceInterface;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Spiral\Files\FilesInterface;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'project:add',
    description: 'Add an additional project context',
)]
final class ProjectAddCommand extends BaseCommand
{
    #[Argument(
        name: 'path',
        description: 'Path to the project directory. Use "." for current directory',
    )]
    protected string $path;

    #[Option(
        name: 'name',
        description: 'Alias name for the project',
    )]
    protected ?string $name = null;

    #[Option(
        name: 'config-file',
        shortcut: 'c',
        description: 'Path to custom configuration file within the project',
    )]
    protected ?string $configFile = null;

    #[Option(
        name: 'env-file',
        shortcut: 'e',
        description: 'Path to .env file within the project',
    )]
    protected ?string $envFile = null;

    public function __invoke(
        Container $container,
        DirectoriesInterface $dirs,
        FilesInterface $files,
        ProjectServiceInterface $projectService,
        ConfigurationProvider $configProvider,
    ): int {
        // Handle using an alias as the path
        $resolvedPath = $projectService->resolvePathOrAlias($this->path);
        if ($resolvedPath !== $this->path) {
            $this->logger->info(\sprintf("Resolved alias '%s' to path: %s", $this->path, $resolvedPath));
            $this->path = $resolvedPath;
        }

        // Normalize path to absolute path
        $projectPath = $this->normalizePath($this->path, $dirs);

        // Validate project path
        if (!$files->exists($projectPath)) {
            $this->output->error(\sprintf("Project path does not exist: %s", $projectPath));
            return Command::FAILURE;
        }

        if (!$files->isDirectory($projectPath)) {
            $this->output->error(\sprintf("Project path is not a directory: %s", $projectPath));
            return Command::FAILURE;
        }

        // Validate env file path if provided
        if ($this->envFile !== null) {
            $envPath = FSPath::create($projectPath)->join($this->envFile)->toString();
            if (!$files->exists($envPath)) {
                $this->output->warning(\sprintf("Env file does not exist: %s", $envPath));
                // Not returning failure here, just warning the user
            }
        }

        try {
            // Create temporary directories to test config loading
            $tempDirs = $dirs->determineRootPath(null, null)->withRootPath($projectPath);

            $container->runScope(
                bindings: new Scope(
                    name: AppScope::Compiler,
                    bindings: [
                        DirectoriesInterface::class => $tempDirs,
                    ],
                ),
                scope: static function () use ($configProvider): void {
                    $configProvider->fromDefaultLocation()->load();
                },
            );
        } catch (ConfigLoaderException $e) {
            $this->output->error(
                \sprintf(
                    "No valid context configuration found in %s: %s",
                    $projectPath,
                    $e->getMessage(),
                ),
            );
            return Command::FAILURE;
        }

        // Add the project
        $projectService->addProject($projectPath, $this->name, $this->configFile, $this->envFile);

        $this->output->success(\sprintf("Added project: %s", $projectPath));

        if ($this->name) {
            $this->output->info(\sprintf("Project alias '%s' has been set", $this->name));
        }

        if ($this->envFile) {
            $this->output->info(\sprintf("Project env file '%s' has been set", $this->envFile));
        }

        // If this is the first project, also set it as the current project
        if (\count($projectService->getProjects()) === 1) {
            $projectService->setCurrentProject($projectPath, $this->name, $this->configFile, $this->envFile);
            $this->output->info('Project was automatically set as the current project (first project added).');
        }

        return Command::SUCCESS;
    }

    /**
     * Normalize a path to an absolute path
     */
    private function normalizePath(string $path, DirectoriesInterface $dirs): string
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
