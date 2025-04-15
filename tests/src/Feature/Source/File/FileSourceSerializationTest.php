<?php

declare(strict_types=1);

namespace Tests\Feature\Source\File;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\File\FileSource;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests JSON serialization functionality of the FileSource component.
 */
#[Group('source')]
#[Group('file-source')]
final class FileSourceSerializationTest extends AbstractFileSourceTestCase
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('Source/File/basic_file_source.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        $source = $this->getFileSourceFromConfig($config);

        // Test JSON serialization
        $serialized = \json_encode($source);
        $this->assertNotFalse($serialized, 'JSON serialization failed');

        $decoded = \json_decode($serialized, true);
        $this->assertIsArray($decoded);

        // Check required fields
        $this->assertArrayHasKey('type', $decoded);
        $this->assertEquals('file', $decoded['type']);

        $this->assertArrayHasKey('sourcePaths', $decoded);
        $this->assertArrayHasKey('filePattern', $decoded);
        $this->assertArrayHasKey('treeView', $decoded);

        // Create a new FileSource with minimal parameters
        $minimalSource = new FileSource(
            sourcePaths: '/test/path',
            description: 'Minimal source',
        );

        $minimalSerialized = \json_encode($minimalSource);
        $this->assertNotFalse($minimalSerialized, 'Minimal source JSON serialization failed');

        $minimalDecoded = \json_decode($minimalSerialized, true);
        $this->assertIsArray($minimalDecoded);

        // Check that optional fields are not included when empty
        $this->assertArrayNotHasKey('path', $minimalDecoded);
        $this->assertArrayNotHasKey('contains', $minimalDecoded);
        $this->assertArrayNotHasKey('notContains', $minimalDecoded);
        $this->assertArrayNotHasKey('size', $minimalDecoded);
        $this->assertArrayNotHasKey('date', $minimalDecoded);

        // Test source with all options
        $fullSource = new FileSource(
            sourcePaths: '/test/path',
            description: 'Full source',
            filePattern: '*.php',
            notPath: ['*/vendor/*'],
            path: 'src/Controller',
            contains: 'namespace',
            notContains: 'private',
            size: '> 1K',
            date: 'since yesterday',
            ignoreUnreadableDirs: true,
            treeView: new TreeViewConfig(
                enabled: true,
                showSize: true,
                showLastModified: true,
            ),
            maxFiles: 10,
        );

        $fullSerialized = \json_encode($fullSource);
        $this->assertNotFalse($fullSerialized, 'Full source JSON serialization failed');

        $fullDecoded = \json_decode($fullSerialized, true);
        $this->assertIsArray($fullDecoded);

        // Check that all options are serialized
        $this->assertArrayHasKey('path', $fullDecoded);
        $this->assertArrayHasKey('contains', $fullDecoded);
        $this->assertArrayHasKey('notContains', $fullDecoded);
        $this->assertArrayHasKey('size', $fullDecoded);
        $this->assertArrayHasKey('date', $fullDecoded);
        $this->assertArrayHasKey('ignoreUnreadableDirs', $fullDecoded);
        $this->assertArrayHasKey('maxFiles', $fullDecoded);
        $this->assertEquals(10, $fullDecoded['maxFiles']);
    }
}
