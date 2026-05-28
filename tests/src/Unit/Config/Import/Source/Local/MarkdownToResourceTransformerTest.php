<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Import\Source\Local;

use Butschster\ContextGenerator\Config\Import\Source\Local\MarkdownToResourceTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownToResourceTransformer::class)]
final class MarkdownToResourceTransformerTest extends TestCase
{
    private MarkdownToResourceTransformer $transformer;

    #[Test]
    public function it_sets_showFileStats_false_when_present_in_front_matter(): void
    {
        $data = [
            'markdownFiles' => [
                [
                    'name' => 'test.md',
                    'relativePath' => 'test.md',
                    'content' => 'Hello',
                    'metadata' => [
                        'description' => 'Test doc',
                        'outputPath' => 'out.md',
                        'showFileStats' => false,
                    ],
                ],
            ],
        ];

        $config = $this->transformer->transform($data);

        $this->assertArrayHasKey('documents', $config);
        $this->assertCount(1, $config['documents']);
        $this->assertArrayHasKey('showFileStats', $config['documents'][0]);
        $this->assertFalse($config['documents'][0]['showFileStats']);
    }

    #[Test]
    public function it_omits_showFileStats_key_when_absent_from_front_matter(): void
    {
        $data = [
            'markdownFiles' => [
                [
                    'name' => 'test.md',
                    'relativePath' => 'test.md',
                    'content' => 'Hello',
                    'metadata' => [
                        'description' => 'Test doc',
                        'outputPath' => 'out.md',
                    ],
                ],
            ],
        ];

        $config = $this->transformer->transform($data);

        $this->assertArrayHasKey('documents', $config);
        $this->assertArrayNotHasKey('showFileStats', $config['documents'][0]);
    }

    #[Test]
    public function it_leaves_overwrite_handling_unaffected(): void
    {
        $data = [
            'markdownFiles' => [
                [
                    'name' => 'test.md',
                    'relativePath' => 'test.md',
                    'content' => 'Hello',
                    'metadata' => [
                        'description' => 'Test doc',
                        'outputPath' => 'out.md',
                        'overwrite' => false,
                        'showFileStats' => false,
                    ],
                ],
            ],
        ];

        $config = $this->transformer->transform($data);

        $doc = $config['documents'][0];
        $this->assertFalse($doc['overwrite']);
        $this->assertFalse($doc['showFileStats']);
    }

    protected function setUp(): void
    {
        $this->transformer = new MarkdownToResourceTransformer();
    }
}
