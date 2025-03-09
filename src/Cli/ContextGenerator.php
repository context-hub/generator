<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Cli;

use Butschster\ContextGenerator\DocumentCompiler;
use Butschster\ContextGenerator\Fetcher\FileSourceFetcher;
use Butschster\ContextGenerator\Fetcher\HtmlCleaner;
use Butschster\ContextGenerator\Fetcher\PhpFileSourceFetcher;
use Butschster\ContextGenerator\Fetcher\SourceFetcherRegistry;
use Butschster\ContextGenerator\Fetcher\TextSourceFetcher;
use Butschster\ContextGenerator\Fetcher\UrlSourceFetcher;
use Butschster\ContextGenerator\Files;
use Butschster\ContextGenerator\Loader\CompositeDocumentsLoader;
use Butschster\ContextGenerator\Loader\ConfigDocumentsLoader;
use Butschster\ContextGenerator\Loader\JsonConfigDocumentsLoader;
use Butschster\ContextGenerator\Parser\DefaultSourceParser;
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
        private readonly ?UriFactoryInterface $urlFactory = null,
        private readonly string $phpConfigName = 'context.php',
        private readonly string $jsonConfigName = 'context.json',
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $files = new Files();

        $sourceFetcherRegistry = new SourceFetcherRegistry(
            fetchers: [
                new TextSourceFetcher(),
                new PhpFileSourceFetcher(
                    basePath: $this->rootPath,
                ),
                new FileSourceFetcher(
                    basePath: $this->rootPath,
                ),
                new UrlSourceFetcher(
                    httpClient: $this->httpClient,
                    requestFactory: $this->requestFactory,
                    uriFactory: $this->urlFactory,
                    cleaner: new HtmlCleaner(),
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
