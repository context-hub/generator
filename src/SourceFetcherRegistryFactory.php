<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Fetcher\SourceFetcherRegistry;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Source\Composer\Client\FileSystemComposerClient;
use Butschster\ContextGenerator\Source\Composer\ComposerSourceFetcher;
use Butschster\ContextGenerator\Source\Composer\Provider\CompositeComposerProvider;
use Butschster\ContextGenerator\Source\Composer\Provider\LocalComposerProvider;
use Butschster\ContextGenerator\Source\File\FileSourceFetcher;
use Butschster\ContextGenerator\Source\GitDiff\GitDiffSourceFetcher;
use Butschster\ContextGenerator\Source\Github\GithubFinder;
use Butschster\ContextGenerator\Source\Github\GithubSourceFetcher;
use Butschster\ContextGenerator\Source\Text\TextSourceFetcher;
use Butschster\ContextGenerator\Source\Tree\TreeSourceFetcher;
use Butschster\ContextGenerator\Source\Url\UrlSourceFetcher;

final readonly class SourceFetcherRegistryFactory
{
    public function __construct(
        private HasPrefixLoggerInterface $logger,
        private HttpClientInterface $httpClient,
        private ContentBuilderFactory $contentBuilderFactory,
        private VariableResolverFactory $variableResolverFactory,
        private GithubClientFactory $githubClientFactory,
    ) {}

    public function create(
        Directories $dirs,
        ?string $githubToken = null,
    ): SourceFetcherRegistry {
        $variableResolver = $this->variableResolverFactory->create(dirs: $dirs);

        $githubClient = $this->githubClientFactory->create($githubToken);

        return new SourceFetcherRegistry(
            fetchers: [
                new TextSourceFetcher(
                    builderFactory: $this->contentBuilderFactory,
                    variableResolver: $variableResolver,
                    logger: $this->logger->withPrefix('text-source'),
                ),
                new FileSourceFetcher(
                    basePath: $dirs->rootPath,
                    builderFactory: $this->contentBuilderFactory,
                    logger: $this->logger->withPrefix('file-source'),
                ),
                new ComposerSourceFetcher(
                    provider: new CompositeComposerProvider(
                        logger: $this->logger,
                        localProvider: new LocalComposerProvider(
                            client: new FileSystemComposerClient(logger: $this->logger),
                            logger: $this->logger,
                        ),
                    ),
                    basePath: $dirs->rootPath,
                    builderFactory: $this->contentBuilderFactory,
                    variableResolver: $variableResolver,
                    logger: $this->logger->withPrefix('composer-source'),
                ),
                new UrlSourceFetcher(
                    httpClient: $this->httpClient,
                    variableResolver: $variableResolver,
                    builderFactory: $this->contentBuilderFactory,
                    logger: $this->logger->withPrefix('url-source'),
                ),
                new GithubSourceFetcher(
                    finder: new GithubFinder(
                        githubClient: $githubClient,
                        variableResolver: $variableResolver,
                    ),
                    builderFactory: $this->contentBuilderFactory,
                    logger: $this->logger->withPrefix('github-source'),
                ),
                new GitDiffSourceFetcher(
                    builderFactory: $this->contentBuilderFactory,
                    logger: $this->logger->withPrefix('commit-diff-source'),
                ),
                new TreeSourceFetcher(
                    basePath: $dirs->rootPath,
                    builderFactory: $this->contentBuilderFactory,
                    logger: $this->logger->withPrefix('tree-source'),
                ),
            ],
        );
    }
}
