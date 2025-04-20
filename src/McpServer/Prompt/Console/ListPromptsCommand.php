<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Console;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\FilterStrategy;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\PromptFilterFactory;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\PromptFilterInterface;
use Butschster\ContextGenerator\McpServer\Prompt\PromptProviderInterface;
use Butschster\ContextGenerator\McpServer\Prompt\PromptType;
use Mcp\Types\Prompt;
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
    name: 'prompts:list',
    description: 'List all available prompts with their details',
    aliases: ['prompts'],
)]
final class ListPromptsCommand extends BaseCommand
{
    #[Option(
        name: 'config-file',
        shortcut: 'c',
        description: 'Path to configuration file (absolute or relative to current directory).',
    )]
    protected ?string $configPath = null;

    #[Option(
        name: 'tag',
        shortcut: 't',
        description: 'Filter prompts by tag (can be used multiple times)',
    )]
    protected array $tags = [];

    #[Option(
        name: 'exclude-tag',
        shortcut: 'x',
        description: 'Exclude prompts with specific tag (can be used multiple times)',
    )]
    protected array $excludeTags = [];

    #[Option(
        name: 'id',
        shortcut: 'p',
        description: 'Filter prompts by ID (can be used multiple times)',
    )]
    protected array $promptIds = [];

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
                PromptProviderInterface $promptProvider,
                PromptFilterFactory $filterFactory,
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

                // Load configuration to make sure all prompts are properly registered
                $loader->load();

                // Get all prompts
                $prompts = $promptProvider->all();

                // Create filter based on command options
                $filter = $this->createFilter($filterFactory);

                // Apply filter if needed
                if ($filter !== null) {
                    $filteredPrompts = [];
                    foreach ($prompts as $id => $promptDef) {
                        $promptConfig = [
                            'id' => $promptDef->id,
                            'tags' => $promptDef->tags,
                        ];

                        if ($filter->shouldInclude($promptConfig)) {
                            $filteredPrompts[$id] = $promptDef;
                        }
                    }
                    $prompts = $filteredPrompts;
                }

                if (empty($prompts)) {
                    $this->output->warning('No prompts found matching the specified criteria.');
                    return Command::SUCCESS;
                }

                // Display prompts based on the selected format
                return $this->displayAsTable($prompts);
            },
        );
    }

    /**
     * Creates a filter based on command options.
     */
    private function createFilter(PromptFilterFactory $filterFactory): ?PromptFilterInterface
    {
        $filterConfig = [];

        // Add ID filter if provided
        if (!empty($this->promptIds)) {
            $filterConfig['ids'] = $this->promptIds;
        }

        // Add tag filters if provided
        if (!empty($this->tags) || !empty($this->excludeTags)) {
            $filterConfig['tags'] = [];

            if (!empty($this->tags)) {
                $filterConfig['tags']['include'] = $this->tags;
                $filterConfig['tags']['match'] = FilterStrategy::ANY->value;
            }

            if (!empty($this->excludeTags)) {
                $filterConfig['tags']['exclude'] = $this->excludeTags;
            }
        }

        return $filterFactory->createFromConfig($filterConfig);
    }

    /**
     * Displays prompts as a table.
     * @param array<PromptDefinition> $prompts
     */
    private function displayAsTable(array $prompts): int
    {
        $title = 'Available Prompts';
        $this->output->title($title);

        $table = new Table($this->output);

        if ($this->detailed) {
            $table->setHeaders(['ID', 'Type', 'Description', 'Tags', 'Arguments']);
        } else {
            $table->setHeaders(['ID', 'Type', 'Description', 'Tags']);
        }

        foreach ($prompts as $promptDef) {
            $row = [
                new TableCell($promptDef->id, ['style' => new TableCellStyle(['fg' => 'cyan'])]),
                new TableCell(
                    $promptDef->type->value,
                    [
                        'style' => new TableCellStyle(
                            ['fg' => $promptDef->type === PromptType::Prompt ? 'green' : 'blue'],
                        ),
                    ],
                ),
                $promptDef->prompt->description ?? '-',
                \implode(', ', $promptDef->tags),
            ];

            if ($this->detailed) {
                $args = $this->formatArguments($promptDef->prompt);
                $row[] = $args;
            }

            $table->addRow($row);
            if ($this->detailed) {
                $table->addRow(new TableSeparator());
            }
        }

        $table->render();

        $this->output->writeln('');
        $this->output->writeln(\sprintf('%s: %s', Style::property('Total prompts'), Style::count(\count($prompts))));

        return Command::SUCCESS;
    }

    /**
     * Formats arguments for display.
     */
    private function formatArguments(Prompt $prompt): string
    {
        if (empty($prompt->arguments)) {
            return '-';
        }

        $args = [];
        foreach ($prompt->arguments as $arg) {
            $name = $arg->name;
            if ($arg->required) {
                $name .= '*';
            }

            if ($arg->description) {
                $name .= \sprintf(' (%s)', $arg->description);
            }

            $args[] = $name;
        }

        return \implode("\n", $args);
    }
}
