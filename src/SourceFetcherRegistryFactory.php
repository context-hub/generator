<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator;

use Butschster\ContextGenerator\Fetcher\SourceFetcherRegistry;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
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
use Psr\Log\LoggerInterface;

final readonly class SourceFetcherRegistryFactory
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ContentBuilderFactory $contentBuilderFactory,
        private VariableResolverFactory $variableResolverFactory,
        private GithubClientFactory $githubClientFactory,
    ) {}

    public function create(
        string $rootPath,
        LoggerInterface $logger,
        ?string $githubToken = null,
        ?string $envFilePath = null,
        ?string $envFileName = null,
    ): SourceFetcherRegistry {
        $variableResolver = $this->variableResolverFactory->create(
            logger: $logger,
            envFilePath: $envFilePath,
            envFileName: $envFileName,
        );

        $githubClient = $this->githubClientFactory->create($githubToken);

        return new SourceFetcherRegistry(
            fetchers: [
                new TextSourceFetcher(
                    builderFactory: $this->contentBuilderFactory,
                    variableResolver: $variableResolver,
                    logger: $logger->withPrefix('text-source'),
                ),
                new FileSourceFetcher(
                    basePath: $rootPath,
                    builderFactory: $this->contentBuilderFactory,
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
                    basePath: $rootPath,
                    builderFactory: $this->contentBuilderFactory,
                    variableResolver: $variableResolver,
                    logger: $logger->withPrefix('composer-source'),
                ),
                new UrlSourceFetcher(
                    httpClient: $this->httpClient,
                    variableResolver: $variableResolver,
                    builderFactory: $this->contentBuilderFactory,
                    logger: $logger->withPrefix('url-source'),
                ),
                new GithubSourceFetcher(
                    finder: new GithubFinder(
                        githubClient: $githubClient,
                        variableResolver: $variableResolver,
                    ),
                    builderFactory: $this->contentBuilderFactory,
                    logger: $logger->withPrefix('github-source'),
                ),
                new CommitDiffSourceFetcher(
                    builderFactory: $this->contentBuilderFactory,
                    logger: $logger->withPrefix('commit-diff-source'),
                ),
                new TreeSourceFetcher(
                    basePath: $rootPath,
                    builderFactory: $this->contentBuilderFactory,
                    logger: $logger->withPrefix('tree-source'),
                ),
            ],
        );
    }
}
