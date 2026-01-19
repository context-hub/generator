<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Url;

use Butschster\ContextGenerator\JsonSchema\Property\ArrayProperty;
use Butschster\ContextGenerator\JsonSchema\Property\ObjectProperty;
use Butschster\ContextGenerator\JsonSchema\Property\StringProperty;
use Butschster\ContextGenerator\JsonSchema\SchemaDefinitionRegistry;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientBootloader;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Logger\HasPrefixLoggerInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\FactoryInterface;

final class UrlSourceBootloader extends Bootloader
{
    #[\Override]
    public function defineDependencies(): array
    {
        return [HttpClientBootloader::class];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            UrlSourceFetcher::class => static fn(
                FactoryInterface $factory,
                ContentBuilderFactory $builderFactory,
                HttpClientInterface $httpClient,
                VariableResolver $variables,
                HasPrefixLoggerInterface $logger,
            ): UrlSourceFetcher => $factory->make(UrlSourceFetcher::class, [
                'defaultHeaders' => [
                    'User-Agent' => 'CTX Bot',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
            ]),
        ];
    }

    public function init(
        SourceFetcherBootloader $registry,
        SourceRegistryInterface $sourceRegistry,
        UrlSourceFactory $factory,
        SchemaDefinitionRegistry $schemaRegistry,
    ): void {
        $registry->register(UrlSourceFetcher::class);
        $sourceRegistry->register($factory);
        $this->registerSchema($schemaRegistry);
    }

    private function registerSchema(SchemaDefinitionRegistry $registry): void
    {
        if ($registry->has('urlSource')) {
            return;
        }

        $registry->define('urlSource')
            ->required('urls')
            ->property('urls', ArrayProperty::of(StringProperty::new()->format('uri'))
                ->description('List of URLs to fetch content from')
                ->minItems(1))
            ->property('selector', StringProperty::new()
                ->description('CSS selector to extract specific content (null for full page)'))
            ->property('headers', ObjectProperty::new()
                ->description('Custom HTTP headers to send with requests')
                ->additionalProperties(StringProperty::new()));
    }
}
