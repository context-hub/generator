<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects;

use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\McpServer\Projects\Console\ProjectAddCommand;
use Butschster\ContextGenerator\McpServer\Projects\Console\ProjectCommand;
use Butschster\ContextGenerator\McpServer\Projects\Console\ProjectListCommand;
use Butschster\ContextGenerator\McpServer\Projects\Repository\ProjectStateRepository;
use Butschster\ContextGenerator\McpServer\Projects\Repository\ProjectStateRepositoryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Core\FactoryInterface;

final class McpProjectsBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ProjectStateRepositoryInterface::class => static fn(
                FactoryInterface $factory,
                DirectoriesInterface $dirs,
            ) => $factory->make(ProjectStateRepository::class, [
                'stateDirectory' => $dirs->get('global-state'),
            ]),
            ProjectServiceInterface::class => ProjectService::class,
        ];
    }

    public function init(ConsoleBootloader $console): void
    {
        $console->addCommand(
            ProjectCommand::class,
            ProjectAddCommand::class,
            ProjectListCommand::class,
        );
    }
}
