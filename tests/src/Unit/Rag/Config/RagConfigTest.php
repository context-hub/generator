<?php

declare(strict_types=1);

namespace Tests\Unit\Rag\Config;

use Butschster\ContextGenerator\Rag\Config\RagConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(RagConfig::class)]
final class RagConfigTest extends TestCase
{
    #[Test]
    public function it_should_create_empty_instance_with_defaults(): void
    {
        $config = new RagConfig();

        $this->assertFalse($config->enabled);
        $this->assertEmpty($config->servers);
        $this->assertEmpty($config->collections);
        $this->assertNull($config->store);
        $this->assertFalse($config->isLegacyFormat());
    }

    #[Test]
    public function it_should_parse_legacy_format_with_store_key(): void
    {
        $config = RagConfig::fromArray([
            'enabled' => true,
            'store' => [
                'driver' => 'qdrant',
                'qdrant' => [
                    'endpoint_url' => 'http://localhost:6333',
                    'api_key' => 'test-key',
                    'collection' => 'my_collection',
                    'embeddings_dimension' => 1536,
                    'embeddings_distance' => 'Cosine',
                ],
            ],
            'vectorizer' => [
                'platform' => 'openai',
                'model' => 'text-embedding-3-small',
                'api_key' => 'openai-key',
            ],
            'transformer' => [
                'chunk_size' => 1000,
                'overlap' => 200,
            ],
        ]);

        $this->assertTrue($config->enabled);
        $this->assertTrue($config->isLegacyFormat());

        // Legacy store should be preserved
        $this->assertNotNull($config->store);
        $this->assertSame('my_collection', $config->store->collection);

        // Should have converted to new format internally
        $this->assertArrayHasKey('default', $config->servers);
        $this->assertArrayHasKey('default', $config->collections);

        $server = $config->getServer('default');
        $this->assertSame('qdrant', $server->driver);
        $this->assertSame('http://localhost:6333', $server->endpointUrl);
        $this->assertSame('test-key', $server->apiKey);

        $collection = $config->getCollection('default');
        $this->assertSame('default', $collection->server);
        $this->assertSame('my_collection', $collection->collection);
    }

    #[Test]
    public function it_should_parse_new_format_with_servers_and_collections(): void
    {
        $config = RagConfig::fromArray([
            'enabled' => true,
            'servers' => [
                'local' => [
                    'driver' => 'qdrant',
                    'endpoint_url' => 'http://localhost:6333',
                ],
                'cloud' => [
                    'driver' => 'qdrant',
                    'endpoint_url' => 'https://my-cluster.qdrant.io',
                    'api_key' => 'cloud-key',
                ],
            ],
            'collections' => [
                'docs' => [
                    'server' => 'local',
                    'collection' => 'project_docs',
                    'description' => 'Project documentation',
                ],
                'architecture' => [
                    'server' => 'cloud',
                    'collection' => 'arch_decisions',
                    'description' => 'Architecture decisions',
                    'transformer' => [
                        'chunk_size' => 2000,
                        'overlap' => 400,
                    ],
                ],
            ],
            'vectorizer' => [
                'platform' => 'openai',
                'model' => 'text-embedding-3-large',
            ],
            'transformer' => [
                'chunk_size' => 1000,
                'overlap' => 200,
            ],
        ]);

        $this->assertTrue($config->enabled);
        $this->assertFalse($config->isLegacyFormat());
        $this->assertNull($config->store);

        // Check servers
        $this->assertCount(2, $config->servers);
        $this->assertSame(['local', 'cloud'], $config->getServerNames());

        $localServer = $config->getServer('local');
        $this->assertSame('http://localhost:6333', $localServer->endpointUrl);

        $cloudServer = $config->getServer('cloud');
        $this->assertSame('https://my-cluster.qdrant.io', $cloudServer->endpointUrl);
        $this->assertSame('cloud-key', $cloudServer->apiKey);

        // Check collections
        $this->assertCount(2, $config->collections);
        $this->assertSame(['docs', 'architecture'], $config->getCollectionNames());

        $docsCollection = $config->getCollection('docs');
        $this->assertSame('local', $docsCollection->server);
        $this->assertSame('project_docs', $docsCollection->collection);
        $this->assertSame('Project documentation', $docsCollection->description);

        $archCollection = $config->getCollection('architecture');
        $this->assertSame('cloud', $archCollection->server);
        $this->assertNotNull($archCollection->transformer);
        $this->assertSame(2000, $archCollection->transformer->chunkSize);
    }

    #[Test]
    public function it_should_enable_rag_when_servers_collections_defined_without_enabled_flag(): void
    {
        $config = RagConfig::fromArray([
            'servers' => [
                'default' => ['endpoint_url' => 'http://localhost:6333'],
            ],
            'collections' => [
                'docs' => ['server' => 'default', 'collection' => 'docs'],
            ],
        ]);

        $this->assertTrue($config->enabled);
    }

    #[Test]
    public function it_should_get_server_for_collection(): void
    {
        $config = RagConfig::fromArray([
            'servers' => [
                'production' => [
                    'endpoint_url' => 'https://prod.qdrant.io',
                    'api_key' => 'prod-key',
                ],
            ],
            'collections' => [
                'knowledge' => [
                    'server' => 'production',
                    'collection' => 'prod_knowledge',
                ],
            ],
        ]);

        $server = $config->getServerForCollection('knowledge');

        $this->assertSame('production', $server->name);
        $this->assertSame('https://prod.qdrant.io', $server->endpointUrl);
    }

    #[Test]
    public function it_should_throw_exception_for_unknown_server(): void
    {
        $config = new RagConfig();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Server "unknown" not found');

        $config->getServer('unknown');
    }

    #[Test]
    public function it_should_throw_exception_for_unknown_collection(): void
    {
        $config = new RagConfig();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Collection "unknown" not found');

        $config->getCollection('unknown');
    }

    #[Test]
    public function it_should_check_if_collection_exists(): void
    {
        $config = RagConfig::fromArray([
            'collections' => [
                'docs' => ['server' => 'default', 'collection' => 'docs'],
            ],
        ]);

        $this->assertTrue($config->hasCollection('docs'));
        $this->assertFalse($config->hasCollection('unknown'));
    }

    #[Test]
    public function it_should_parse_vectorizer_config(): void
    {
        $config = RagConfig::fromArray([
            'vectorizer' => [
                'platform' => 'openai',
                'model' => 'text-embedding-3-large',
                'api_key' => 'my-api-key',
            ],
        ]);

        $this->assertSame('openai', $config->vectorizer->platform);
        $this->assertSame('text-embedding-3-large', $config->vectorizer->model);
        $this->assertSame('my-api-key', $config->vectorizer->apiKey);
    }

    #[Test]
    public function it_should_parse_transformer_config(): void
    {
        $config = RagConfig::fromArray([
            'transformer' => [
                'chunk_size' => 2000,
                'overlap' => 500,
            ],
        ]);

        $this->assertSame(2000, $config->transformer->chunkSize);
        $this->assertSame(500, $config->transformer->overlap);
    }

    #[Test]
    public function it_should_use_default_values_for_empty_array(): void
    {
        $config = RagConfig::fromArray([]);

        $this->assertFalse($config->enabled);
        $this->assertEmpty($config->servers);
        $this->assertEmpty($config->collections);
        $this->assertSame('openai', $config->vectorizer->platform);
        $this->assertSame('text-embedding-3-small', $config->vectorizer->model);
        $this->assertSame(1000, $config->transformer->chunkSize);
        $this->assertSame(200, $config->transformer->overlap);
    }

    #[Test]
    public function it_should_handle_integer_server_names(): void
    {
        $config = RagConfig::fromArray([
            'servers' => [
                0 => ['endpoint_url' => 'http://server0:6333'],
                1 => ['endpoint_url' => 'http://server1:6333'],
            ],
            'collections' => [
                0 => ['server' => '0', 'collection' => 'col0'],
            ],
        ]);

        $this->assertCount(2, $config->servers);
        $this->assertSame(['0', '1'], $config->getServerNames());

        $server = $config->getServer('0');
        $this->assertSame('http://server0:6333', $server->endpointUrl);
    }
}
