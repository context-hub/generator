<?php

declare(strict_types=1);

namespace Tests\Source;

use Butschster\ContextGenerator\Source\FileSource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FileSourceFromArrayTest extends TestCase
{
    #[Test]
    public function it_should_create_from_array_with_minimal_parameters()
    {
        $data = [
            'sourcePaths' => 'path/to/file.php',
        ];

        $source = FileSource::fromArray($data);

        $this->assertEquals(['/path/to/file.php'], $source->sourcePaths);
        $this->assertEquals('', $source->getDescription());
        $this->assertEquals('*.*', $source->filePattern);
        $this->assertEquals([], $source->notPath);
        $this->assertTrue($source->showTreeView);
        $this->assertEquals([], $source->modifiers);
    }

    #[Test]
    public function it_should_create_from_array_with_all_parameters()
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

        $source = FileSource::fromArray($data);

        $expectedPaths = ['/path/to/file.php', '/path/to/directory'];
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
        $this->assertTrue($source->showTreeView);
        $this->assertEquals($data['modifiers'], $source->modifiers);
    }

    #[Test]
    public function it_should_support_array_file_pattern()
    {
        $data = [
            'sourcePaths' => 'path/to/file.php',
            'filePattern' => ['*.php', '*.js'],
        ];

        $source = FileSource::fromArray($data);

        $this->assertEquals($data['filePattern'], $source->filePattern);
    }

    #[Test]
    public function it_should_support_excludePatterns_as_alias_for_notPath()
    {
        $data = [
            'sourcePaths' => 'path/to/file.php',
            'excludePatterns' => ['vendor', 'node_modules'],
        ];

        $source = FileSource::fromArray($data);

        $this->assertEquals($data['excludePatterns'], $source->notPath);
    }

    #[Test]
    public function it_should_prepend_root_path_to_source_paths()
    {
        $data = [
            'sourcePaths' => ['path/to/file.php', 'path/to/directory'],
        ];
        $rootPath = '/var/www';

        $source = FileSource::fromArray($data, $rootPath);

        $expectedPaths = [
            '/var/www/path/to/file.php',
            '/var/www/path/to/directory',
        ];
        $this->assertEquals($expectedPaths, $source->sourcePaths);
    }

    #[Test]
    public function it_should_throw_exception_if_sourcePaths_is_missing()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File source must have a "sourcePaths" property');

        FileSource::fromArray([]);
    }

    #[Test]
    public function it_should_throw_exception_if_sourcePaths_is_not_string_or_array()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"sourcePaths" must be a string or array in source');

        FileSource::fromArray(['sourcePaths' => 123]);
    }

    #[Test]
    public function it_should_throw_exception_if_filePattern_is_not_string_or_array()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('filePattern must be a string or an array of strings');

        FileSource::fromArray([
            'sourcePaths' => 'path/to/file.php',
            'filePattern' => 123,
        ]);
    }

    #[Test]
    public function it_should_throw_exception_if_filePattern_array_contains_non_strings()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('All elements in filePattern must be strings');

        FileSource::fromArray([
            'sourcePaths' => 'path/to/file.php',
            'filePattern' => ['*.php', 123],
        ]);
    }

    #[Test]
    public function it_should_serialize_to_json()
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
            showTreeView: false,
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
            'modifiers' => ['modifier1', 'modifier2'],
        ];

        $this->assertEquals($expected, $source->jsonSerialize());
    }

    #[Test]
    public function it_should_omit_empty_values_in_json_serialization()
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
            'showTreeView' => true,
        ];

        $this->assertEquals($expected, $source->jsonSerialize());
    }
}
