<?php

declare(strict_types=1);

namespace Tests\Feature\Source\File;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the filtering capabilities of the FileSource component.
 */
#[Group('source')]
#[Group('file-source')]
final class FileSourceFilterTest extends AbstractFileSourceTestCase
{
    #[\Override]
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('Source/File/filtered_file_source.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        $documents = $config->getDocuments();
        $document = $documents[0];
        $this->assertEquals('Filtered File Source Test', $document->description);
        $source = $this->getFileSourceFromConfig($config);

        // Verify filter properties
        $this->assertEquals('test_directory', $source->path);
        $this->assertEquals(['**/unwanted*.txt'], $source->notPath);
        $this->assertEquals('nested file', $source->contains);

        // Compile and verify content filtering worked
        $compiledDocument = $compiler->compile($document);
        $content = (string) $compiledDocument->content;

        // Should include nested file but not the root test_file.txt
        $this->assertStringContainsString('nested_file.txt', $content);
        $this->assertStringContainsString('nested file content', $content);
        $this->assertStringNotContainsString('test_file.txt', $content, 'Root test_file.txt should be filtered out');
    }
}
