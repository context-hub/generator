<?php

declare(strict_types=1);

namespace Tests\ConfigLoader\Import;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderFactoryInterface;
use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderInterface;
use Butschster\ContextGenerator\ConfigLoader\Import\ImportResolver;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\FilesInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test specific functionality of the updated ImportResolver that uses path prefixers
 */
#[CoversClass(ImportResolver::class)]
final class ImportResolverWithPrefixerTest extends TestCase
{
    private ImportResolver $resolver;
    private FilesInterface $files;
    private Directories $dirs;
    private ConfigLoaderFactoryInterface $loaderFactory;
    private LoggerInterface $logger;

    #[Test]
    public function it_should_apply_path_prefixes_using_prefixer_services(): void
    {
        // Sample configs
        $importedConfig = [
            'documents' => [
                [
                    'name' => 'Imported Document',
                    'outputPath' => 'docs/api.md',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => 'src/api.php',
                        ],
                        [
                            'type' => 'composer',
                            'composerPath' => 'composer.json',
                        ],
                    ],
                ],
            ],
        ];

        $mainConfig = [
            'name' => 'Main Config',
            'import' => [
                [
                    'path' => '/imported/config.json',
                    'pathPrefix' => 'api/v1',
                ],
            ],
        ];

        // Configure mocks
        $this->files
            ->method('exists')
            ->willReturn(true);

        $loader = $this->createMock(ConfigLoaderInterface::class);
        $loader->method('isSupported')->willReturn(true);
        $loader->method('loadRawConfig')->willReturn($importedConfig);

        $this->loaderFactory
            ->method('createForFile')
            ->willReturn($loader);

        // Process imports with path prefixes
        $result = $this->resolver->resolveImports($mainConfig, '/test/root');

        // Verify document output path has prefix applied
        $this->assertEquals(
            'api/v1/docs/api.md',
            $result['documents'][0]['outputPath'],
        );

        // Verify source paths have prefix applied
        $this->assertEquals(
            '/imported/src/api.php',
            $result['documents'][0]['sources'][0]['sourcePaths'],
        );

        // Verify composer path has prefix applied
        $this->assertEquals(
            '/imported/composer.json',
            $result['documents'][0]['sources'][1]['composerPath'],
        );
    }

    #[Test]
    public function it_should_correctly_handle_array_source_paths(): void
    {
        // Create imported config with array source paths
        $importedConfig = [
            'documents' => [
                [
                    'name' => 'Document with Array Sources',
                    'outputPath' => 'docs/array-paths.md',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => [
                                'src/file1.php',
                                'src/file2.php',
                                '/absolute/path/file3.php', // Absolute path
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $mainConfig = [
            'name' => 'Main Config',
            'import' => [
                [
                    'path' => '/imported/config.json',
                    'pathPrefix' => 'prefixed',
                ],
            ],
        ];

        // Configure mocks
        $this->files
            ->method('exists')
            ->willReturn(true);

        $loader = $this->createMock(ConfigLoaderInterface::class);
        $loader->method('isSupported')->willReturn(true);
        $loader->method('loadRawConfig')->willReturn($importedConfig);

        $this->loaderFactory
            ->method('createForFile')
            ->willReturn($loader);

        // Process imports
        $result = $this->resolver->resolveImports($mainConfig, '/test/root');

        // Verify document output path has prefix applied
        $this->assertEquals(
            'prefixed/docs/array-paths.md',
            $result['documents'][0]['outputPath'],
            'Output path should have prefix applied',
        );

        // Verify array source paths have prefixes applied correctly
        $this->assertEquals(
            '/imported/src/file1.php',
            $result['documents'][0]['sources'][0]['sourcePaths'][0],
            'First array source path should have prefix applied',
        );

        $this->assertEquals(
            '/imported/src/file2.php',
            $result['documents'][0]['sources'][0]['sourcePaths'][1],
            'Second array source path should have prefix applied',
        );

        $this->assertEquals(
            '/absolute/path/file3.php',
            $result['documents'][0]['sources'][0]['sourcePaths'][2],
            'Absolute path in array should remain unchanged',
        );
    }

    #[Test]
    public function it_should_only_prefix_composer_path_for_composer_source_type(): void
    {
        // Create config with various source types
        $importedConfig = [
            'documents' => [
                [
                    'name' => 'Document with Various Sources',
                    'outputPath' => 'docs/sources.md',
                    'sources' => [
                        [
                            'type' => 'composer',
                            'composerPath' => 'composer.json',
                        ],
                        [
                            'type' => 'not_composer', // Not composer type
                            'composerPath' => 'not-prefixed.json', // Should not be prefixed
                        ],
                        [
                            'type' => 'git_diff', // Other source type
                            'repository' => 'repo-name',
                        ],
                    ],
                ],
            ],
        ];

        $mainConfig = [
            'name' => 'Main Config',
            'import' => [
                [
                    'path' => '/imported/config.json',
                    'pathPrefix' => 'api',
                ],
            ],
        ];

        // Configure mocks
        $this->files
            ->method('exists')
            ->willReturn(true);

        $loader = $this->createMock(ConfigLoaderInterface::class);
        $loader->method('isSupported')->willReturn(true);
        $loader->method('loadRawConfig')->willReturn($importedConfig);

        $this->loaderFactory
            ->method('createForFile')
            ->willReturn($loader);

        // Process imports
        $result = $this->resolver->resolveImports($mainConfig, '/test/root');

        // Verify output path prefix is applied
        $this->assertEquals(
            'api/docs/sources.md',
            $result['documents'][0]['outputPath'],
            'Output path should have prefix applied',
        );

        // Verify composer path is prefixed for composer source type
        $this->assertEquals(
            '/imported/composer.json',
            $result['documents'][0]['sources'][0]['composerPath'],
            'Composer path should be prefixed for composer source type',
        );

        // Verify non-composer composerPath is not prefixed
        $this->assertEquals(
            'not-prefixed.json',
            $result['documents'][0]['sources'][1]['composerPath'],
            'composerPath should not be prefixed for non-composer source type',
        );

        // Verify other source types are not affected
        $this->assertEquals(
            'repo-name',
            $result['documents'][0]['sources'][2]['repository'],
            'Other source properties should remain unchanged',
        );
    }

    #[Test]
    public function it_should_apply_nested_path_prefixes_correctly(): void
    {
        // First level imported config
        $level1Config = [
            'documents' => [
                [
                    'name' => 'Level 1 Document',
                    'outputPath' => 'docs/level1.md',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => 'src/level1.php',
                        ],
                    ],
                ],
            ],
            'import' => [
                [
                    'path' => 'level2-config.json',
                    'pathPrefix' => 'level2',
                ],
            ],
        ];

        // Second level imported config
        $level2Config = [
            'documents' => [
                [
                    'name' => 'Level 2 Document',
                    'outputPath' => 'docs/level2.md',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => 'src/level2.php',
                        ],
                    ],
                ],
            ],
        ];

        // Main config that imports level1
        $mainConfig = [
            'name' => 'Main Config',
            'import' => [
                [
                    'path' => 'level1-config.json',
                    'pathPrefix' => 'level1',
                ],
            ],
        ];

        // Configure mocks
        $this->files
            ->method('exists')
            ->willReturn(true);

        // Create loader that returns different configs based on path
        $this->loaderFactory
            ->method('createForFile')
            ->willReturnCallback(function ($dirs) use ($level1Config, $level2Config) {
                $path = $dirs->configPath;

                $loader = $this->createMock(ConfigLoaderInterface::class);
                $loader->method('isSupported')->willReturn(true);

                if (\str_contains($path, 'level1-config.json')) {
                    $loader->method('loadRawConfig')->willReturn($level1Config);
                } else {
                    $loader->method('loadRawConfig')->willReturn($level2Config);
                }

                return $loader;
            });

        // Process imports
        $result = $this->resolver->resolveImports($mainConfig, '/test/root');

        // We should have two documents
        $this->assertCount(2, $result['documents'], 'Two documents should be merged from imports');

        // Check level 1 document paths
        $level1Doc = $result['documents'][0];
        $this->assertEquals('Level 1 Document', $level1Doc['name']);
        $this->assertEquals(
            'level1/docs/level1.md',
            $level1Doc['outputPath'],
            'Level 1 document should have level1 prefix',
        );
        $this->assertEquals(
            'src/level1.php',
            $level1Doc['sources'][0]['sourcePaths'],
            'Level 1 source paths should have source directory prefix',
        );

        // Check level 2 document paths
        $level2Doc = $result['documents'][1];
        $this->assertEquals('Level 2 Document', $level2Doc['name']);
        $this->assertEquals(
            'level1/level2/docs/level2.md',
            $level2Doc['outputPath'],
            'Level 2 document should have combined level1/level2 prefix',
        );
        $this->assertEquals(
            'src/level2.php',
            $level2Doc['sources'][0]['sourcePaths'],
            'Level 2 source paths should have source directory prefix',
        );
    }

    #[Test]
    public function it_should_handle_empty_documents_and_sources_correctly(): void
    {
        // Create config with empty documents and sources
        $importedConfig = [
            'documents' => [
                [
                    'name' => 'Document without sources',
                    'outputPath' => 'docs/no-sources.md',
                    // No sources defined
                ],
                [
                    'name' => 'Document with empty sources',
                    'outputPath' => 'docs/empty-sources.md',
                    'sources' => [],
                ],
                [
                    'name' => 'Document with sources missing paths',
                    'outputPath' => 'docs/missing-paths.md',
                    'sources' => [
                        [
                            'type' => 'file',
                            // No sourcePaths defined
                        ],
                    ],
                ],
            ],
        ];

        $mainConfig = [
            'name' => 'Main Config',
            'import' => [
                [
                    'path' => '/imported/config.json',
                    'pathPrefix' => 'prefixed',
                ],
            ],
        ];

        // Configure mocks
        $this->files
            ->method('exists')
            ->willReturn(true);

        $loader = $this->createMock(ConfigLoaderInterface::class);
        $loader->method('isSupported')->willReturn(true);
        $loader->method('loadRawConfig')->willReturn($importedConfig);

        $this->loaderFactory
            ->method('createForFile')
            ->willReturn($loader);

        // Process imports
        $result = $this->resolver->resolveImports($mainConfig, '/test/root');

        // Verify output path prefixes are applied
        $this->assertEquals(
            'prefixed/docs/no-sources.md',
            $result['documents'][0]['outputPath'],
            'Document without sources should have prefix applied to outputPath',
        );

        $this->assertEquals(
            'prefixed/docs/empty-sources.md',
            $result['documents'][1]['outputPath'],
            'Document with empty sources should have prefix applied to outputPath',
        );

        $this->assertEquals(
            'prefixed/docs/missing-paths.md',
            $result['documents'][2]['outputPath'],
            'Document with sources missing paths should have prefix applied to outputPath',
        );

        // Verify structure is preserved
        $this->assertArrayNotHasKey(
            'sources',
            $result['documents'][0],
            'Document without sources should remain without sources',
        );

        $this->assertEmpty(
            $result['documents'][1]['sources'],
            'Document with empty sources should still have empty sources array',
        );

        $this->assertArrayNotHasKey(
            'sourcePaths',
            $result['documents'][2]['sources'][0],
            'Source without sourcePaths should remain without sourcePaths',
        );
    }

    protected function setUp(): void
    {
        $this->files = $this->createMock(FilesInterface::class);
        $this->loaderFactory = $this->createMock(ConfigLoaderFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->dirs = new Directories(
            rootPath: '/test/root',
            outputPath: '/test/output',
            configPath: '/test/config',
            jsonSchemaPath: '/test/schema',
            envFilePath: null,
        );

        $this->resolver = new ImportResolver(
            $this->dirs,
            $this->files,
            $this->loaderFactory,
            $this->logger,
        );
    }
}
