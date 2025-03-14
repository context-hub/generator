<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Document\DocumentCompiler;
use Butschster\ContextGenerator\Error\ErrorCollection;
use Butschster\ContextGenerator\Fetcher\SourceFetcherRegistry;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Content\Renderer\MarkdownRenderer;
use Butschster\ContextGenerator\Lib\GithubClient\GithubClient;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Loader\CompositeDocumentsLoader;
use Butschster\ContextGenerator\Loader\ConfigDocumentsLoader;
use Butschster\ContextGenerator\Loader\JsonConfigDocumentsLoader;
use Butschster\ContextGenerator\Modifier\AstDocTransformer;
use Butschster\ContextGenerator\Modifier\ContextSanitizerModifier;
use Butschster\ContextGenerator\Modifier\PhpContentFilter;
use Butschster\ContextGenerator\Modifier\PhpSignature;
use Butschster\ContextGenerator\Modifier\SourceModifierRegistry;
use Butschster\ContextGenerator\Parser\DefaultSourceParser;
use Butschster\ContextGenerator\Source\File\FileSourceFetcher;
use Butschster\ContextGenerator\Source\GitDiff\CommitDiffSourceFetcher;
use Butschster\ContextGenerator\Source\Github\GithubFinder;
use Butschster\ContextGenerator\Source\Github\GithubSourceFetcher;
use Butschster\ContextGenerator\Source\Text\TextSourceFetcher;
use Butschster\ContextGenerator\Source\Url\UrlSourceFetcher;
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
                ),
                new FileSourceFetcher(
                    basePath: $this->rootPath,
                    modifiers: $modifiers,
                    builderFactory: $contentBuilderFactory,
                ),
                new UrlSourceFetcher(
                    httpClient: $this->httpClient,
                    builderFactory: $contentBuilderFactory,
                ),
                new GithubSourceFetcher(
                    finder: $githubFinder,
                    modifiers: $modifiers,
                    builderFactory: $contentBuilderFactory,
                ),
                new CommitDiffSourceFetcher(
                    modifiers: $modifiers,
                    builderFactory: $contentBuilderFactory,
                ),
            ],
        );

        $sourceParser = new DefaultSourceParser(
            fetcherRegistry: $sourceFetcherRegistry,
        );

        $compiler = new DocumentCompiler(
            files: $files,
            parser: $sourceParser,
            basePath: $this->outputPath,
            builderFactory: $contentBuilderFactory,
        );

        $outputStyle->info(\sprintf('Loading configuration from %s ...', $this->rootPath . '/' . $configPath));
        $loader = new CompositeDocumentsLoader(
            new ConfigDocumentsLoader(
                configPath: $this->rootPath . '/' . $this->phpConfigName,
            ),
            new JsonConfigDocumentsLoader(
                files: $files,
                configPath: $this->rootPath . '/' . $configPath,
                rootPath: $this->rootPath,
            ),
        );

        foreach ($loader->load()->getDocuments() as $document) {
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

    private function renderErrors(SymfonyStyle $output, ErrorCollection $errors): void
    {
        foreach ($errors as $error) {
            $output->error($error);
        }
    }
}
