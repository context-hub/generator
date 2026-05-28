<?php

declare(strict_types=1);

namespace Tests\Unit\Document;

use Butschster\ContextGenerator\Document\DocumentsParserPlugin;
use Butschster\ContextGenerator\Source\Registry\SourceProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(DocumentsParserPlugin::class)]
final class DocumentsParserPluginTest extends TestCase
{
    private SourceProviderInterface $sources;
    private DocumentsParserPlugin $plugin;

    #[Test]
    public function it_uses_global_showFileStats_false_when_no_per_document_key(): void
    {
        $config = [
            'settings' => ['showFileStats' => false],
            'documents' => [
                ['description' => 'Doc', 'outputPath' => '/out.md'],
            ],
        ];

        $registry = $this->plugin->parse($config, '/root');
        $documents = \iterator_to_array($registry);

        $this->assertCount(1, $documents);
        $this->assertFalse($documents[0]->showFileStats);
    }

    #[Test]
    public function it_allows_per_document_to_override_global_false_with_true(): void
    {
        $config = [
            'settings' => ['showFileStats' => false],
            'documents' => [
                ['description' => 'Doc', 'outputPath' => '/out.md', 'showFileStats' => true],
            ],
        ];

        $registry = $this->plugin->parse($config, '/root');
        $documents = \iterator_to_array($registry);

        $this->assertCount(1, $documents);
        $this->assertTrue($documents[0]->showFileStats);
    }

    #[Test]
    public function it_defaults_to_true_when_neither_global_nor_per_document_key_is_set(): void
    {
        $config = [
            'documents' => [
                ['description' => 'Doc', 'outputPath' => '/out.md'],
            ],
        ];

        $registry = $this->plugin->parse($config, '/root');
        $documents = \iterator_to_array($registry);

        $this->assertCount(1, $documents);
        $this->assertTrue($documents[0]->showFileStats);
    }

    #[Test]
    public function it_applies_per_doc_show_file_stats_false_when_no_global(): void
    {
        $config = [
            'documents' => [
                ['description' => 'Doc', 'outputPath' => '/out.md', 'showFileStats' => false],
            ],
        ];

        $registry = $this->plugin->parse($config, '/root');
        $documents = \iterator_to_array($registry);

        $this->assertCount(1, $documents);
        $this->assertFalse($documents[0]->showFileStats);
    }

    #[Test]
    public function it_does_not_emit_notice_when_settings_is_absent(): void
    {
        $config = [
            'documents' => [
                ['description' => 'Doc', 'outputPath' => '/out.md'],
            ],
        ];

        // If PHP emitted a notice/warning, PHPUnit would report it as a risky test
        // (or error, depending on configuration). A clean parse confirms no notice.
        $registry = $this->plugin->parse($config, '/root');
        $documents = \iterator_to_array($registry);

        $this->assertTrue($documents[0]->showFileStats);
    }

    protected function setUp(): void
    {
        $this->sources = $this->createMock(SourceProviderInterface::class);
        $this->plugin = new DocumentsParserPlugin($this->sources);
    }
}
