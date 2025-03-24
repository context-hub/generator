<?php

declare(strict_types=1);

namespace Tests\Source;

use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\File\FileSource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FileSourceConstructorTest extends TestCase
{
    #[Test]
    public function it_should_store_constructor_parameters(): void
    {
        $sourcePaths = ['path/to/file.php', 'path/to/directory'];
        $description = 'Test description';
        $filePattern = '*.php';
        $notPath = ['vendor', 'node_modules'];
        $path = ['src', 'app'];
        $contains = ['keyword'];
        $notContains = ['exclude'];
        $size = ['> 10K', '< 1M'];
        $date = ['since yesterday'];
        $ignoreUnreadableDirs = true;
        $showTreeView = false;
        $modifiers = ['modifier1', 'modifier2'];

        $source = new FileSource(
            sourcePaths: $sourcePaths,
            description: $description,
            filePattern: $filePattern,
            notPath: $notPath,
            path: $path,
            contains: $contains,
            notContains: $notContains,
            size: $size,
            date: $date,
            ignoreUnreadableDirs: $ignoreUnreadableDirs,
            treeView: new TreeViewConfig($showTreeView),
            modifiers: $modifiers,
        );

        $this->assertEquals($sourcePaths, $source->sourcePaths);
        $this->assertEquals($description, $source->getDescription());
        $this->assertEquals($filePattern, $source->filePattern);
        $this->assertEquals($notPath, $source->notPath);
        $this->assertEquals($path, $source->path);
        $this->assertEquals($contains, $source->contains);
        $this->assertEquals($notContains, $source->notContains);
        $this->assertEquals($size, $source->size);
        $this->assertEquals($date, $source->date);
        $this->assertEquals($ignoreUnreadableDirs, $source->ignoreUnreadableDirs);
        $this->assertEquals($showTreeView, $source->treeView->enabled);
        $this->assertEquals($modifiers, $source->modifiers);
    }

    #[Test]
    public function it_should_accept_string_source_path(): void
    {
        $sourcePath = 'path/to/file.php';
        $source = new FileSource(sourcePaths: $sourcePath);

        $this->assertEquals($sourcePath, $source->sourcePaths);
    }

    #[Test]
    public function it_should_accept_string_file_pattern(): void
    {
        $filePattern = '*.php';
        $source = new FileSource(sourcePaths: 'path', filePattern: $filePattern);

        $this->assertEquals($filePattern, $source->filePattern);
    }

    #[Test]
    public function it_should_accept_array_file_pattern(): void
    {
        $filePattern = ['*.php', '*.js'];
        $source = new FileSource(sourcePaths: 'path', filePattern: $filePattern);

        $this->assertEquals($filePattern, $source->filePattern);
    }

    #[Test]
    public function it_should_have_default_values(): void
    {
        $source = new FileSource(sourcePaths: 'path');

        $this->assertEquals('', $source->getDescription());
        $this->assertEquals('*.*', $source->filePattern);
        $this->assertEquals([], $source->notPath);
        $this->assertEquals([], $source->path);
        $this->assertEquals([], $source->contains);
        $this->assertEquals([], $source->notContains);
        $this->assertEquals([], $source->size);
        $this->assertEquals([], $source->date);
        $this->assertFalse($source->ignoreUnreadableDirs);
        $this->assertTrue($source->treeView->enabled);
        $this->assertEquals([], $source->modifiers);
    }
}
