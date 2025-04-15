<?php

declare(strict_types=1);

namespace Tests\Feature\Source\File;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests basic functionality of the FileSource component.
 */
#[Group('source')]
#[Group('file-source')]
final class FileSourceTest extends AbstractFileSourceTestCase
{
    #[\Override]
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('Source/File/basic_file_source.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        $documents = $config->getDocuments();
        $source = $this->getFileSourceFromConfig($config);

        // Check source type
        $this->assertEquals('file', $this->getSourceType($source));

        // Verify source properties
        $this->assertEquals('Basic file source test', $source->getDescription());
        $this->assertEquals('*.txt', $source->filePattern);
        $this->assertTrue($source->treeView->enabled);

        // Verify document properties
        $document = $documents[0];
        $this->assertEquals('File Source Test Document', $document->description);
        $compiledDocument = $compiler->compile($document);
        $content = (string) $compiledDocument->content;

        // Check for expected content in the output
        $this->assertStringContainsString('# File Source Test Document', $content);
        $this->assertStringContainsString('```', $content);

        // Check that files from the fixture were included
        $this->assertStringContainsString('test_file.txt', $content);
        $this->assertStringContainsString('This is test content', $content);
    }
}
