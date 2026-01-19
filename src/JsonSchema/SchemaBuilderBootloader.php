<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema;

use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\JsonSchema\Combinator\AllOf;
use Butschster\ContextGenerator\JsonSchema\Combinator\Conditional;
use Butschster\ContextGenerator\JsonSchema\Combinator\OneOf;
use Butschster\ContextGenerator\JsonSchema\Console\BuildSchemaCommand;
use Butschster\ContextGenerator\JsonSchema\Definition\EnumDefinition;
use Butschster\ContextGenerator\JsonSchema\Definition\OneOfDefinition;
use Butschster\ContextGenerator\JsonSchema\Property\ArrayProperty;
use Butschster\ContextGenerator\JsonSchema\Property\BooleanProperty;
use Butschster\ContextGenerator\JsonSchema\Property\IntegerProperty;
use Butschster\ContextGenerator\JsonSchema\Property\NumberProperty;
use Butschster\ContextGenerator\JsonSchema\Property\ObjectProperty;
use Butschster\ContextGenerator\JsonSchema\Property\RefProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;

#[Singleton]
final class SchemaBuilderBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            SchemaDefinitionRegistryInterface::class => SchemaDefinitionRegistry::class,
            SchemaDefinitionRegistry::class => SchemaDefinitionRegistry::class,
        ];
    }

    public function init(ConsoleBootloader $console): void
    {
        $console->addCommand(BuildSchemaCommand::class);
    }

    public function boot(SchemaDefinitionRegistry $registry): void
    {
        $this->registerCoreDefinitions($registry);
        $this->registerRootSchema($registry);
    }

    private function registerCoreDefinitions(SchemaDefinitionRegistry $registry): void
    {
        $this->registerSourcePaths($registry);
        $this->registerPatternConstraint($registry);
        $this->registerTreeViewConfig($registry);
        $this->registerModifiers($registry);
        $this->registerInputSchema($registry);
        $this->registerDocument($registry);
        $this->registerSource($registry);
        $this->registerImportConfig($registry);
        $this->registerSettings($registry);
        $this->registerRagConfig($registry);
        $this->registerVisibilityOptions($registry);
        $this->registerTool($registry);
        $this->registerPrompt($registry);
    }

    private function registerRootSchema(SchemaDefinitionRegistry $registry): void
    {
        // $schema
        $registry->addRootProperty(
            '$schema',
            StringProperty::new()->description('URL to the JSON schema for this configuration file'),
        );

        // documents
        $registry->addRootProperty(
            'documents',
            ArrayProperty::of(RefProperty::to('document'))
                ->description('List of documents to generate')
                ->minItems(1),
        );

        // prompts
        $registry->addRootProperty(
            'prompts',
            ArrayProperty::of(RefProperty::to('prompt'))
                ->description('List of prompts to be registered'),
        );

        // tools
        $registry->addRootProperty(
            'tools',
            ArrayProperty::of(RefProperty::to('tool'))
                ->description('List of custom tools to be registered for execution'),
        );

        // import
        $registry->addRootProperty(
            'import',
            ArrayProperty::of(
                OneOf::create(
                    StringProperty::new()->description('Path to configuration file (relative to this file) - shorthand format'),
                    RefProperty::to('importConfig'),
                ),
            )->description('List of external configuration files to import'),
        );

        // variables
        $registry->addRootProperty(
            'variables',
            ObjectProperty::new()
                ->description('Custom variables to use throughout the configuration')
                ->additionalProperties(
                    OneOf::create(
                        StringProperty::new(),
                        NumberProperty::new(),
                        BooleanProperty::new(),
                    ),
                ),
        );

        // exclude
        $registry->addRootProperty(
            'exclude',
            ObjectProperty::new()
                ->description('Global exclusion patterns for filtering files from being included in documents')
                ->property('patterns', ArrayProperty::strings()
                    ->description('Glob patterns to exclude files globally (e.g., \'**/.env*\', \'**/*.pem\')'))
                ->property('paths', ArrayProperty::strings()
                    ->description('Specific paths to exclude globally (directories or files)')),
        );

        // settings
        $registry->addRootProperty(
            'settings',
            RefProperty::to('settings'),
        );

        // rag
        $registry->addRootProperty(
            'rag',
            RefProperty::to('ragConfig'),
        );

        // Root oneOf - at least one main section required
        $registry->addRootOneOf(['required' => ['documents']]);
        $registry->addRootOneOf(['required' => ['prompts']]);
        $registry->addRootOneOf(['required' => ['tools']]);
    }

    private function registerSourcePaths(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('sourcePaths')) {
            return;
        }

        $registry->register(OneOfDefinition::create(
            'sourcePaths',
            StringProperty::new()->description('Path to file or directory'),
            ArrayProperty::strings()->description('List of paths to files or directories'),
        ));
    }

    private function registerPatternConstraint(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('patternConstraint')) {
            return;
        }

        $registry->register(OneOfDefinition::create(
            'patternConstraint',
            StringProperty::new()->description('Pattern constraint'),
            ArrayProperty::strings()->description('List of pattern constraints'),
        ));
    }

    private function registerTreeViewConfig(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('treeViewConfig')) {
            return;
        }

        $registry->define('treeViewConfig')
            ->property('enabled', BooleanProperty::new()
                ->description('Whether to show the tree view')
                ->default(true))
            ->property('showSize', BooleanProperty::new()
                ->description('Include file/directory sizes in the tree')
                ->default(false))
            ->property('showLastModified', BooleanProperty::new()
                ->description('Include last modified dates in the tree')
                ->default(false))
            ->property('showCharCount', BooleanProperty::new()
                ->description('Include character counts in the tree')
                ->default(false))
            ->property('includeFiles', BooleanProperty::new()
                ->description('Whether to include files in the tree or only directories')
                ->default(true))
            ->property('maxDepth', IntegerProperty::new()
                ->description('Maximum depth of the tree to display (0 for unlimited)')
                ->minimum(0)
                ->default(0))
            ->property('dirContext', ObjectProperty::new()
                ->description('Optional context/descriptions for specific directories')
                ->additionalProperties(StringProperty::new()));
    }

    private function registerModifiers(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('modifiers')) {
            return;
        }

        $registry->define('modifiers')
            ->description('List of content modifiers to apply')
            ->property('type', StringProperty::new()->enum(['array']))
            ->property('items', OneOf::create(
                StringProperty::new()->description('Modifier identifier or alias'),
                ObjectProperty::new()
                    ->required('name')
                    ->property('name', StringProperty::new()
                        ->description('Modifier identifier'))
                    ->property('options', ObjectProperty::new()
                        ->description('Modifier options')),
            ));
    }

    private function registerInputSchema(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('inputSchema')) {
            return;
        }

        $registry->define('inputSchema')
            ->description('JSON Schema defining arguments that can be provided to tools or other components')
            ->required('properties')
            ->property('type', StringProperty::new()
                ->enum(['object'])
                ->default('object')
                ->description('Schema type'))
            ->property('properties', ObjectProperty::new()
                ->description('Properties that define the available arguments')
                ->additionalProperties(ObjectProperty::new()
                    ->required('type')
                    ->property('type', StringProperty::new()
                        ->enum(['string', 'number', 'boolean', 'array', 'object'])
                        ->description('Data type of the argument'))
                    ->property('description', StringProperty::new()
                        ->description('Human-readable description of the argument'))))
            ->property('required', ArrayProperty::strings()
                ->description('List of arguments that must be provided'));
    }

    private function registerDocument(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('document')) {
            return;
        }

        $registry->define('document')
            ->required('description', 'outputPath', 'sources')
            ->property('description', StringProperty::new()
                ->description('Human-readable description of the document'))
            ->property('outputPath', StringProperty::new()
                ->description('Path where the document will be saved')
                ->pattern('^[\\w\\-./]+\\.[\\w]+$'))
            ->property('overwrite', BooleanProperty::new()
                ->description('Whether to overwrite existing files')
                ->default(true))
            ->property('sources', ArrayProperty::of(RefProperty::to('source'))
                ->description('List of content sources for this document'))
            ->property('modifiers', RefProperty::to('modifiers'))
            ->property('tags', ArrayProperty::strings()
                ->description('List of tags for a document'));
    }

    private function registerSource(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('source')) {
            return;
        }

        $registry->define('source')
            ->required('type')
            ->property('type', StringProperty::new()
                ->enum(['file', 'url', 'text', 'github', 'gitlab', 'git_diff', 'tree', 'mcp', 'composer', 'docs'])
                ->description('Type of content source'))
            ->property('description', StringProperty::new()
                ->description('Human-readable description of the source'))
            ->property('modifiers', RefProperty::to('modifiers'))
            ->property('tags', ArrayProperty::strings()
                ->description('List of tags for this source'));
    }

    private function registerImportConfig(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('importConfig')) {
            return;
        }

        $registry->define('importConfig')
            ->required('type')
            ->property('type', StringProperty::new()
                ->enum(['local', 'url'])
                ->description('Type of import source'))
            ->property('description', StringProperty::new()
                ->description('Human-readable description of the import'))
            ->property('pathPrefix', StringProperty::new()
                ->description('Path prefix to apply to all source paths in the imported configuration'))
            ->property('filter', RefProperty::to('importFilter'));

        // importFilter definition
        if (!$registry->has('importFilter')) {
            $registry->define('importFilter')
                ->description('Filter configuration for selective import of prompts')
                ->property('ids', ArrayProperty::strings()
                    ->description('List of prompt IDs to import'))
                ->property('tags', ObjectProperty::new()
                    ->description('Tag-based filtering configuration')
                    ->property('include', ArrayProperty::strings()
                        ->description('Tags that prompts must have (based on match strategy)'))
                    ->property('exclude', ArrayProperty::strings()
                        ->description('Tags that prompts must not have'))
                    ->property('match', StringProperty::new()
                        ->enum(['any', 'all'])
                        ->description('Match strategy: \'any\' = OR logic (default), \'all\' = AND logic')
                        ->default('any')))
                ->property('match', StringProperty::new()
                    ->enum(['any', 'all'])
                    ->description('Overall match strategy between different filter types')
                    ->default('any'));
        }
    }

    private function registerSettings(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('settings')) {
            return;
        }

        $registry->define('settings')
            ->description('Global settings for the context generator')
            ->property('modifiers', ObjectProperty::new()
                ->description('Named modifier configurations that can be referenced by alias'))
            ->property('mcp', ObjectProperty::new()
                ->description('Settings for MCP (Model Context Protocol) integration')
                ->property('servers', ObjectProperty::new()
                    ->description('Pre-defined MCP server configurations')
                    ->additionalProperties(RefProperty::to('mcpServerConfig'))))
            ->property('gitlab', ObjectProperty::new()
                ->description('Settings for GitLab integration')
                ->property('servers', ObjectProperty::new()
                    ->description('Pre-defined GitLab server configurations')
                    ->additionalProperties(RefProperty::to('gitlabServerConfig'))));

        // mcpServerConfig
        if (!$registry->has('mcpServerConfig')) {
            $registry->register(OneOfDefinition::create(
                'mcpServerConfig',
                ObjectProperty::new()
                    ->required('command')
                    ->property('command', StringProperty::new()
                        ->description('Command to execute to start the MCP server'))
                    ->property('args', ArrayProperty::strings()
                        ->description('Command arguments'))
                    ->property('env', ObjectProperty::new()
                        ->description('Environment variables for the command')
                        ->additionalProperties(StringProperty::new())),
                ObjectProperty::new()
                    ->required('url')
                    ->property('url', StringProperty::new()
                        ->description('URL of the MCP server')
                        ->format('uri'))
                    ->property('headers', ObjectProperty::new()
                        ->description('HTTP headers to send with requests')
                        ->additionalProperties(StringProperty::new())),
            ));
        }

        // gitlabServerConfig placeholder (will be expanded by GitlabSourceBootloader)
        if (!$registry->has('gitlabServerConfig')) {
            $registry->define('gitlabServerConfig')
                ->property('url', StringProperty::new()
                    ->description('GitLab server URL'))
                ->property('token', StringProperty::new()
                    ->description('GitLab API token'));
        }
    }

    private function registerRagConfig(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('ragConfig')) {
            return;
        }

        $registry->define('ragConfig')
            ->description('RAG (Retrieval-Augmented Generation) knowledge store configuration')
            ->property('enabled', BooleanProperty::new()
                ->description('Enable RAG knowledge store')
                ->default(false))
            ->property('store', ObjectProperty::new()
                ->description('Vector store configuration')
                ->property('driver', StringProperty::new()
                    ->enum(['qdrant', 'memory'])
                    ->default('qdrant')
                    ->description('Store backend driver'))
                ->property('qdrant', ObjectProperty::new()
                    ->description('Qdrant-specific configuration')
                    ->property('endpoint_url', StringProperty::new()
                        ->default('http://localhost:6333')
                        ->description('Qdrant server endpoint URL'))
                    ->property('api_key', StringProperty::new()
                        ->description('Qdrant API key (optional for local instances)'))
                    ->property('collection', StringProperty::new()
                        ->default('ctx_knowledge')
                        ->description('Collection name'))
                    ->property('embeddings_dimension', IntegerProperty::new()
                        ->default(1536)
                        ->description('Vector embeddings dimension'))
                    ->property('embeddings_distance', StringProperty::new()
                        ->enum(['Cosine', 'Euclid', 'Dot'])
                        ->default('Cosine')
                        ->description('Distance metric for similarity search'))))
            ->property('vectorizer', ObjectProperty::new()
                ->description('Embedding/vectorizer configuration')
                ->property('platform', StringProperty::new()
                    ->enum(['openai'])
                    ->default('openai')
                    ->description('Embedding platform'))
                ->property('model', StringProperty::new()
                    ->default('text-embedding-3-small')
                    ->description('Embedding model name'))
                ->property('api_key', StringProperty::new()
                    ->description('API key for the embedding platform (can use environment variable)')))
            ->property('transformer', ObjectProperty::new()
                ->description('Text chunking configuration')
                ->property('chunk_size', IntegerProperty::new()
                    ->default(1000)
                    ->description('Maximum chunk size in characters'))
                ->property('overlap', IntegerProperty::new()
                    ->default(200)
                    ->description('Overlap between chunks in characters')));
    }

    private function registerVisibilityOptions(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('visibilityOptions')) {
            return;
        }

        $registry->define('visibilityOptions')
            ->description('Visibility filter options')
            ->property('type', StringProperty::new()->enum(['array']))
            ->property('items', ObjectProperty::new()
                ->property('type', StringProperty::new()->enum(['string']))
                ->property('enum', ArrayProperty::of(
                    StringProperty::new()->enum(['public', 'protected', 'private']),
                )));
    }

    private function registerTool(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('tool')) {
            return;
        }

        $registry->define('tool')
            ->required('id', 'description')
            ->property('id', StringProperty::new()
                ->description('Unique identifier for the tool'))
            ->property('description', StringProperty::new()
                ->description('Human-readable description of the tool'))
            ->property('type', StringProperty::new()
                ->enum(['run', 'http'])
                ->description('Type of tool (run = command execution, http = HTTP requests)')
                ->default('run'))
            ->property('schema', RefProperty::to('inputSchema')
                ->description('Schema defining arguments that can be provided to the tool'))
            ->property('env', ObjectProperty::new()
                ->description('Environment variables for command executions')
                ->additionalProperties(StringProperty::new()))
            ->property('commands', ArrayProperty::of(RefProperty::to('toolCommand'))
                ->description('List of commands to execute (for \'run\' type tools)'))
            ->property('requests', ArrayProperty::of(RefProperty::to('httpRequest'))
                ->description('List of HTTP requests to execute (for \'http\' type tools)'));

        // toolCommand
        if (!$registry->has('toolCommand')) {
            $registry->define('toolCommand')
                ->required('command')
                ->property('command', StringProperty::new()
                    ->description('Command to execute, may contain {{argument}} placeholders'))
                ->property('cwd', StringProperty::new()
                    ->description('Working directory for command execution'))
                ->property('timeout', IntegerProperty::new()
                    ->description('Timeout in seconds')
                    ->default(30));
        }

        // httpRequest
        if (!$registry->has('httpRequest')) {
            $registry->define('httpRequest')
                ->required('url')
                ->property('url', StringProperty::new()
                    ->description('URL to send the request to, may contain {{argument}} placeholders'))
                ->property('method', StringProperty::new()
                    ->enum(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'])
                    ->description('HTTP method to use')
                    ->default('GET'))
                ->property('headers', ObjectProperty::new()
                    ->description('HTTP headers to send with the request')
                    ->additionalProperties(StringProperty::new()))
                ->property('body', StringProperty::new()
                    ->description('Request body, may contain {{argument}} placeholders'));
        }
    }

    private function registerPrompt(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('prompt')) {
            return;
        }

        $registry->define('prompt')
            ->required('id', 'description')
            ->property('id', StringProperty::new()
                ->description('Unique identifier for the prompt'))
            ->property('description', StringProperty::new()
                ->description('Human-readable description of the prompt'))
            ->property('tags', ArrayProperty::strings()
                ->description('List of tags for categorization'))
            ->property('type', StringProperty::new()
                ->enum(['prompt', 'template'])
                ->description('Type of prompt (regular prompt or template)')
                ->default('prompt'))
            ->property('schema', RefProperty::to('inputSchema')
                ->description('Defines input parameters for this prompt'))
            ->property('messages', ArrayProperty::of(
                ObjectProperty::new()
                    ->required('role', 'content')
                    ->property('role', StringProperty::new()
                        ->enum(['user', 'assistant'])
                        ->description('The role of the message sender'))
                    ->property('content', StringProperty::new()
                        ->description('The content of the message')),
            )->description('List of messages that define the prompt'))
            ->property('extend', ArrayProperty::of(
                ObjectProperty::new()
                    ->required('id')
                    ->property('id', StringProperty::new()
                        ->description('ID of the template to extend'))
                    ->property('arguments', ObjectProperty::new()
                        ->description('Arguments to pass to the template')
                        ->additionalProperties(StringProperty::new())),
            )->description('List of templates to extend'));
    }
}
