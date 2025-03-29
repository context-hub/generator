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
