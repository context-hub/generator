<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Cli;

use Butschster\ContextGenerator\DocumentCompiler;
use Butschster\ContextGenerator\Fetcher\FileSourceFetcher;
use Butschster\ContextGenerator\Fetcher\Finder\GithubFinder;
use Butschster\ContextGenerator\Fetcher\Github\GithubContentFetcher;
use Butschster\ContextGenerator\Fetcher\GithubSourceFetcher;
use Butschster\ContextGenerator\Fetcher\SourceFetcherRegistry;
use Butschster\ContextGenerator\Fetcher\TextSourceFetcher;
use Butschster\ContextGenerator\Fetcher\UrlSourceFetcher;
use Butschster\ContextGenerator\Files;
use Butschster\ContextGenerator\Loader\CompositeDocumentsLoader;
use Butschster\ContextGenerator\Loader\ConfigDocumentsLoader;
use Butschster\ContextGenerator\Loader\JsonConfigDocumentsLoader;
use Butschster\ContextGenerator\Modifier\PhpSignature;
use Butschster\ContextGenerator\Parser\DefaultSourceParser;
use Butschster\ContextGenerator\Source\SourceModifierRegistry;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'generate')]
final class ContextGenerator extends Command
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
        $files = new Files();
        $modifiers = new SourceModifierRegistry();
        $modifiers->register(new PhpSignature());

        // Create GitHub-related components
        $githubToken = \getenv('GITHUB_TOKEN') ?: null;
        $githubFinder = new GithubFinder(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            uriFactory: $this->uriFactory,
            githubToken: $githubToken,
        );

        $githubContentFetcher = new GithubContentFetcher(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            uriFactory: $this->uriFactory,
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
                    contentFetcher: $githubContentFetcher,
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
            $output->writeln(\sprintf('Compiling %s', $document->description));
            $compiler->compile($document);
            $output->writeln(\sprintf('Document compiled into %s', $document->outputPath));
            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
