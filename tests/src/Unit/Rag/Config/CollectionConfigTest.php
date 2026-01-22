<?php

declare(strict_types=1);

namespace Tests\Unit\Rag\Config;

use Butschster\ContextGenerator\Rag\Config\CollectionConfig;
use Butschster\ContextGenerator\Rag\Config\ServerConfig;
use Butschster\ContextGenerator\Rag\Config\TransformerConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CollectionConfig::class)]
final class CollectionConfigTest extends TestCase
{
    #[Test]
    public function it_should_create_instance_with_required_values(): void
    {
        $config = new CollectionConfig(
            name: 'docs',
            server: 'default',
            collection: 'project_docs',
        );

        $this->assertSame('docs', $config->name);
        $this->assertSame('default', $config->server);
        $this->assertSame('project_docs', $config->collection);
        $this->assertNull($config->description);
        $this->assertNull($config->embeddingsDimension);
        $this->assertNull($config->embeddingsDistance);
        $this->assertNull($config->transformer);
    }

    #[Test]
    public function it_should_create_instance_with_all_values(): void
    {
        $transformer = new TransformerConfig(chunkSize: 2000, overlap: 400);

        $config = new CollectionConfig(
            name: 'architecture',
            server: 'cloud',
            collection: 'arch_docs',
            description: 'Architecture documentation',
            embeddingsDimension: 3072,
            embeddingsDistance: 'Dot',
            transformer: $transformer,
        );

        $this->assertSame('architecture', $config->name);
        $this->assertSame('cloud', $config->server);
        $this->assertSame('arch_docs', $config->collection);
        $this->assertSame('Architecture documentation', $config->description);
        $this->assertSame(3072, $config->embeddingsDimension);
        $this->assertSame('Dot', $config->embeddingsDistance);
        $this->assertSame($transformer, $config->transformer);
    }

    #[Test]
    public function it_should_create_instance_from_array_with_minimal_data(): void
    {
        $config = CollectionConfig::fromArray('test', [
            'server' => 'default',
            'collection' => 'test_collection',
        ]);

        $this->assertSame('test', $config->name);
        $this->assertSame('default', $config->server);
        $this->assertSame('test_collection', $config->collection);
        $this->assertNull($config->description);
        $this->assertNull($config->transformer);
    }

    #[Test]
    public function it_should_create_instance_from_array_with_all_values(): void
    {
        $config = CollectionConfig::fromArray('docs', [
            'server' => 'production',
            'collection' => 'prod_docs',
            'description' => 'Production documentation',
            'embeddings_dimension' => 2048,
            'embeddings_distance' => 'Euclid',
            'transformer' => [
                'chunk_size' => 1500,
                'overlap' => 300,
            ],
        ]);

        $this->assertSame('docs', $config->name);
        $this->assertSame('production', $config->server);
        $this->assertSame('prod_docs', $config->collection);
        $this->assertSame('Production documentation', $config->description);
        $this->assertSame(2048, $config->embeddingsDimension);
        $this->assertSame('Euclid', $config->embeddingsDistance);
        $this->assertNotNull($config->transformer);
        $this->assertSame(1500, $config->transformer->chunkSize);
        $this->assertSame(300, $config->transformer->overlap);
    }

    #[Test]
    public function it_should_use_name_as_collection_when_not_specified(): void
    {
        $config = CollectionConfig::fromArray('my-collection', [
            'server' => 'default',
        ]);

        $this->assertSame('my-collection', $config->collection);
    }

    #[Test]
    public function it_should_use_default_server_when_not_specified(): void
    {
        $config = CollectionConfig::fromArray('test', [
            'collection' => 'test_col',
        ]);

        $this->assertSame('default', $config->server);
    }

    #[Test]
    public function it_should_return_fallback_transformer_when_no_override(): void
    {
        $config = new CollectionConfig(
            name: 'docs',
            server: 'default',
            collection: 'docs',
        );

        $fallback = new TransformerConfig(chunkSize: 1000, overlap: 200);
        $result = $config->getTransformer($fallback);

        $this->assertSame($fallback, $result);
    }

    #[Test]
    public function it_should_return_collection_transformer_when_specified(): void
    {
        $collectionTransformer = new TransformerConfig(chunkSize: 2000, overlap: 400);

        $config = new CollectionConfig(
            name: 'docs',
            server: 'default',
            collection: 'docs',
            transformer: $collectionTransformer,
        );

        $fallback = new TransformerConfig(chunkSize: 1000, overlap: 200);
        $result = $config->getTransformer($fallback);

        $this->assertSame($collectionTransformer, $result);
        $this->assertNotSame($fallback, $result);
    }

    #[Test]
    public function it_should_get_effective_embeddings_dimension_from_collection(): void
    {
        $server = new ServerConfig(name: 'default', embeddingsDimension: 1536);

        $config = new CollectionConfig(
            name: 'docs',
            server: 'default',
            collection: 'docs',
            embeddingsDimension: 3072,
        );

        $this->assertSame(3072, $config->getEffectiveEmbeddingsDimension($server));
    }

    #[Test]
    public function it_should_get_effective_embeddings_dimension_from_server_when_not_overridden(): void
    {
        $server = new ServerConfig(name: 'default', embeddingsDimension: 1536);

        $config = new CollectionConfig(
            name: 'docs',
            server: 'default',
            collection: 'docs',
        );

        $this->assertSame(1536, $config->getEffectiveEmbeddingsDimension($server));
    }

    #[Test]
    public function it_should_get_effective_embeddings_distance_from_collection(): void
    {
        $server = new ServerConfig(name: 'default', embeddingsDistance: 'Cosine');

        $config = new CollectionConfig(
            name: 'docs',
            server: 'default',
            collection: 'docs',
            embeddingsDistance: 'Dot',
        );

        $this->assertSame('Dot', $config->getEffectiveEmbeddingsDistance($server));
    }

    #[Test]
    public function it_should_get_effective_embeddings_distance_from_server_when_not_overridden(): void
    {
        $server = new ServerConfig(name: 'default', embeddingsDistance: 'Cosine');

        $config = new CollectionConfig(
            name: 'docs',
            server: 'default',
            collection: 'docs',
        );

        $this->assertSame('Cosine', $config->getEffectiveEmbeddingsDistance($server));
    }
}
