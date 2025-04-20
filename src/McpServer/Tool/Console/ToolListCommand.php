<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Console;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\ToolProviderInterface;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;

#[AsCommand(
    name: 'tool:list',
    description: 'List all available tools with their details',
    aliases: ['tools'],
)]
final class ToolListCommand extends BaseCommand
{
    #[Option(
        name: 'config-file',
        shortcut: 'c',
        description: 'Path to configuration file (absolute or relative to current directory).',
    )]
    protected ?string $configPath = null;

    #[Option(
        name: 'type',
        shortcut: 't',
        description: 'Filter tools by type (e.g., run, http)',
    )]
    protected ?string $type = null;

    #[Option(
        name: 'id',
        shortcut: 'i',
        description: 'Filter tools by ID (can be used multiple times)',
    )]
    protected array $toolIds = [];

    #[Option(
        name: 'detailed',
        shortcut: 'd',
        description: 'Show detailed information including arguments',
    )]
    protected bool $detailed = false;

    public function __invoke(Container $container, DirectoriesInterface $dirs): int
    {
        return $container->runScope(
            bindings: new Scope(
                name: AppScope::Compiler,
                bindings: [
                    DirectoriesInterface::class => $dirs->determineRootPath($this->configPath),
                ],
            ),
            scope: function (
                ConfigurationProvider $configProvider,
                ToolProviderInterface $toolProvider,
            ) {
                try {
                    // Get the appropriate loader based on options provided
                    if ($this->configPath !== null) {
                        $this->logger->info(\sprintf('Loading configuration from %s...', $this->configPath));
                        $loader = $configProvider->fromPath($this->configPath);
                    } else {
                        $this->logger->info('Loading configuration from default location...');
                        $loader = $configProvider->fromDefaultLocation();
                    }
                } catch (ConfigLoaderException $e) {
                    $this->logger->error('Failed to load configuration', [
                        'error' => $e->getMessage(),
                    ]);

                    $this->output->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));

                    return Command::FAILURE;
                }

                // Load configuration to make sure all tools are properly registered
                $loader->load();

                // Get all tools
                $tools = $toolProvider->all();

                // Apply filters if needed
                if (!empty($this->toolIds) || $this->type !== null) {
                    $filteredTools = [];
                    foreach ($tools as $tool) {
                        // Filter by ID if specified
                        if (!empty($this->toolIds) && !\in_array($tool->id, $this->toolIds, true)) {
                            continue;
                        }

                        // Filter by type if specified
                        if ($this->type !== null && $tool->type !== $this->type) {
                            continue;
                        }

                        $filteredTools[] = $tool;
                    }
                    $tools = $filteredTools;
                }

                if (empty($tools)) {
                    $this->output->warning('No tools found matching the specified criteria.');
                    return Command::SUCCESS;
                }

                // Display tools as a table
                return $this->displayAsTable($tools);
            },
        );
    }

    /**
     * Displays tools as a table.
     *
     * @param array<ToolDefinition> $tools
     */
    private function displayAsTable(array $tools): int
    {
        $title = 'Available Tools';
        $this->output->title($title);

        $table = new Table($this->output);

        if ($this->detailed) {
            $table->setHeaders(['ID', 'Type', 'Description', 'Schema', 'Commands']);
        } else {
            $table->setHeaders(['ID', 'Type', 'Description', 'Schema']);
        }

        foreach ($tools as $tool) {
            $hasSchema = $tool->schema !== null && !empty($tool->schema->getProperties());

            $row = [
                new TableCell($tool->id, ['style' => new TableCellStyle(['fg' => 'cyan'])]),
                new TableCell(
                    $tool->type,
                    ['style' => new TableCellStyle(['fg' => $tool->type === 'run' ? 'green' : 'blue'])],
                ),
                $tool->description,
                $hasSchema ? '✓' : '-',
            ];

            if ($this->detailed) {
                $commandDetails = $this->formatCommands($tool);
                $row[] = $commandDetails;
            }

            $table->addRow($row);
            if ($this->detailed) {
                $table->addRow(new TableSeparator());
            }
        }

        $table->render();

        $this->output->writeln('');
        $this->output->writeln(\sprintf('%s: %s', Style::property('Total tools'), Style::count(\count($tools))));
        $this->output->writeln('');
        $this->output->writeln('To execute a tool, run: <info>tool:run <tool-id></info>');

        return Command::SUCCESS;
    }

    /**
     * Formats commands information for display in detailed view.
     */
    private function formatCommands(ToolDefinition $tool): string
    {
        if ($tool->type !== 'run' || empty($tool->commands)) {
            return '-';
        }

        $commands = [];
        foreach ($tool->commands as $index => $command) {
            $args = [];
            foreach ($command->args as $arg) {
                $args[] = (string) $arg;
            }

            $cmdInfo = $command->cmd;
            if (!empty($args)) {
                $cmdInfo .= ' ' . \implode(' ', $args);
            }

            $commands[] = \sprintf('#%d: %s', $index + 1, $cmdInfo);
        }

        return \implode("\n", $commands);
    }
}
