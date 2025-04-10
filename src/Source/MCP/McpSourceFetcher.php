<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Source\SourceInterface;
use Butschster\ContextGenerator\Source\MCP\Connection\ConnectionManager;
use Psr\Log\LoggerInterface;

/**
 * Fetcher for MCP sources
 * @implements SourceFetcherInterface<McpSource>
 */
final readonly class McpSourceFetcher implements SourceFetcherInterface
{
    public function __construct(
        private ConnectionManager $connectionManager,
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private VariableResolver $variableResolver = new VariableResolver(),
        #[LoggerPrefix(prefix: 'mcp-source')]
        private ?LoggerInterface $logger = null,
    ) {}

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof McpSource;
        $this->logger?->debug('Checking if source is supported', [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        if (!$source instanceof McpSource) {
            $errorMessage = 'Source must be an instance of McpSource';
            $this->logger?->error($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException($errorMessage);
        }

        $description = $this->variableResolver->resolve($source->getDescription());

        $this->logger?->info('Fetching MCP source content', [
            'description' => $description,
            'server' => $source->serverConfig,
            'operation' => $source->operation,
        ]);

        try {
            // Get or create a client session
            $session = $this->connectionManager->getSession($source->serverConfig);

            // Execute the operation
            $content = $source->operation->execute($session, $this->variableResolver);


            // Create builder
            $builder = $this->builderFactory
                ->create()
                ->addDescription($description);

            // Build content based on operation type
            $source->operation->buildContent($builder, $content);

            // Add separator
            $builder->addSeparator();

            $result = $builder->build();
            $this->logger?->info('MCP source content fetched successfully', [
                'contentLength' => \strlen($result),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $errorMessage = \sprintf('Error fetching MCP source: %s', $e->getMessage());
            $this->logger?->error($errorMessage, [
                'exception' => $e,
                'server' => $source->serverConfig,
                'operation' => $source->operation,
            ]);
            throw new \RuntimeException($errorMessage, 0, $e);
        }
    }
}
