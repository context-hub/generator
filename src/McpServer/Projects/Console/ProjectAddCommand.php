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
        // Display command title
        $this->outputService->title('Add Project');
        
        // Display input parameters
        $this->outputService->section('Project Parameters');
        $this->outputService->keyValue('Project Path', $this->path);
        
        if ($this->name !== null) {
            $this->outputService->keyValue('Project Alias', $this->name);
        }
        
        if ($this->configFile !== null) {
            $this->outputService->keyValue('Config File', $this->configFile);
        }
        
        if ($this->envFile !== null) {
            $this->outputService->keyValue('Environment File', $this->envFile);
        }

        // Handle path resolution
        $this->outputService->section('Path Resolution');
        
        // Handle using an alias as the path
        $resolvedPath = $projectService->resolvePathOrAlias($this->path);
        if ($resolvedPath !== $this->path) {
            $this->logger->info(\sprintf("Resolved alias '%s' to path: %s", $this->path, $resolvedPath));
            $this->outputService->info(\sprintf(
                "Resolved alias %s to path: %s",
                $this->outputService->highlight($this->path, 'bright-cyan'),
                $this->outputService->highlight($resolvedPath, 'bright-green')
            ));
            $this->path = $resolvedPath;
        }

        // Normalize path to absolute path
        $projectPath = $this->normalizePath($this->path, $dirs);
        $this->outputService->keyValue('Absolute Path', $projectPath);

        // Validate project path
        $statusRenderer = $this->outputService->getStatusRenderer();
        
        $this->outputService->section('Validation Checks');
        
        if (!$files->exists($projectPath)) {
            $statusRenderer->renderError('Path Existence', 'Not found');
            $this->outputService->error(\sprintf("Project path does not exist: %s", $projectPath));
            return Command::FAILURE;
        } else {
            $statusRenderer->renderSuccess('Path Existence', 'Found');
        }

        if (!$files->isDirectory($projectPath)) {
            $statusRenderer->renderError('Path Type', 'Not a directory');
            $this->outputService->error(\sprintf("Project path is not a directory: %s", $projectPath));
            return Command::FAILURE;
        } else {
            $statusRenderer->renderSuccess('Path Type', 'Directory');
        }

        // Validate env file path if provided
        if ($this->envFile !== null) {
            $envPath = FSPath::create($projectPath)->join($this->envFile)->toString();
            $this->outputService->keyValue('Env File Path', $envPath);
            
            if (!$files->exists($envPath)) {
                $statusRenderer->renderWarning('Env File', 'Not found');
                $this->outputService->warning(\sprintf(
                    "Env file does not exist: %s (will continue anyway)",
                    $envPath
                ));
            } else {
                $statusRenderer->renderSuccess('Env File', 'Found');
            }
        }

        // Validate configuration
        $this->outputService->section('Configuration Validation');
        
        try {
            // Create temporary directories to test config loading
            $tempDirs = $dirs->determineRootPath(null, null)->withRootPath($projectPath);
            $this->outputService->info('Testing configuration loading...');

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
            
            $statusRenderer->renderSuccess('Configuration', 'Valid context configuration found');
        } catch (ConfigLoaderException $e) {
            $statusRenderer->renderError('Configuration', 'Invalid or missing');
            $this->outputService->error(\sprintf(
                "No valid context configuration found in %s: %s",
                $projectPath,
                $e->getMessage(),
            ));
            
            // Provide helpful tips
            $this->outputService->note([
                'Possible solutions:',
                '- Ensure a valid context.yaml or context.json file exists in the project directory',
                '- Use --config-file option to specify a custom configuration file path',
                '- Run "ctx init" in the project directory to create a configuration file'
            ]);
            
            return Command::FAILURE;
        }

        // Add the project
        $this->outputService->section('Adding Project');
        $projectService->addProject($projectPath, $this->name, $this->configFile, $this->envFile);

        // Display success information
        $statusList = [
            'Project path' => ['status' => 'success', 'message' => $projectPath],
        ];
        
        if ($this->name) {
            $statusList['Project alias'] = ['status' => 'success', 'message' => $this->name];
        }
        
        if ($this->configFile) {
            $statusList['Config file'] = ['status' => 'success', 'message' => $this->configFile];
        }
        
        if ($this->envFile) {
            $statusList['Env file'] = ['status' => 'success', 'message' => $this->envFile];
        }
        
        $this->outputService->statusList($statusList);

        // If this is the first project, also set it as the current project
        $isFirstProject = \count($projectService->getProjects()) === 1;
        if ($isFirstProject) {
            $projectService->setCurrentProject($projectPath, $this->name, $this->configFile, $this->envFile);
            $statusRenderer->renderInfo(
                'Current Project', 
                'Automatically set as the current project (first project added)'
            );
        }
        
        // Summary section
        $this->outputService->section('Summary');
        
        $summaryRenderer = $this->outputService->getSummaryRenderer();
        $summaryRenderer->renderStatsSummary('Project Information', [
            'Path' => $projectPath,
            'Alias' => $this->name ?? 'None',
            'Config File' => $this->configFile ?? 'Default',
            'Env File' => $this->envFile ?? 'None',
            'Set as Current' => $isFirstProject ? 'Yes (First Project)' : 'No'
        ]);

        // Final success message
        $this->outputService->success(\sprintf(
            "Project %s has been successfully added",
            $this->outputService->highlight($projectPath, 'bright-cyan')
        ));
        
        // Display next steps hint
        $this->outputService->note([
            'Next steps:',
            '- Use "ctx project" to view or switch between projects',
            '- Use "ctx project:list" to see all registered projects',
            $isFirstProject ? '- Project is already set as current' : '- Use "ctx project ' . $projectPath . '" to switch to this project'
        ]);

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
