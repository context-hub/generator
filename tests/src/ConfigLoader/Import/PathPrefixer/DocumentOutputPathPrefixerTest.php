<?php

declare(strict_types=1);

namespace Tests\ConfigLoader\Import\PathPrefixer;

use Butschster\ContextGenerator\Config\Import\PathPrefixer\DocumentOutputPathPrefixer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the DocumentOutputPathPrefixer class
 */
#[CoversClass(DocumentOutputPathPrefixer::class)]
final class DocumentOutputPathPrefixerTest extends TestCase
{
    private DocumentOutputPathPrefixer $prefixer;

    #[Test]
    public function it_should_apply_prefix_to_document_output_paths(): void
    {
        $config = [
            'documents' => [
                [
                    'name' => 'Document 1',
                    'outputPath' => 'docs/api.md',
                ],
                [
                    'name' => 'Document 2',
                    'outputPath' => '/docs/schema.md', // Absolute path
                ],
                [
                    'name' => 'Document 3',
                    // No outputPath
                ],
            ],
        ];

        $pathPrefix = 'api/v1';

        $result = $this->prefixer->applyPrefix($config, $pathPrefix);

        // Validate the results
        $this->assertCount(3, $result['documents']);

        // First document should have prefix applied
        $this->assertEquals('api/v1/docs/api.md', $result['documents'][0]['outputPath']);

        // Second document should have prefix applied even to absolute path
        $this->assertEquals('api/v1/docs/schema.md', $result['documents'][1]['outputPath']);

        // Third document should be unchanged (no outputPath)
        $this->assertArrayNotHasKey('outputPath', $result['documents'][2]);
    }

    #[Test]
    public function it_should_handle_empty_config(): void
    {
        $config = [
            'name' => 'Empty Config',
            // No documents array
        ];

        $result = $this->prefixer->applyPrefix($config, 'prefix');

        // Config should remain unchanged
        $this->assertEquals($config, $result);
    }

    #[Test]
    public function it_should_handle_empty_documents_array(): void
    {
        $config = [
            'name' => 'Config with Empty Documents',
            'documents' => [],
        ];

        $result = $this->prefixer->applyPrefix($config, 'prefix');

        // Config should remain unchanged
        $this->assertEquals($config, $result);
    }

    protected function setUp(): void
    {
        $this->prefixer = new DocumentOutputPathPrefixer();
    }
}
