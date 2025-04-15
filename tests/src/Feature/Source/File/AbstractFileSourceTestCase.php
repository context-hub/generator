<?php

declare(strict_types=1);

namespace Tests\Feature\Source\File;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\Source\SourceInterface;
use Tests\Feature\FeatureTestCases;

/**
 * Base class for file source tests providing common functionality
 */
abstract class AbstractFileSourceTestCase extends FeatureTestCases
{
    #[\Override]
    public function rootDirectory(): string
    {
        return $this->getFixturesDir('Source/File');
    }

    /**
     * Gets the first file source from the first document
     */
    protected function getFileSourceFromConfig(ConfigRegistryAccessor $config): FileSource
    {
        $documents = $config->getDocuments();
        $this->assertCount(1, $documents, 'Should have exactly 1 document');

        $document = $documents[0];
        $sources = $document->getSources();
        $this->assertCount(1, $sources, 'Document should have exactly 1 source');

        $source = $sources[0];
        $this->assertInstanceOf(FileSource::class, $source, 'Source should be a FileSource instance');

        return $source;
    }

    /**
     * Gets the type of a source from its JSON serialization
     *
     * @param SourceInterface $source The source to get the type from
     * @return string The source type
     */
    protected function getSourceType(SourceInterface $source): string
    {
        return \json_decode(\json_encode($source), true)['type'] ?? '';
    }

    /**
     * Creates a test file structure for tests requiring file operations
     */
    protected function createTestFileStructure(): string
    {
        $tempDir = $this->createTempDir();

        // Create a basic structure
        $rootFile = $tempDir . '/root_file.txt';
        \file_put_contents($rootFile, 'Root file content');

        $subDir = $tempDir . '/subdir';
        \mkdir($subDir, 0777, true);

        $nestedFile = $subDir . '/nested_file.txt';
        \file_put_contents($nestedFile, 'Nested file content');

        $deepDir = $subDir . '/deep';
        \mkdir($deepDir, 0777, true);

        $deepFile = $deepDir . '/deep_file.txt';
        \file_put_contents($deepFile, 'Deep nested file content');

        return $tempDir;
    }
}
