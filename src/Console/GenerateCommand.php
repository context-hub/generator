<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderFactory;
use Butschster\ContextGenerator\ConfigLoader\ConfigurationProvider;
use Butschster\ContextGenerator\ConfigLoader\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\ConfigLoader\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Document\DocumentsParserPlugin;
use Butschster\ContextGenerator\Fetcher\SourceFetcherRegistry;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Content\Renderer\MarkdownRenderer;
use Butschster\ContextGenerator\Lib\GithubClient\GithubClient;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Lib\Logger\LoggerFactory;
use Butschster\ContextGenerator\Lib\Variable\Provider\CompositeVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\DotEnvVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\PredefinedVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Modifier\Alias\AliasesRegistry;
use Butschster\ContextGenerator\Modifier\Alias\ModifierAliasesParserPlugin;
use Butschster\ContextGenerator\Modifier\Alias\ModifierResolver;
use Butschster\ContextGenerator\Modifier\AstDocTransformer;
use Butschster\ContextGenerator\Modifier\ContextSanitizerModifier;
use Butschster\ContextGenerator\Modifier\PhpContentFilter;
use Butschster\ContextGenerator\Modifier\PhpSignature;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\Source\Composer\Client\FileSystemComposerClient;
use Butschster\ContextGenerator\Source\Composer\ComposerSourceFetcher;
use Butschster\ContextGenerator\Source\Composer\Provider\CompositeComposerProvider;
use Butschster\ContextGenerator\Source\Composer\Provider\LocalComposerProvider;
use Butschster\ContextGenerator\Source\File\FileSourceFetcher;
use Butschster\ContextGenerator\Source\GitDiff\CommitDiffSourceFetcher;
use Butschster\ContextGenerator\Source\Github\GithubFinder;
use Butschster\ContextGenerator\Source\Github\GithubSourceFetcher;
use Butschster\ContextGenerator\Source\Text\TextSourceFetcher;
use Butschster\ContextGenerator\Source\Tree\TreeSourceFetcher;
use Butschster\ContextGenerator\Source\Url\UrlSourceFetcher;
use Dotenv\Repository\RepositoryBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'generate',
    description: 'Generate context files from configuration',
    aliases: ['build', 'compile'],
)]
final class GenerateCommand extends Command
{
    public function __construct(
        private readonly string $rootPath,
        private readonly string $outputPath,
        private readonly HttpClientInterface $httpClient,
        private readonly FilesInterface $files,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'github-token',
                't',
                InputOption::VALUE_OPTIONAL,
                'GitHub token for authentication',
                \getenv('GITHUB_TOKEN') ?: null,
            )
            ->addOption(
                'env',
                'e',
                InputOption::VALUE_REQUIRED,
                'Path to .env (like .env.local) file. If not provided, will ignore any .env files',
            )
            ->addOption(
                'inline',
                'i',
                InputOption::VALUE_REQUIRED,
                'Inline JSON configuration string. If provided, file-based configuration will be ignored',
            )
            ->addOption(
                'config-file',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to configuration file (absolute or relative to current directory).',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputStyle = new SymfonyStyle($input, $output);

        // Create a logger specific to this command execution
        $logger = LoggerFactory::create(
            output: $output,
            loggingEnabled: $output->isVerbose() || $output->isDebug() || $output->isVeryVerbose(),
        );

        \assert($logger instanceof HasPrefixLoggerInterface);
        \assert($logger instanceof LoggerInterface);

        $files = $this->files;


        // Get the configuration options
        $inlineConfig = $input->getOption('inline');
        $configPath = $input->getOption('config-file');

        // Determine the effective root path based on config file path
        $effectiveRootPath = $this->rootPath;
        if ($configPath !== null && $inlineConfig === null) {
            // If config path is absolute, use its directory as root
            if (\str_starts_with((string) $configPath, '/')) {
                $configDir = \dirname((string) $configPath);
            } else {
                // If relative, resolve against the original root path
                $fullConfigPath = \rtrim($this->rootPath, '/') . '/' . $configPath;
                $configDir = \rtrim(\is_dir($fullConfigPath) ? $fullConfigPath : \dirname($fullConfigPath));
            }

            if ($files->exists($configDir) && \is_dir($configDir)) {
                $effectiveRootPath = $configDir;
                $logger->info('Updated root path based on config file location', [
                    'original' => $this->rootPath,
                    'effective' => $effectiveRootPath,
                ]);
            } else {
                $logger->warning('Could not determine directory from config file path', [
                    'configPath' => $configPath,
                    'using' => $this->rootPath,
                ]);
            }
        }

        $modifiers = new SourceModifierRegistry();
        $modifiers->register(
            new PhpSignature(),
            new ContextSanitizerModifier(),
            new PhpContentFilter(),
            new AstDocTransformer(),
        );

        $githubToken = $input->getOption('github-token');

        $contentBuilderFactory = new ContentBuilderFactory(
            defaultRenderer: new MarkdownRenderer(),
        );

        // Get the env file path from the command option
        $envFileName = $input->getOption('env') ?? null;
        $envFilePath = $envFileName ? $effectiveRootPath : null;

        $variablesProvider = new CompositeVariableProvider(
            envProvider: new DotEnvVariableProvider(
                repository: RepositoryBuilder::createWithDefaultAdapters()->make(),
                rootPath: $envFilePath,
                envFileName: $envFileName,
            ),
            predefinedProvider: new PredefinedVariableProvider(),
        );

        $variableResolver = new VariableResolver(
            processor: new VariableReplacementProcessor(
                provider: $variablesProvider,
                logger: $logger->withPrefix('variable-resolver'),
            ),
        );

        $sourceFetcherRegistry = new SourceFetcherRegistry(
            fetchers: [
                new TextSourceFetcher(
                    builderFactory: $contentBuilderFactory,
                    variableResolver: $variableResolver,
                    logger: $logger->withPrefix('text-source'),
                ),
                new FileSourceFetcher(
                    basePath: $effectiveRootPath,
                    builderFactory: $contentBuilderFactory,
                    logger: $logger->withPrefix('file-source'),
                ),
                new ComposerSourceFetcher(
                    provider: new CompositeComposerProvider(
                        logger: $logger,
                        localProvider: new LocalComposerProvider(
                            client: new FileSystemComposerClient(logger: $logger),
                            logger: $logger,
                        ),
                    ),
                    basePath: $effectiveRootPath,
                    builderFactory: $contentBuilderFactory,
                    variableResolver: $variableResolver,
                    logger: $logger->withPrefix('composer-source'),
                ),
                new UrlSourceFetcher(
                    httpClient: $this->httpClient,
                    variableResolver: $variableResolver,
                    builderFactory: $contentBuilderFactory,
                    logger: $logger->withPrefix('url-source'),
                ),
                new GithubSourceFetcher(
                    finder: new GithubFinder(
                        githubClient: new GithubClient($this->httpClient, token: $githubToken),
                        variableResolver: $variableResolver,
                    ),
                    builderFactory: $contentBuilderFactory,
                    logger: $logger->withPrefix('github-source'),
                ),
                new CommitDiffSourceFetcher(
                    builderFactory: $contentBuilderFactory,
                    logger: $logger->withPrefix('commit-diff-source'),
                ),
                new TreeSourceFetcher(
                    basePath: $effectiveRootPath,
                    builderFactory: $contentBuilderFactory,
                    logger: $logger->withPrefix('tree-source'),
                ),
            ],
        );

        $compiler = new DocumentCompiler(
            files: $files,
            parser: $sourceFetcherRegistry,
            basePath: $this->outputPath,
            modifierRegistry: $modifiers,
            builderFactory: $contentBuilderFactory,
            logger: $logger->withPrefix('documents'),
        );

        $configProvider = new ConfigurationProvider(
            loaderFactory: new ConfigLoaderFactory(
                files: $this->files,
                rootPath: $effectiveRootPath,
                logger: $logger->withPrefix('config-loader'),
            ),
            files: $this->files,
            rootPath: $this->rootPath,
            logger: $logger->withPrefix('config-provider'),
            parserPlugins: $this->getParserPlugins(),
        );

        try {
            // Get the appropriate loader based on options provided
            if ($inlineConfig !== null) {
                $outputStyle->info('Using inline JSON configuration...');
                $loader = $configProvider->fromString($inlineConfig);
            } elseif ($configPath !== null) {
                $outputStyle->info(\sprintf('Loading configuration from %s...', $configPath));
                $loader = $configProvider->fromPath($configPath);
            } else {
                $outputStyle->info('Loading configuration from default location...');
                $loader = $configProvider->fromDefaultLocation();
            }
        } catch (ConfigLoaderException $e) {
            $logger->error('Failed to load configuration', [
                'error' => $e->getMessage(),
            ]);

            $outputStyle->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        foreach ($loader->load()->getItems() as $document) {
            $outputStyle->info(\sprintf('Compiling %s...', $document->description));

            $compiledDocument = $compiler->compile($document);
            if (!$compiledDocument->errors->hasErrors()) {
                $outputStyle->success(\sprintf('Document compiled into %s', $document->outputPath));
                continue;
            }

            $outputStyle->warning(\sprintf('Document compiled into %s with errors', $document->outputPath));
            $outputStyle->listing(\iterator_to_array($compiledDocument->errors));
        }

        return Command::SUCCESS;
    }

    /**
     * Get parser plugins for the config loader
     *
     * @return array<ConfigParserPluginInterface>
     */
    private function getParserPlugins(): array
    {
        $modifierResolver = new ModifierResolver(
            aliasesRegistry: $aliasesRegistry = new AliasesRegistry(),
        );

        return [
            new ModifierAliasesParserPlugin(
                aliasesRegistry: $aliasesRegistry,
            ),
            new DocumentsParserPlugin(
                modifierResolver: $modifierResolver,
            ),
        ];
    }
}
