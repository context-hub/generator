<?php

declare(strict_types=1);

namespace Tests\Loader;

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Loader\JsonConfigDocumentsLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class JsonConfigDocumentsLoaderTest extends TestCase
{
    private FilesInterface $files;
    private string $rootPath;
    private string $stubsDir;

    #[Test]
    public function it_should_check_if_json_file_exists_and_has_json_extension(): void
    {
        $configPath = $this->stubsDir . '/valid-config.json';

        $loader = new JsonConfigDocumentsLoader(
            files: $this->files,
            configPath: $configPath,
            rootPath: $this->rootPath,
        );

        $this->assertTrue($loader->isSupported());
    }

    #[Test]
    public function it_should_return_false_when_file_does_not_exist(): void
    {
        $configPath = $this->stubsDir . '/non-existent-file.json';

        $loader = new JsonConfigDocumentsLoader(
            files: $this->files,
            configPath: $configPath,
            rootPath: $this->rootPath,
        );

        $this->assertFalse($loader->isSupported());
    }

    #[Test]
    public function it_should_return_false_when_file_does_not_have_json_extension(): void
    {
        $configPath = $this->stubsDir . '/not-json-file.yaml';

        $loader = new JsonConfigDocumentsLoader(
            files: $this->files,
            configPath: $configPath,
            rootPath: $this->rootPath,
        );

        $this->assertFalse($loader->isSupported());
    }

    #[Test]
    public function it_should_throw_exception_when_unable_to_read_config_file(): void
    {
        $configPath = $this->stubsDir . '/valid-config.json';

        $loader = new JsonConfigDocumentsLoader(
            files: $this->files,
            configPath: $configPath,
            rootPath: $this->rootPath,
        );

        $this->files
            ->expects($this->once())
            ->method('read')
            ->with($configPath)
            ->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Unable to read configuration file: %s', $configPath));

        $loader->load();
    }

    #[Test]
    public function it_should_throw_exception_when_config_file_contains_invalid_json(): void
    {
        $configPath = $this->stubsDir . '/invalid-json.json';
        $invalidJson = \file_get_contents($configPath);

        $loader = new JsonConfigDocumentsLoader(
            files: $this->files,
            configPath: $configPath,
            rootPath: $this->rootPath,
        );

        $this->files
            ->expects($this->once())
            ->method('read')
            ->with($configPath)
            ->willReturn($invalidJson);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Invalid JSON configuration file: %s', $configPath));

        $loader->load();
    }

    #[Test]
    public function it_should_throw_exception_when_document_is_missing_required_fields(): void
    {
        $configPath = $this->stubsDir . '/missing-required-fields.json';
        $jsonContent = \file_get_contents($configPath);

        $loader = new JsonConfigDocumentsLoader(
            files: $this->files,
            configPath: $configPath,
            rootPath: $this->rootPath,
        );

        $this->files
            ->expects($this->once())
            ->method('read')
            ->with($configPath)
            ->willReturn($jsonContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Document at index 0 must have "description" and "outputPath"');

        $loader->load();
    }

    #[Test]
    public function it_should_successfully_load_and_parse_valid_json_config(): void
    {
        $configPath = $this->stubsDir . '/valid-config.json';
        $jsonContent = \file_get_contents($configPath);

        $loader = new JsonConfigDocumentsLoader(
            files: $this->files,
            configPath: $configPath,
            rootPath: $this->rootPath,
        );

        $this->files
            ->expects($this->once())
            ->method('read')
            ->with($configPath)
            ->willReturn($jsonContent);

        $result = $loader->load();

        $this->assertInstanceOf(DocumentRegistry::class, $result);
        $documents = $result->getDocuments();
        $this->assertCount(1, $documents);

        $document = $documents[0];
        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals('Test Document', $document->description);
        $this->assertEquals('/output/path.md', $document->outputPath);
        $this->assertTrue($document->overwrite);

        $sources = $document->getSources();
        $this->assertCount(2, $sources);
        $this->assertInstanceOf(\Butschster\ContextGenerator\Source\FileSource::class, $sources[0]);
        $this->assertInstanceOf(\Butschster\ContextGenerator\Source\UrlSource::class, $sources[1]);
    }

    #[Test]
    public function it_should_successfully_load_and_parse_complex_json_config(): void
    {
        $configPath = $this->stubsDir . '/complex-config.json';
        $jsonContent = \file_get_contents($configPath);

        $loader = new JsonConfigDocumentsLoader(
            files: $this->files,
            configPath: $configPath,
            rootPath: $this->rootPath,
        );

        $this->files
            ->expects($this->once())
            ->method('read')
            ->with($configPath)
            ->willReturn($jsonContent);

        $registry = $loader->load();

        $this->assertInstanceOf(DocumentRegistry::class, $registry);
        $documents = $registry->getDocuments();
        $this->assertCount(3, $documents);

        // Verify first document (API Documentation)
        $apiDoc = $documents[0];
        $this->assertEquals('API Documentation', $apiDoc->description);
        $this->assertEquals('/output/api-docs.md', $apiDoc->outputPath);
        $this->assertTrue($apiDoc->overwrite);

        $apiSources = $apiDoc->getSources();
        $this->assertCount(2, $apiSources);
        $this->assertInstanceOf(\Butschster\ContextGenerator\Source\FileSource::class, $apiSources[0]);
        $this->assertInstanceOf(\Butschster\ContextGenerator\Source\UrlSource::class, $apiSources[1]);

        // Check file source properties
        $fileSource = $apiSources[0];
        $this->assertEquals('API Source Files', $fileSource->getDescription());
        $this->assertEquals('*.php', $fileSource->filePattern);
        $this->assertTrue($fileSource->showTreeView);
        $this->assertCount(2, $fileSource->modifiers);

        // Check URL source properties
        $urlSource = $apiSources[1];
        $this->assertEquals('External API References', $urlSource->getDescription());
        $this->assertEquals(["https://example.com/api/docs", "https://example.com/api/reference"], $urlSource->urls);
        $this->assertEquals('.api-content', $urlSource->getSelector());

        // Verify second document (User Guide)
        $userGuide = $documents[1];
        $this->assertEquals('User Guide', $userGuide->description);
        $this->assertEquals('/output/user-guide.md', $userGuide->outputPath);
        $this->assertFalse($userGuide->overwrite);

        $userGuideSources = $userGuide->getSources();
        $this->assertCount(3, $userGuideSources);
        $this->assertInstanceOf(\Butschster\ContextGenerator\Source\TextSource::class, $userGuideSources[0]);
        $this->assertInstanceOf(\Butschster\ContextGenerator\Source\FileSource::class, $userGuideSources[1]);
        $this->assertInstanceOf(\Butschster\ContextGenerator\Source\GithubSource::class, $userGuideSources[2]);

        // Verify third document (Development Setup)
        $devSetup = $documents[2];
        $this->assertEquals('Development Setup', $devSetup->description);
        $this->assertEquals('/output/dev-setup.md', $devSetup->outputPath);
        $this->assertTrue($devSetup->overwrite); // Default value is true when not specified

        $devSetupSources = $devSetup->getSources();
        $this->assertCount(2, $devSetupSources);
        $this->assertInstanceOf(\Butschster\ContextGenerator\Source\FileSource::class, $devSetupSources[0]);
        $this->assertInstanceOf(\Butschster\ContextGenerator\Source\TextSource::class, $devSetupSources[1]);

        // Check file source with complex modifiers
        $configFileSource = $devSetupSources[0];
        $this->assertFalse($configFileSource->showTreeView);
        $this->assertCount(1, $configFileSource->modifiers);
        $this->assertIsArray($configFileSource->modifiers[0]);
        $this->assertEquals('remove-secrets', $configFileSource->modifiers[0]['name']);
        $this->assertIsArray($configFileSource->modifiers[0]['options']);
        $this->assertEquals(['password', 'token', 'key'], $configFileSource->modifiers[0]['options']['patterns']);
    }

    protected function setUp(): void
    {
        $this->files = $this->createMock(FilesInterface::class);
        $this->rootPath = \dirname(__DIR__, 3);
        $this->stubsDir = $this->rootPath . '/tests/stubs/config';
    }
}
