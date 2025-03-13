<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Document\DocumentCompiler;
use Butschster\ContextGenerator\Error\ErrorCollection;
use Butschster\ContextGenerator\Fetcher\SourceFetcherRegistry;
use Butschster\ContextGenerator\Lib\Files;
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
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'generate',
    description: 'Generate context files from configuration',
)]
final class GenerateCommand extends Command
{
    public function __construct(
        private readonly string $rootPath,
        private readonly string $outputPath,
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
        private readonly ?UriFactoryInterface $uriFactory = null,
        private readonly string $phpConfigName = 'context.php',
        private readonly string $jsonConfigName = 'context.json',
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputStyle = new SymfonyStyle($input, $output);

        $files = new Files();
        $modifiers = new SourceModifierRegistry();
        $modifiers->register(
            new PhpSignature(),
            new ContextSanitizerModifier(),
            new PhpContentFilter(),
            new AstDocTransformer(),
        );

        // Create GitHub-related components
        $githubToken = \getenv('GITHUB_TOKEN') ?: null;
        $githubFinder = new GithubFinder(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            githubToken: $githubToken,
        );

        $sourceFetcherRegistry = new SourceFetcherRegistry(
            fetchers: [
                new TextSourceFetcher(),
                new FileSourceFetcher(
                    basePath: $this->rootPath,
                    modifiers: $modifiers,
                ),
                new UrlSourceFetcher(
                    httpClient: $this->httpClient,
                    requestFactory: $this->requestFactory,
                    uriFactory: $this->uriFactory,
                ),
                new GithubSourceFetcher(
                    finder: $githubFinder,
                    modifiers: $modifiers,
                ),
                new CommitDiffSourceFetcher(
                    modifiers: $modifiers,
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
        );

        $loader = new CompositeDocumentsLoader(
            new ConfigDocumentsLoader(
                configPath: $this->rootPath . '/' . $this->phpConfigName,
            ),
            new JsonConfigDocumentsLoader(
                files: $files,
                configPath: $this->rootPath . '/' . $this->jsonConfigName,
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
