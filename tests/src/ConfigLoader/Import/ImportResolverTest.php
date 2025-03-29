<?php

declare(strict_types=1);

namespace Tests\ConfigLoader\Import;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderFactoryInterface;
use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderInterface;
use Butschster\ContextGenerator\ConfigLoader\Import\ImportResolver;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\FilesInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ImportResolverTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/../../../fixtures/ConfigLoader/Import';

    private FilesInterface $files;
    private ConfigLoaderFactoryInterface $loaderFactory;
    private LoggerInterface $logger;
    private ImportResolver $resolver;

    #[Test]
    public function it_should_process_regular_imports(): void
    {
        // Load fixture files
        $baseConfig = $this->loadFixture('base-config.json');
        $regularImport = $this->loadFixture('regular-import.json');

        // Configure mocks
        $this->files
            ->expects($this->atLeastOnce())
            ->method('exists')
            ->willReturn(true);

        $this->loaderFactory
            ->expects($this->atLeastOnce())
            ->method('createForFile')
            ->willReturnCallback(function () use ($regularImport) {
                $loader = $this->createMock(ConfigLoaderInterface::class);
                $loader->method('isSupported')->willReturn(true);
                $loader->method('loadRawConfig')->willReturn($regularImport);
                return $loader;
            });

        // Process imports
        $result = $this->resolver->resolveImports($baseConfig, self::FIXTURES_DIR);

        // Verify the expected structure
        $this->assertArrayNotHasKey('import', $result, 'Import directive should be removed');

        // Verify documents were merged correctly
        $this->assertCount(2, $result['documents'], 'Documents from base and import should be merged');
        $this->assertEquals('Base Document', $result['documents'][0]['name']);
        $this->assertEquals('Regular Document', $result['documents'][1]['name']);
    }

    #[Test]
    public function it_should_handle_nested_imports_with_path_prefix(): void
    {
        // Load fixture files
        $baseConfig = $this->loadFixture('nested-import.json');
        $deeplyNestedConfig = $this->loadFixture('deeply-nested.json');

        // Configure mocks
        $this->files
            ->expects($this->atLeastOnce())
            ->method('exists')
            ->willReturn(true);

        $this->loaderFactory
            ->expects($this->atLeastOnce())
            ->method('createForFile')
            ->willReturnCallback(function () use ($deeplyNestedConfig) {
                $loader = $this->createMock(ConfigLoaderInterface::class);
                $loader->method('isSupported')->willReturn(true);
                $loader->method('loadRawConfig')->willReturn($deeplyNestedConfig);
                return $loader;
            });

        // Process imports
        $result = $this->resolver->resolveImports($baseConfig, self::FIXTURES_DIR);

        // Verify the expected structure
        $this->assertArrayNotHasKey('import', $result, 'Import directive should be removed');

        // Verify documents were merged correctly
        $this->assertCount(2, $result['documents'], 'Documents from both configs should be merged');
        $this->assertEquals('Nested Document', $result['documents'][0]['name']);
        $this->assertEquals('Deeply Nested Document', $result['documents'][1]['name']);
    }

    #[Test]
    public function it_should_process_wildcard_imports(): void
    {
        // Load fixture files
        $baseConfig = $this->loadFixture('base-config.json');
        $baseConfig['import'] = [
            ['path' => 'wildcard/*.json'],
        ];

        $wildcardConfig1 = $this->loadFixture('wildcard/config1.json');
        $wildcardConfig2 = $this->loadFixture('wildcard/config2.json');

        // Configure mocks
        $this->files
            ->expects($this->atLeastOnce())
            ->method('exists')
            ->willReturn(true);

        // Mock the loader factory to return different configs based on the path
        $loaderCallback = function ($dirs) use ($wildcardConfig1, $wildcardConfig2) {
            $path = $dirs->configPath;
            $loader = $this->createMock(ConfigLoaderInterface::class);
            $loader->method('isSupported')->willReturn(true);

            if (\str_contains($path, 'config1.json')) {
                $loader->method('loadRawConfig')->willReturn($wildcardConfig1);
            } else {
                if (\str_contains($path, 'config2.json')) {
                    $loader->method('loadRawConfig')->willReturn($wildcardConfig2);
                } else {
                    $loader->method('loadRawConfig')->willReturn([]);
                }
            }

            return $loader;
        };

        $this->loaderFactory
            ->expects($this->atLeastOnce())
            ->method('createForFile')
            ->willReturnCallback($loaderCallback);

        // Process imports
        $result = $this->resolver->resolveImports($baseConfig, self::FIXTURES_DIR);

        // Verify the expected structure
        $this->assertArrayNotHasKey('import', $result, 'Import directive should be removed');

        // Verify documents were merged correctly - base + 2 wildcard configs
        $this->assertCount(3, $result['documents'], 'Documents from base and wildcard imports should be merged');
        $this->assertEquals('Base Document', $result['documents'][0]['name']);
        $this->assertEquals('Wildcard Document 1', $result['documents'][1]['name']);
        $this->assertEquals('Wildcard Document 2', $result['documents'][2]['name']);
    }

    #[Test]
    public function it_should_detect_circular_imports(): void
    {
        // Load fixture files
        $baseConfig = $this->loadFixture('base-config.json');
        $baseConfig['import'] = [
            ['path' => 'circular-import.json'],
        ];
        $circularConfig = $this->loadFixture('circular-import.json');

        // Configure mocks
        $this->files
            ->expects($this->atLeastOnce())
            ->method('exists')
            ->willReturn(true);

        $this->loaderFactory
            ->expects($this->atLeastOnce())
            ->method('createForFile')
            ->willReturnCallback(function ($dirs) use ($baseConfig, $circularConfig) {
                $path = $dirs->configPath;

                $loader = $this->createMock(ConfigLoaderInterface::class);
                $loader->method('isSupported')->willReturn(true);

                if (\str_contains($path, 'base-config.json')) {
                    $loader->method('loadRawConfig')->willReturn($baseConfig);
                } else {
                    if (\str_contains($path, 'circular-import.json')) {
                        $loader->method('loadRawConfig')->willReturn($circularConfig);
                    } else {
                        $loader->method('loadRawConfig')->willReturn([]);
                    }
                }

                return $loader;
            });

        // Expect exception due to circular dependency
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular import detected');

        // Process imports - should throw exception
        $this->resolver->resolveImports($circularConfig, self::FIXTURES_DIR);
    }

    #[Test]
    public function it_should_apply_path_prefix_to_sources(): void
    {
        // Load fixture files
        $configWithSources = $this->loadFixture('path-prefix/config-with-sources.json');
        $importedConfig = $this->loadFixture('path-prefix/imported-config.json');

        // Configure mocks
        $this->files
            ->expects($this->atLeastOnce())
            ->method('exists')
            ->willReturn(true);

        $this->loaderFactory
            ->expects($this->once())
            ->method('createForFile')
            ->willReturnCallback(function () use ($importedConfig) {
                $loader = $this->createMock(ConfigLoaderInterface::class);
                $loader->method('isSupported')->willReturn(true);
                $loader->method('loadRawConfig')->willReturn($importedConfig);
                return $loader;
            });

        // Process imports
        $result = $this->resolver->resolveImports($configWithSources, self::FIXTURES_DIR . '/path-prefix');

        // Verify path prefixes were applied correctly
        $importedDoc = $result['documents'][1]; // Second document is from the imported config
        $this->assertEquals('Imported Document', $importedDoc['name']);

        // Check that path prefixes were applied to sourcePaths
        $this->assertEquals(
            'nested/path.php',
            $importedDoc['outputPath'],
        );
    }

    #[Test]
    public function it_should_skip_already_processed_imports(): void
    {
        // Create a new config that imports the same file twice
        $duplicateImportsConfig = [
            'name' => 'Duplicate Imports',
            'import' => [
                ['path' => 'regular-import.json'],
                ['path' => 'regular-import.json'],
            ],
        ];

        $regularImport = $this->loadFixture('regular-import.json');

        // Configure mocks
        $this->files
            ->expects($this->atLeastOnce())
            ->method('exists')
            ->willReturn(true);

        // We should only call createForFile once since the second import should be skipped
        $this->loaderFactory
            ->expects($this->once())
            ->method('createForFile')
            ->willReturnCallback(function () use ($regularImport) {
                $loader = $this->createMock(ConfigLoaderInterface::class);
                $loader->method('isSupported')->willReturn(true);
                $loader->method('loadRawConfig')->willReturn($regularImport);
                return $loader;
            });

        // Process imports
        $result = $this->resolver->resolveImports($duplicateImportsConfig, self::FIXTURES_DIR);

        // Verify the document was only included once
        $this->assertCount(1, $result['documents'], 'Document should only be included once');
        $this->assertEquals('Regular Document', $result['documents'][0]['name']);
    }

    protected function setUp(): void
    {
        $this->fixturesDir = self::FIXTURES_DIR;

        $this->files = $this->createMock(FilesInterface::class);
        $this->dirs = new Directories(
            rootPath: $this->fixturesDir,
            outputPath: $this->fixturesDir,
            configPath: $this->fixturesDir,
            jsonSchemaPath: $this->fixturesDir,
            envFilePath: null,
        );

        $this->loaderFactory = $this->createMock(ConfigLoaderFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->resolver = new ImportResolver(
            $this->dirs,
            $this->files,
            $this->loaderFactory,
            $this->logger,
        );
    }

    /**
     * Helper method to load a fixture file
     */
    private function loadFixture(string $filename): array
    {
        $path = \realpath(self::FIXTURES_DIR . '/' . $filename);

        if (!\file_exists($path)) {
            throw new \RuntimeException("Fixture file not found: {$path}");
        }

        $content = \file_get_contents($path);
        return \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
