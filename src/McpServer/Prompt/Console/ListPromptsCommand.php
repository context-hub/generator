<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Console;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Console\BaseCommand;
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
        // Display command title
        $this->outputService->title('Available Prompts');
        
        // Display filter criteria if any are set
        if (!empty($this->tags) || !empty($this->excludeTags) || !empty($this->promptIds)) {
            $this->outputService->section('Active Filters');
            
            if (!empty($this->promptIds)) {
                $this->outputService->keyValue(
                    'Prompt IDs', 
                    \implode(', ', \array_map(
                        fn($id) => $this->outputService->highlight($id, 'bright-cyan'),
                        $this->promptIds
                    ))
                );
            }
            
            if (!empty($this->tags)) {
                $this->outputService->keyValue(
                    'Include Tags', 
                    \implode(', ', \array_map(
                        fn($tag) => $this->outputService->highlight($tag, 'bright-green'),
                        $this->tags
                    ))
                );
            }
            
            if (!empty($this->excludeTags)) {
                $this->outputService->keyValue(
                    'Exclude Tags', 
                    \implode(', ', \array_map(
                        fn($tag) => $this->outputService->highlight($tag, 'red'),
                        $this->excludeTags
                    ))
                );
            }
        }
        
        // Configuration section
        $this->outputService->section('Configuration');
        $configSource = $this->configPath !== null ? 
            $this->configPath : 
            'Default location';
        $this->outputService->keyValue('Config Source', $configSource);
        
        if ($this->detailed) {
            $this->outputService->info('Detailed mode is enabled, showing argument information');
        }

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
                    $this->outputService->info('Loading configuration...');
                    
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

                    $this->outputService->error('Failed to load configuration: ' . $e->getMessage());
                    
                    // Add helpful tips for configuration issues
                    $this->outputService->note([
                        'Possible solutions:',
                        '- Check if the configuration file exists and is accessible',
                        '- Ensure the configuration file contains valid YAML or JSON',
                        '- Use --config-file to specify a different configuration file'
                    ]);

                    return Command::FAILURE;
                }

                // Load configuration to make sure all prompts are properly registered
                $loader->load();
                $this->outputService->success('Configuration loaded successfully');

                // Get all prompts
                $prompts = $promptProvider->all();
                $totalPrompts = \count($prompts);
                $this->outputService->keyValue('Total Prompts Found', 
                    $this->outputService->formatCount($totalPrompts));

                // Create filter based on command options
                $filter = $this->createFilter($filterFactory);

                // Apply filter if needed
                if ($filter !== null) {
                    $this->outputService->info('Applying filters to prompts...');
                    
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
                    
                    $filteredCount = \count($prompts);
                    $this->outputService->keyValue('Prompts After Filtering', 
                        $this->outputService->formatCount($filteredCount));
                }

                if (empty($prompts)) {
                    $this->outputService->warning('No prompts found matching the specified criteria.');
                    
                    // Show helpful information about available tags
                    $this->outputService->note([
                        'Troubleshooting:',
                        '- Try removing some filters or using different tags',
                        '- Check your configuration file for properly defined prompts',
                        '- Use the command without filters to see all available prompts'
                    ]);
                    
                    return Command::SUCCESS;
                }

                // Display prompts using table renderer
                return $this->displayPrompts($prompts);
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

        return !empty($filterConfig) ? $filterFactory->createFromConfig($filterConfig) : null;
    }

    /**
     * Displays prompts using the TableRenderer.
     * @param array<PromptDefinition> $prompts
     */
    private function displayPrompts(array $prompts): int
    {
        $this->outputService->section('Prompt List');
        
        // Use TableRenderer for better styled output
        $tableRenderer = $this->outputService->getTableRenderer();
        
        if ($this->detailed) {
            $headers = ['ID', 'Type', 'Description', 'Tags', 'Arguments'];
        } else {
            $headers = ['ID', 'Type', 'Description', 'Tags'];
        }
        
        // Create styled headers
        $styledHeaders = $tableRenderer->createStyledHeaderRow($headers);
        
        // Build rows with proper styling
        $rows = [];
        foreach ($prompts as $promptDef) {
            $row = [
                $tableRenderer->createPropertyCell($promptDef->id),
                $tableRenderer->createStyledCell(
                    $promptDef->type->value,
                    $promptDef->type === PromptType::Prompt ? 'green' : 'blue'
                ),
                $promptDef->prompt->description ?? '-',
                !empty($promptDef->tags) ? 
                    \implode(', ', \array_map(
                        fn($tag) => $this->outputService->highlight($tag, 'bright-cyan'),
                        $promptDef->tags
                    )) : 
                    '-',
            ];

            if ($this->detailed) {
                $args = $this->formatArguments($promptDef->prompt);
                $row[] = $args;
            }

            $rows[] = $row;
        }

        // Render the table with separators between rows if detailed
        $tableRenderer->render($styledHeaders, $rows, null, $this->detailed);
        
        // Display summary information
        $this->outputService->section('Summary');
        
        // Group prompts by type for summary
        $typeCount = [];
        foreach ($prompts as $promptDef) {
            $type = $promptDef->type->value;
            if (!isset($typeCount[$type])) {
                $typeCount[$type] = 0;
            }
            $typeCount[$type]++;
        }
        
        // Display statistics
        $summaryRenderer = $this->outputService->getSummaryRenderer();
        $summaryRenderer->renderStatsSummary('Prompt Statistics', [
            'Total Prompts' => \count($prompts),
            'Prompt Types' => \implode(', ', \array_keys($typeCount)),
        ]);
        
        // Display counts by type
        $summaryRenderer->renderCompletionSummary($typeCount);
        
        // Display helpful tips for usage
        $this->outputService->note([
            'Usage tips:',
            '- Use --detailed (-d) for more information about each prompt',
            '- Filter by tag with --tag=<tag> (can be used multiple times)',
            '- Filter by prompt ID with --id=<id> (can be used multiple times)'
        ]);

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
                $name = $this->outputService->highlight($name . '*', 'bright-red');
            } else {
                $name = $this->outputService->highlight($name, 'cyan');
            }

            if ($arg->description) {
                $name .= \sprintf(' (%s)', $arg->description);
            }

            $args[] = $name;
        }

        return \implode("\n", $args);
    }
}
