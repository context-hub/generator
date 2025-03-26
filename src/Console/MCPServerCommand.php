<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\ConfigurationProviderConfig;
use Butschster\ContextGenerator\ConfigurationProviderFactory;
use Butschster\ContextGenerator\Document\Compiler\Error\ErrorCollection;
use Butschster\ContextGenerator\DocumentCompilerFactory;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Lib\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Lib\Logger\LoggerFactory;
use Mcp\Types\CallToolRequestParams;
use Mcp\Types\CallToolResult;
use Mcp\Types\GetPromptRequestParams;
use Mcp\Types\GetPromptResult;
use Mcp\Types\ListPromptsResult;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\ListToolsResult;
use Mcp\Types\Prompt;
use Mcp\Types\PromptMessage;
use Mcp\Types\ReadResourceRequestParams;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\Resource;
use Mcp\Types\Role;
use Mcp\Types\TextContent;
use Mcp\Types\TextResourceContents;
use Mcp\Types\Tool;
use Mcp\Types\ToolInputProperties;
use Mcp\Types\ToolInputSchema;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Mcp\Server\Server;
use Mcp\Server\ServerRunner;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'server',
    description: 'Start the context generator MCP server',
)]
final class MCPServerCommand extends Command
{
    use DetermineRootPath;

    private LoggerInterface $logger;

    public function __construct(
        private readonly string $rootPath,
        private readonly string $jsonSchemaPath,
        private readonly FilesInterface $files,
        private readonly DocumentCompilerFactory $documentCompilerFactory,
        private readonly ConfigurationProviderFactory $configurationProviderFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'env',
                'e',
                InputOption::VALUE_REQUIRED,
                'Path to .env (like .env.local) file. If not provided, will ignore any .env files',
            )
            ->addOption(
                'config-file',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to configuration file (absolute or relative to current directory).',
            );
    }

    /**
     * @param SymfonyStyle $output
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger = LoggerFactory::create(
            output: $output,
            loggingEnabled: $output->isVerbose() || $output->isDebug() || $output->isVeryVerbose(),
        );

        \assert($this->logger instanceof HasPrefixLoggerInterface);
        \assert($this->logger instanceof LoggerInterface);

        $this->logger->info('Starting MCP server...');

        $envFileName = $input->getOption('env') ?? null;
        $configPath = $input->getOption('config-file');

        // Determine the effective root path based on config file path
        $effectiveRootPath = $this->determineRootPath($configPath, null);
        // Determine the env file path
        $envFilePath = $envFileName ? $effectiveRootPath : null;

        $this->logger->info(\sprintf('Using root path: %s', $effectiveRootPath));


        // Create the document compiler
        $compiler = $this->documentCompilerFactory->create(
            rootPath: $effectiveRootPath,
            outputPath: $effectiveRootPath,
            logger: $this->logger,
            envFilePath: $envFilePath,
            envFileName: $envFileName,
        );

        // Create configuration provider
        $configProvider = $this->configurationProviderFactory->create(
            new ConfigurationProviderConfig(
                rootPath: $effectiveRootPath,
                files: $this->files,
                logger: $this->logger,
                configPath: $configPath,
            ),
        );

        try {
            // Get the appropriate loader based on options provided
            if (!\is_dir($effectiveRootPath)) {
                $this->logger->info(
                    'Loading configuration from provided path...',
                    [
                        'path' => $effectiveRootPath,
                    ],
                );
                $loader = $configProvider->fromPath($configPath);
            } else {
                $this->logger->info('Using default configuration location...');
                $loader = $configProvider->fromDefaultLocation();
            }
        } catch (ConfigLoaderException $e) {
            $this->logger->error('Failed to load configuration', [
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }

        $server = new Server(name: 'ContextGenerator', logger: $this->logger);

        $server->registerHandler('prompts/list', static function ($params) {
            return new ListPromptsResult([
                new Prompt(
                    name: 'available-context',
                    description: 'Provides a list of available contexts',
                ),
                new Prompt(
                    name: 'project-structure',
                    description: 'Tries to guess the project structure',
                ),
            ]);
        });

        $server->registerHandler(
            'prompts/get',
            static function (GetPromptRequestParams $params) {
                $name = $params->name;
                $arguments = $params->arguments;

                if ($name === 'available-context') {
                    return new GetPromptResult(
                        messages: [
                            new PromptMessage(
                                role: Role::USER,
                                content: new TextContent(
                                    text: "Provide list of available contexts in JSON format",
                                ),
                            ),
                        ],
                    );
                }

                if ($name === 'project-structure') {
                    return new GetPromptResult(
                        messages: [
                            new PromptMessage(
                                role: Role::USER,
                                content: new TextContent(
                                    text: "Look at available contexts and try to find the project structure. If there is no context for structure. Request structure from context using JSON schema. Provide the result in JSON format",
                                ),
                            ),
                        ],
                    );
                }

                return new GetPromptResult(
                    messages: [
                        new PromptMessage(
                            role: Role::USER,
                            content: new TextContent(
                                text: "Error: Prompt not found",
                            ),
                        ),
                    ],
                );
            },
        );

        $server->registerHandler(
            'resources/read',
            function (ReadResourceRequestParams $params) use ($loader, $compiler) {
                $documents = $loader->load();
                $list = [];

                [$type, $uri] = \explode('://', $params->uri, 2);

                if ($type !== 'docs') {
                    return new ReadResourceResult($list);
                }

                if ($uri === 'context/documents') {
                    foreach ($documents->getItems() as $document) {
                        $list[] = new TextResourceContents(
                            \json_encode($document),
                            uri: 'docs://context/document/' . $document->outputPath,
                            mimeType: 'application/json',
                        );
                    }
                }

                if ($uri === 'context/json-schema') {
                    return new ReadResourceResult([
                        new TextResourceContents(
                            text: (string) \json_encode($this->jsonSchema()),
                            uri: 'docs://context/json-schema',
                            mimeType: 'application/json',
                        ),
                    ]);
                }

                if (\str_starts_with($uri, 'context/document')) {
                    $path = \str_replace('context/document/', '', $uri);
                    foreach ($documents->getItems() as $document) {
                        if ($document->outputPath === $path) {
                            $list[] = new TextResourceContents(
                                text: (string) $compiler->buildContent(new ErrorCollection(), $document)->content,
                                uri: 'docs://context/document/' . $document->outputPath,
                                mimeType: 'text/markdown',
                            );
                        }
                    }
                }

                return new ReadResourceResult($list);
            },
        );

        $server->registerHandler('resources/list', static function ($params) use ($loader) {
            $documents = $loader->load();
            $resources = [
                new Resource(
                    name: 'List of available contexts',
                    uri: 'docs://context/documents',
                    description: 'Returns a list of available contexts of project in document format',
                    mimeType: 'text/markdown',
                ),
                new Resource(
                    name: 'Json Schema of context generator',
                    uri: 'docs://context/json-schema',
                    description: 'Returns a simplified JSON schema of the context generator',
                    mimeType: 'application/json',
                ),
            ];

            foreach ($documents->getItems() as $document) {
                $resources[] = new Resource(
                    name: $document->outputPath,
                    uri: 'docs://context/document/' . $document->outputPath,
                    description: \sprintf(
                        '%s. Tags: %s',
                        $document->description,
                        \implode(', ', $document->getTags()),
                    ),
                    mimeType: 'application/markdown',
                );
            }

            return new ListResourcesResult(resources: $resources);
        });

        $server->registerHandler(
            'tools/call',
            static function (
                CallToolRequestParams $params,
            ) use ($configProvider, $loader, $compiler) {
                $documents = $loader->load();
                $name = $params->name;

                if ($name === 'context-request') {
                    $loader = $configProvider->fromString($params->arguments['json'] ?? '');
                    $documents = $loader->load()->getItems();
                    $compiledDocuments = [];
                    foreach ($documents as $document) {
                        $compiledDocuments[$document->outputPath] = new TextContent(
                            (string) $compiler->buildContent(new ErrorCollection(), $document)->content,
                        );
                    }

                    return new CallToolResult($compiledDocuments);
                }

                if ($name === 'context') {
                    return new CallToolResult([
                        new TextContent(\json_encode($documents)),
                    ]);
                }

                if ($name === 'context-get') {
                    $path = $params->arguments['path'] ?? '';
                    foreach ($documents->getItems() as $document) {
                        if ($document->outputPath === $path) {
                            $compiledDocuments[] = new TextContent(
                                (string) $compiler->buildContent(new ErrorCollection(), $document)->content,
                            );
                        }
                    }

                    return new CallToolResult($compiledDocuments);
                }

                return new CallToolResult([new TextContent('Error: Tool not found')]);
            },
        );

        $server->registerHandler('tools/list', static function ($params) {
            $tools = [
                new Tool(
                    name: 'context-request',
                    inputSchema: new ToolInputSchema(
                        properties: ToolInputProperties::fromArray([
                            'json' => [
                                'type' => 'string',
                                'description' => 'JSON string to process',
                            ],
                        ]),
                        required: ['json'],
                    ),
                    description: 'Requests the context from server using JSON schema',
                ),
                new Tool(
                    name: 'context-get',
                    inputSchema: new ToolInputSchema(
                        properties: ToolInputProperties::fromArray([
                            'path' => [
                                'type' => 'string',
                                'description' => 'Path to the document',
                            ],
                        ]),
                        required: ['document'],
                    ),
                    description: 'Requests a document by path',
                ),
                new Tool(
                    name: 'context',
                    inputSchema: new ToolInputSchema(),
                    description: 'Provide list of available contexts',
                ),
            ];

            return new ListToolsResult($tools);
        });

        // Create initialization options and run server
        $initOptions = $server->createInitializationOptions();

        $runner = new ServerRunner($server, $initOptions);
        $runner->run();

        return self::SUCCESS;
    }

    private function jsonSchema(): array
    {
        $schema = \json_decode(
            $this->files->read($this->jsonSchemaPath),
            associative: true,
        );

        unset(
            $schema['properties']['import'],
            $schema['properties']['settings'],
            $schema['definitions']['document']['properties']['modifiers'],
            $schema['definitions']['source']['properties']['modifiers'],
            $schema['definitions']['urlSource'],
            $schema['definitions']['githubSource'],
            $schema['definitions']['textSource'],
            $schema['definitions']['composerSource'],
            $schema['definitions']['php-content-filter'],
            $schema['definitions']['php-docs'],
            $schema['definitions']['sanitizer'],
            $schema['definitions']['modifiers'],
            $schema['definitions']['visibilityOptions'],
        );

        $schema['definitions']['source']['properties']['type']['enum'] = ['file', 'tree', 'git_diff'];

        return $schema;
    }
}
