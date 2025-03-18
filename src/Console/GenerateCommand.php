<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

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
use Butschster\ContextGenerator\Loader\CompositeDocumentsLoader;
use Butschster\ContextGenerator\Loader\ConfigDocumentsLoader;
use Butschster\ContextGenerator\Loader\ConfigRegistry\ConfigParser;
use Butschster\ContextGenerator\Loader\JsonConfigDocumentsLoader;
use Butschster\ContextGenerator\Modifier\Alias\AliasesRegistry;
use Butschster\ContextGenerator\Modifier\Alias\ModifierAliasesParserPlugin;
use Butschster\ContextGenerator\Modifier\Alias\ModifierResolver;
use Butschster\ContextGenerator\Modifier\AstDocTransformer;
use Butschster\ContextGenerator\Modifier\ContextSanitizerModifier;
use Butschster\ContextGenerator\Modifier\PhpContentFilter;
use Butschster\ContextGenerator\Modifier\PhpSignature;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\Source\File\FileSourceFetcher;
use Butschster\ContextGenerator\Source\GitDiff\CommitDiffSourceFetcher;
use Butschster\ContextGenerator\Source\Github\GithubFinder;
use Butschster\ContextGenerator\Source\Github\GithubSourceFetcher;
use Butschster\ContextGenerator\Source\Text\TextSourceFetcher;
use Butschster\ContextGenerator\Source\Url\UrlSourceFetcher;
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
        private readonly string $phpConfigName = 'context.php',
        private readonly string $jsonConfigName = 'context.json',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to configuration file',
                'context.json',
            )
            ->addOption(
                'github-token',
                't',
                InputOption::VALUE_OPTIONAL,
                'GitHub token for authentication',
                \getenv('GITHUB_TOKEN') ?: null,
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

        $configPath = $input->getOption('config') ?: $this->jsonConfigName;

        if (!\file_exists($configPath)) {
            $outputStyle->error(\sprintf('Configuration file not found: %s', $configPath));
            return Command::FAILURE;
        }

        $files = $this->files;
        $modifiers = new SourceModifierRegistry();
        $modifiers->register(
            new PhpSignature(),
            new ContextSanitizerModifier(),
            new PhpContentFilter(),
            new AstDocTransformer(),
        );

        $githubToken = $input->getOption('github-token');
        $githubClient = new GithubClient($this->httpClient, token: $githubToken);

        // Create GitHub-related components
        $githubFinder = new GithubFinder(
            githubClient: $githubClient,
        );

        $contentBuilderFactory = new ContentBuilderFactory(
            defaultRenderer: new MarkdownRenderer(),
        );

        $sourceFetcherRegistry = new SourceFetcherRegistry(
            fetchers: [
                new TextSourceFetcher(
                    builderFactory: $contentBuilderFactory,
                    logger: $logger->withPrefix('text-source'),
                ),
                new FileSourceFetcher(
                    basePath: $this->rootPath,
                    builderFactory: $contentBuilderFactory,
                    logger: $logger->withPrefix('file-source'),
                ),
                new UrlSourceFetcher(
                    httpClient: $this->httpClient,
                    builderFactory: $contentBuilderFactory,
                    logger: $logger->withPrefix('url-source'),
                ),
                new GithubSourceFetcher(
                    finder: $githubFinder,
                    builderFactory: $contentBuilderFactory,
                    logger: $logger->withPrefix('github-source'),
                ),
                new CommitDiffSourceFetcher(
                    builderFactory: $contentBuilderFactory,
                    logger: $logger->withPrefix('commit-diff-source'),
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

        $outputStyle->info(\sprintf('Loading configuration from %s ...', $this->rootPath . '/' . $configPath));
        $modifierResolver = new ModifierResolver(
            aliasesRegistry: $aliasesRegistry = new AliasesRegistry(),
        );
        $loader = new CompositeDocumentsLoader(
            new ConfigDocumentsLoader(
                configPath: $this->rootPath . '/' . $this->phpConfigName,
            ),
            // todo use factory
            new JsonConfigDocumentsLoader(
                files: $files,
                parser: new ConfigParser(
                    rootPath: $this->rootPath,
                    logger: $logger->withPrefix('parser'),
                    modifierAliasesParser: new ModifierAliasesParserPlugin(
                        aliasesRegistry: $aliasesRegistry,
                    ),
                    documentsParser: new DocumentsParserPlugin(
                        modifierResolver: $modifierResolver,
                    ),
                ),
                configPath: $this->rootPath . '/' . $configPath,
                logger: $logger->withPrefix('json-config'),
            ),
        );

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
}
