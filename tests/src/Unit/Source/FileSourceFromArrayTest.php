<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\Source\File\FileSourceFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FileSourceFromArrayTest extends TestCase
{
    private FileSourceFactory $factory;

    #[Test]
    public function it_should_create_from_array_with_minimal_parameters(): void
    {
        $data = [
            'sourcePaths' => 'path/to/file.php',
        ];

        $source = $this->factory->create(config: $data);

        $this->assertEquals(['/test/path/to/file.php'], $source->sourcePaths);
        $this->assertEquals('', $source->getDescription());
        $this->assertEquals('*.*', $source->filePattern);
        $this->assertEquals([], $source->notPath);
        $this->assertTrue($source->treeView->enabled);
        $this->assertEquals([], $source->modifiers);
    }

    #[Test]
    public function it_should_create_from_array_with_all_parameters(): void
    {
        $data = [
            'sourcePaths' => ['path/to/file.php', 'path/to/directory'],
            'description' => 'Test description',
            'filePattern' => '*.php',
            'notPath' => ['vendor', 'node_modules'],
            'path' => ['src', 'app'],
            'contains' => ['keyword'],
            'notContains' => ['exclude'],
            'size' => ['> 10K', '< 1M'],
            'date' => ['since yesterday'],
            'ignoreUnreadableDirs' => true,
            'modifiers' => ['modifier1', 'modifier2'],
        ];

        $source = $this->factory->create(config: $data);

        $expectedPaths = ['/test/path/to/file.php', '/test/path/to/directory'];
        $this->assertEquals($expectedPaths, $source->sourcePaths);
        $this->assertEquals($data['description'], $source->getDescription());
        $this->assertEquals($data['filePattern'], $source->filePattern);
        $this->assertEquals($data['notPath'], $source->notPath);
        $this->assertEquals($data['path'], $source->path);
        $this->assertEquals($data['contains'], $source->contains);
        $this->assertEquals($data['notContains'], $source->notContains);
        $this->assertEquals($data['size'], $source->size);
        $this->assertEquals($data['date'], $source->date);
        $this->assertEquals($data['ignoreUnreadableDirs'], $source->ignoreUnreadableDirs);
        $this->assertTrue($source->treeView->enabled);
    }

    #[Test]
    public function it_should_support_array_file_pattern(): void
    {
        $data = [
            'sourcePaths' => 'path/to/file.php',
            'filePattern' => ['*.php', '*.js'],
        ];

        $source = $this->factory->create(config: $data);

        $this->assertEquals($data['filePattern'], $source->filePattern);
    }

    #[Test]
    public function it_should_support_excludePatterns_as_alias_for_notPath(): void
    {
        $data = [
            'sourcePaths' => 'path/to/file.php',
            'excludePatterns' => ['vendor', 'node_modules'],
        ];

        $source = $this->factory->create(config: $data);

        $this->assertEquals($data['excludePatterns'], $source->notPath);
    }

    #[Test]
    public function it_should_prepend_root_path_to_source_paths(): void
    {
        $data = [
            'sourcePaths' => ['path/to/file.php', 'path/to/directory'],
        ];

        $rootPath = '/var/www';

        $source = (new FileSourceFactory(dirs: $this->createDirectories($rootPath)))->create(config: $data);

        $expectedPaths = [
            '/var/www/path/to/file.php',
            '/var/www/path/to/directory',
        ];

        $this->assertEquals($expectedPaths, $source->sourcePaths);
    }

    #[Test]
    public function it_should_throw_exception_if_sourcePaths_is_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File source must have a "sourcePaths" property');

        $this->factory->create(config: []);
    }

    #[Test]
    public function it_should_throw_exception_if_sourcePaths_is_not_string_or_array(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"sourcePaths" must be a string or array in source');

        $this->factory->create(config: ['sourcePaths' => 123]);
    }

    #[Test]
    public function it_should_throw_exception_if_filePattern_is_not_string_or_array(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('filePattern must be a string or an array of strings');

        $this->factory->create(config: [
            'sourcePaths' => 'path/to/file.php',
            'filePattern' => 123,
        ]);
    }

    #[Test]
    public function it_should_throw_exception_if_filePattern_array_contains_non_strings(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('All elements in filePattern must be strings');

        $this->factory->create(config: [
            'sourcePaths' => 'path/to/file.php',
            'filePattern' => ['*.php', 123],
        ]);
    }

    #[Test]
    public function it_should_serialize_to_json(): void
    {
        $source = new FileSource(
            sourcePaths: ['path/to/file.php', 'path/to/directory'],
            description: 'Test description',
            filePattern: '*.php',
            notPath: ['vendor', 'node_modules'],
            path: ['src', 'app'],
            contains: ['keyword'],
            notContains: ['exclude'],
            size: ['> 10K', '< 1M'],
            date: ['since yesterday'],
            ignoreUnreadableDirs: true,
            treeView: new TreeViewConfig(enabled: false),
            modifiers: ['modifier1', 'modifier2'],
        );

        $expected = [
            'type' => 'file',
            'description' => 'Test description',
            'sourcePaths' => ['path/to/file.php', 'path/to/directory'],
            'filePattern' => '*.php',
            'notPath' => ['vendor', 'node_modules'],
            'path' => ['src', 'app'],
            'contains' => ['keyword'],
            'notContains' => ['exclude'],
            'size' => ['> 10K', '< 1M'],
            'date' => ['since yesterday'],
            'ignoreUnreadableDirs' => true,
            'treeView' => [
                'enabled' => false,
                'showSize' => false,
                'showLastModified' => false,
                'showCharCount' => false,
            ],
            'modifiers' => ['modifier1', 'modifier2'],
        ];

        $this->assertEquals($expected, \json_decode(json: \json_encode(value: $source->jsonSerialize()), associative: true));
    }

    #[Test]
    public function it_should_omit_empty_values_in_json_serialization(): void
    {
        $source = new FileSource(
            sourcePaths: 'path/to/file.php',
            description: 'Test description',
        );

        $expected = [
            'type' => 'file',
            'description' => 'Test description',
            'sourcePaths' => 'path/to/file.php',
            'filePattern' => '*.*',
            'treeView' => [
                'enabled' => true,
                'showSize' => false,
                'showLastModified' => false,
                'showCharCount' => false,
            ],
        ];

        $this->assertEquals($expected, \json_decode(json: \json_encode(value: $source->jsonSerialize()), associative: true));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new FileSourceFactory(dirs: $this->createDirectories());
    }
}
