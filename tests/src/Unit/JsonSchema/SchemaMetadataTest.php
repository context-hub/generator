<?php

declare(strict_types=1);

namespace Tests\Unit\JsonSchema;

use Butschster\ContextGenerator\JsonSchema\SchemaMetadata;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SchemaMetadataTest extends TestCase
{
    #[Test]
    public function it_should_have_default_values(): void
    {
        $metadata = new SchemaMetadata();

        $this->assertEquals('http://json-schema.org/draft-07/schema#', $metadata->schema);
        $this->assertEquals('https://ctxllm.com', $metadata->id);
        $this->assertEquals('ContextHub Configuration', $metadata->title);
        $this->assertEquals(['context.json', 'context.yaml'], $metadata->fileMatch);
    }

    #[Test]
    public function it_should_accept_custom_values(): void
    {
        $metadata = new SchemaMetadata(
            title: 'Custom Title',
            description: 'Custom Description',
            fileMatch: ['custom.json'],
        );

        $this->assertEquals('Custom Title', $metadata->title);
        $this->assertEquals('Custom Description', $metadata->description);
        $this->assertEquals(['custom.json'], $metadata->fileMatch);
    }

    #[Test]
    public function it_should_convert_to_array(): void
    {
        $metadata = new SchemaMetadata(
            title: 'Test',
            description: 'Desc',
        );

        $array = $metadata->toArray();

        $this->assertEquals('http://json-schema.org/draft-07/schema#', $array['$schema']);
        $this->assertEquals('https://ctxllm.com', $array['id']);
        $this->assertEquals('Test', $array['title']);
        $this->assertEquals('Desc', $array['description']);
        $this->assertArrayHasKey('fileMatch', $array);
    }
}
