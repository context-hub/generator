<?php

declare(strict_types=1);

namespace Tests\Unit\Rag\Config;

use Butschster\ContextGenerator\Rag\Config\ServerConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ServerConfig::class)]
final class ServerConfigTest extends TestCase
{
    #[Test]
    public function it_should_create_instance_with_default_values(): void
    {
        $config = new ServerConfig(name: 'test');

        $this->assertSame('test', $config->name);
        $this->assertSame('qdrant', $config->driver);
        $this->assertSame('http://localhost:6333', $config->endpointUrl);
        $this->assertSame('', $config->apiKey);
        $this->assertSame(1536, $config->embeddingsDimension);
        $this->assertSame('Cosine', $config->embeddingsDistance);
    }

    #[Test]
    public function it_should_create_instance_with_custom_values(): void
    {
        $config = new ServerConfig(
            name: 'cloud',
            driver: 'qdrant',
            endpointUrl: 'https://my-cluster.qdrant.io',
            apiKey: 'secret-key',
            embeddingsDimension: 3072,
            embeddingsDistance: 'Dot',
        );

        $this->assertSame('cloud', $config->name);
        $this->assertSame('qdrant', $config->driver);
        $this->assertSame('https://my-cluster.qdrant.io', $config->endpointUrl);
        $this->assertSame('secret-key', $config->apiKey);
        $this->assertSame(3072, $config->embeddingsDimension);
        $this->assertSame('Dot', $config->embeddingsDistance);
    }

    #[Test]
    public function it_should_create_instance_from_array_with_defaults(): void
    {
        $config = ServerConfig::fromArray('default', []);

        $this->assertSame('default', $config->name);
        $this->assertSame('qdrant', $config->driver);
        $this->assertSame('http://localhost:6333', $config->endpointUrl);
        $this->assertSame('', $config->apiKey);
        $this->assertSame(1536, $config->embeddingsDimension);
        $this->assertSame('Cosine', $config->embeddingsDistance);
    }

    #[Test]
    public function it_should_create_instance_from_array_with_all_values(): void
    {
        $config = ServerConfig::fromArray('production', [
            'driver' => 'qdrant',
            'endpoint_url' => 'https://prod.qdrant.io',
            'api_key' => 'prod-api-key',
            'embeddings_dimension' => 2048,
            'embeddings_distance' => 'Euclid',
        ]);

        $this->assertSame('production', $config->name);
        $this->assertSame('qdrant', $config->driver);
        $this->assertSame('https://prod.qdrant.io', $config->endpointUrl);
        $this->assertSame('prod-api-key', $config->apiKey);
        $this->assertSame(2048, $config->embeddingsDimension);
        $this->assertSame('Euclid', $config->embeddingsDistance);
    }

    #[Test]
    public function it_should_handle_partial_array_data(): void
    {
        $config = ServerConfig::fromArray('partial', [
            'endpoint_url' => 'http://custom:6333',
        ]);

        $this->assertSame('partial', $config->name);
        $this->assertSame('qdrant', $config->driver);
        $this->assertSame('http://custom:6333', $config->endpointUrl);
        $this->assertSame('', $config->apiKey);
        $this->assertSame(1536, $config->embeddingsDimension);
        $this->assertSame('Cosine', $config->embeddingsDistance);
    }

    #[Test]
    public function it_should_cast_numeric_string_to_integer_for_dimension(): void
    {
        $config = ServerConfig::fromArray('test', [
            'embeddings_dimension' => '3072',
        ]);

        $this->assertSame(3072, $config->embeddingsDimension);
    }
}
