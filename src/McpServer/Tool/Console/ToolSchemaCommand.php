<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Console;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\ToolProviderInterface;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'tool:schema',
    description: 'Display JSON schema for a tool',
)]
final class ToolSchemaCommand extends BaseCommand
{
    #[Argument(
        description: 'Tool ID to get schema for (optional, lists all if omitted)',
    )]
    protected ?string $toolId = null;

    #[Option(
        name: 'config-file',
        shortcut: 'c',
        description: 'Path to configuration file (absolute or relative to current directory).',
    )]
    protected ?string $configPath = null;

    #[Option(
        name: 'json',
        description: 'Output as JSON (for machine parsing)',
    )]
    protected bool $asJson = false;

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

                // If no tool ID specified, list all schemas
                if ($this->toolId === null) {
                    return $this->listAllSchemas($toolProvider);
                }

                // Get specific tool schema
                return $this->showToolSchema($toolProvider);
            },
        );
    }

    /**
     * List schemas for all tools.
     */
    private function listAllSchemas(ToolProviderInterface $toolProvider): int
    {
        $tools = $toolProvider->all();

        if (empty($tools)) {
            $this->output->warning('No tools found');
            return Command::SUCCESS;
        }

        $schemas = [];
        foreach ($tools as $tool) {
            $schemas[$tool->id] = $this->buildToolSchema($tool);
        }

        if ($this->asJson) {
            $this->output->writeln(\json_encode($schemas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($schemas as $id => $schema) {
                $this->output->section($id);
                $this->output->writeln(\sprintf('Description: %s', $schema['description']));
                $this->output->writeln(\sprintf('Type: %s', $schema['type']));
                $this->output->newLine();

                if ($schema['inputSchema'] !== null) {
                    $this->output->writeln('Input Schema:');
                    $this->output->writeln(\json_encode($schema['inputSchema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    $this->output->writeln('Input Schema: (none)');
                }

                $this->output->newLine();
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Show schema for a specific tool.
     */
    private function showToolSchema(ToolProviderInterface $toolProvider): int
    {
        try {
            $tool = $toolProvider->get($this->toolId);
        } catch (\InvalidArgumentException $e) {
            $this->output->error($e->getMessage());
            return Command::FAILURE;
        }

        $schema = $this->buildToolSchema($tool);

        if ($this->asJson) {
            $this->output->writeln(\json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->output->title($tool->id);
            $this->output->writeln(\sprintf('Description: %s', $schema['description']));
            $this->output->writeln(\sprintf('Type: %s', $schema['type']));
            $this->output->newLine();

            if ($schema['inputSchema'] !== null) {
                $this->output->writeln('Input Schema:');
                $this->output->writeln(\json_encode($schema['inputSchema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->output->writeln('Input Schema: (none)');
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Build schema array for a tool.
     */
    private function buildToolSchema(ToolDefinition $tool): array
    {
        $inputSchema = null;

        if ($tool->schema !== null) {
            $inputSchema = [
                'type' => 'object',
                'properties' => $tool->schema->getProperties(),
                'required' => $tool->schema->getRequiredProperties(),
            ];
        }

        return [
            'name' => $tool->id,
            'description' => $tool->description,
            'type' => $tool->type,
            'inputSchema' => $inputSchema,
        ];
    }
}
