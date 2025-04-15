<?php

declare(strict_types=1);

namespace Tests\Feature\Source\File;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the tree view functionality of the FileSource component.
 */
#[Group('source')]
#[Group('file-source')]
final class FileSourceTreeViewTest extends AbstractFileSourceTestCase
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('Source/File/tree_view_file_source.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        $source = $this->getFileSourceFromConfig($config);
        $document = $config->getDocuments()[0];
        $this->assertEquals('Tree View File Source Test', $document->description);

        // Verify tree view configuration
        $treeView = $source->treeView;
        $this->assertInstanceOf(TreeViewConfig::class, $treeView);
        $this->assertTrue($treeView->enabled);
        $this->assertTrue($treeView->showSize);
        $this->assertTrue($treeView->showLastModified);
        $this->assertTrue($treeView->showCharCount);
        $this->assertTrue($treeView->includeFiles);
        $this->assertEquals(2, $treeView->maxDepth);

        // Compile and verify tree view output
        $compiledDocument = $compiler->compile($document);
        $content = (string) $compiledDocument->content;

        // Check that the tree view formatting shows the configured information
        $this->assertStringContainsString('B', $content, 'Tree view should show size');
        $this->assertStringContainsString('chars', $content, 'Tree view should show character count');

        // Tree view should include both the root file and the nested directory
        $this->assertStringContainsString('test_file.txt', $content);
        $this->assertStringContainsString('test_directory', $content);
        $this->assertStringContainsString('nested_file.txt', $content);
    }
}
