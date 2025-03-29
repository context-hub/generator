<?php

declare(strict_types=1);

namespace Tests\ConfigLoader\Import;

use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderFactoryInterface;
use Butschster\ContextGenerator\ConfigLoader\ConfigLoaderInterface;
use Butschster\ContextGenerator\ConfigLoader\Import\ImportResolver;
use Butschster\ContextGenerator\ConfigLoader\Import\PathPrefixer\SourcePathPrefixer;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\FilesInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests specific functionality of source path prefixing in the ImportResolver
 */
#[CoversClass(ImportResolver::class)]
#[CoversClass(SourcePathPrefixer::class)]
final class SourcePathPrefixingTest extends TestCase
{
    private ImportResolver $resolver;
    private FilesInterface $files;
    private Directories $dirs;
    private ConfigLoaderFactoryInterface $loaderFactory;
    private LoggerInterface $logger;

    #[Test]
    public function it_should_apply_prefixes_to_various_source_path_types(): void
    {
        // Sample config with various source path configurations
        $importedConfig = [
            'documents' => [
                [
                    'name' => 'Document with String SourcePath',
                    'outputPath' => 'docs/string-path.md',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => 'src/file.php', // Simple string path
                        ],
                    ],
                ],
                [
                    'name' => 'Document with Array SourcePaths',
                    'outputPath' => 'docs/array-paths.md',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => [ // Array of paths
                                'src/file1.php',
                                'src/file2.php',
                                '/absolute/path/file3.php', // Absolute path - should remain unchanged
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Document with Composer Source',
                    'outputPath' => 'docs/composer.md',
                    'sources' => [
                        [
                            'type' => 'composer',
                            'composerPath' => 'composer.json', // Composer path
                        ],
                    ],
                ],
                [
                    'name' => 'Document with Mixed Sources',
                    'outputPath' => 'docs/mixed.md',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => 'src/mixed.php',
                        ],
                        [
                            'type' => 'composer',
                            'composerPath' => 'composer.json',
                        ],
                        [
                            'type' => 'git_diff', // Source without paths to prefix
                            'repository' => 'repo',
                        ],
                        [
                            'type' => 'non_composer',
                            'composerPath' => 'should-not-be-prefixed.json',
                            // Should not be prefixed as type is not composer
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
                    'pathPrefix' => 'api/v1', // Output path prefix
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

        // Verify document output paths have prefix applied
        $this->assertEquals(
            'api/v1/docs/string-path.md',
            $result['documents'][0]['outputPath'],
            'Output path prefix should be applied to string path document',
        );

        $this->assertEquals(
            'api/v1/docs/array-paths.md',
            $result['documents'][1]['outputPath'],
            'Output path prefix should be applied to array paths document',
        );

        // Verify string source path has prefix applied
        $this->assertEquals(
            '/imported/src/file.php',
            $result['documents'][0]['sources'][0]['sourcePaths'],
            'Source path prefix should be applied to string sourcePaths',
        );

        // Verify array source paths have prefix applied
        $this->assertEquals(
            '/imported/src/file1.php',
            $result['documents'][1]['sources'][0]['sourcePaths'][0],
            'Source path prefix should be applied to first array sourcePath',
        );

        $this->assertEquals(
            '/imported/src/file2.php',
            $result['documents'][1]['sources'][0]['sourcePaths'][1],
            'Source path prefix should be applied to second array sourcePath',
        );

        $this->assertEquals(
            '/absolute/path/file3.php',
            $result['documents'][1]['sources'][0]['sourcePaths'][2],
            'Absolute source path should remain unchanged',
        );

        // Verify composer path has prefix applied
        $this->assertEquals(
            '/imported/composer.json',
            $result['documents'][2]['sources'][0]['composerPath'],
            'Source path prefix should be applied to composer path',
        );

        // Verify mixed sources document
        $this->assertEquals(
            '/imported/src/mixed.php',
            $result['documents'][3]['sources'][0]['sourcePaths'],
            'Source path prefix should be applied to mixed document file source',
        );

        $this->assertEquals(
            '/imported/composer.json',
            $result['documents'][3]['sources'][1]['composerPath'],
            'Source path prefix should be applied to mixed document composer source',
        );

        // Verify non-composer path with composerPath field is not prefixed
        $this->assertEquals(
            'should-not-be-prefixed.json',
            $result['documents'][3]['sources'][3]['composerPath'],
            'Non-composer source with composerPath should not be prefixed',
        );
    }

    #[Test]
    public function it_should_handle_deeply_nested_source_paths(): void
    {
        // Sample config with deeply nested source paths
        $importedConfig = [
            'documents' => [
                [
                    'name' => 'Document with Nested Source Paths',
                    'outputPath' => 'docs/nested-paths.md',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => [
                                'src/level1/level2/level3/file.php',
                                'src/another/deep/path/file.php',
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
                    'path' => '/imported/deep/nested/config.json',
                    'pathPrefix' => 'api/v1/deep',
                ],
            ],
        ];

        // Configure mocks
        $this->files->method('exists')->willReturn(true);

        $loader = $this->createMock(ConfigLoaderInterface::class);
        $loader->method('isSupported')->willReturn(true);
        $loader->method('loadRawConfig')->willReturn($importedConfig);

        $this->loaderFactory->method('createForFile')->willReturn($loader);

        // Process imports
        $result = $this->resolver->resolveImports($mainConfig, '/test/root');

        // Verify output path has prefix applied
        $this->assertEquals(
            'api/v1/deep/docs/nested-paths.md',
            $result['documents'][0]['outputPath'],
            'Output path should have deep nested prefix applied',
        );

        // Verify deeply nested source paths have prefix applied
        $this->assertEquals(
            '/imported/deep/nested/src/level1/level2/level3/file.php',
            $result['documents'][0]['sources'][0]['sourcePaths'][0],
            'Deeply nested source path should have prefix applied',
        );

        $this->assertEquals(
            '/imported/deep/nested/src/another/deep/path/file.php',
            $result['documents'][0]['sources'][0]['sourcePaths'][1],
            'Another deeply nested source path should have prefix applied',
        );
    }

    #[Test]
    public function it_should_handle_complex_multi_level_imports_with_prefixes(): void
    {
        // Define configs for each level
        $level3Config = [
            'documents' => [
                [
                    'name' => 'Level 3 Document',
                    'outputPath' => 'docs/level3.md',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => 'src/level3.php',
                        ],
                    ],
                ],
            ],
        ];

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
            'import' => [
                [
                    'path' => 'level3-config.json',
                    'pathPrefix' => 'l3',
                ],
            ],
        ];

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
                    'pathPrefix' => 'l2',
                ],
            ],
        ];

        $mainConfig = [
            'name' => 'Main Config',
            'import' => [
                [
                    'path' => 'level1-config.json',
                    'pathPrefix' => 'l1',
                ],
            ],
        ];

        // Configure mock loader to return different configs based on path
        $this->files
            ->method('exists')
            ->willReturn(true);

        $this->loaderFactory
            ->method('createForFile')
            ->willReturnCallback(function ($dirs) use ($level1Config, $level2Config, $level3Config) {
                $path = $dirs->configPath;

                $loader = $this->createMock(ConfigLoaderInterface::class);
                $loader->method('isSupported')->willReturn(true);

                if (\str_contains($path, 'level1-config.json')) {
                    $loader->method('loadRawConfig')->willReturn($level1Config);
                } elseif (\str_contains($path, 'level2-config.json')) {
                    $loader->method('loadRawConfig')->willReturn($level2Config);
                } else {
                    $loader->method('loadRawConfig')->willReturn($level3Config);
                }

                return $loader;
            });

        // Process imports
        $result = $this->resolver->resolveImports($mainConfig, '/test/root');

        // Verify we have 3 documents from all levels
        $this->assertCount(3, $result['documents'], 'Should have documents from all 3 levels');

        // Verify output paths have correctly nested prefixes
        $this->assertEquals(
            'l1/docs/level1.md',
            $result['documents'][0]['outputPath'],
            'Level 1 output path should have l1 prefix',
        );

        $this->assertEquals(
            'l1/l2/docs/level2.md',
            $result['documents'][1]['outputPath'],
            'Level 2 output path should have l1/l2 prefix',
        );

        $this->assertEquals(
            'l1/l2/l3/docs/level3.md',
            $result['documents'][2]['outputPath'],
            'Level 3 output path should have l1/l2/l3 prefix',
        );

        // With path normalization, we now expect clean paths regardless of nesting level
        $this->assertEquals(
            'src/level1.php',
            $result['documents'][0]['sources'][0]['sourcePaths'],
            'Level 1 source path should be normalized',
        );

        $this->assertEquals(
            'src/level2.php',
            $result['documents'][1]['sources'][0]['sourcePaths'],
            'Level 2 source path should be normalized',
        );

        $this->assertEquals(
            'src/level3.php',
            $result['documents'][2]['sources'][0]['sourcePaths'],
            'Level 3 source path should be normalized',
        );
    }

    #[Test]
    public function it_should_normalize_paths_with_dots_and_redundancies(): void
    {
        // Sample config with various path edge cases that need normalization
        $importedConfig = [
            'documents' => [
                [
                    'name' => 'Document with Path Edge Cases',
                    'outputPath' => './docs/./path/../file.md', // Path with dots and redundancies
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => './src/file.php', // Path with current directory reference
                        ],
                        [
                            'type' => 'file',
                            'sourcePaths' => 'src/../other/file.php', // Path with parent directory reference
                        ],
                        [
                            'type' => 'file',
                            'sourcePaths' => 'src/./nested//../file.php', // Path with multiple dots and redundancies
                        ],
                        [
                            'type' => 'composer',
                            'composerPath' => './composer.json', // Composer path with dot
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
                    'pathPrefix' => './api/v1',
                ],
            ],
        ];

        // Configure mocks
        $this->files->method('exists')->willReturn(true);

        $loader = $this->createMock(ConfigLoaderInterface::class);
        $loader->method('isSupported')->willReturn(true);
        $loader->method('loadRawConfig')->willReturn($importedConfig);

        $this->loaderFactory->method('createForFile')->willReturn($loader);

        // Process imports
        $result = $this->resolver->resolveImports($mainConfig, '/test/root');

        // Verify paths are properly normalized
        $this->assertEquals(
            'api/v1/docs/file.md', // Normalized from './docs/./path/../file.md'
            $result['documents'][0]['outputPath'],
            'Output path should be normalized',
        );

        $this->assertEquals(
            '/imported/src/file.php', // Normalized from './src/file.php'
            $result['documents'][0]['sources'][0]['sourcePaths'],
            'Source path with current directory reference should be normalized',
        );

        $this->assertEquals(
            '/imported/other/file.php', // Normalized from 'src/../other/file.php'
            $result['documents'][0]['sources'][1]['sourcePaths'],
            'Source path with parent directory reference should be normalized',
        );

        $this->assertEquals(
            '/imported/src/file.php', // Normalized from 'src/./nested//../file.php'
            $result['documents'][0]['sources'][2]['sourcePaths'],
            'Source path with multiple dots and redundancies should be normalized',
        );

        $this->assertEquals(
            '/imported/composer.json', // Normalized from './composer.json'
            $result['documents'][0]['sources'][3]['composerPath'],
            'Composer path with dot should be normalized',
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
