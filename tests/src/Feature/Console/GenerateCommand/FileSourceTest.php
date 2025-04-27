<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

final class FileSourceTest extends ConsoleTestCase
{
    private string $outputDir;

    public static function commandsProvider(): \Generator
    {
        yield 'generate' => ['generate'];
        yield 'build' => ['build'];
        yield 'compile' => ['compile'];
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function basic_file_source_should_be_rendered(string $command): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/FileSource/basic.yaml'),
                command: $command,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'file-source.md',
                contains: [
                    '# Basic File Source Test',
                    'TestClass.php',
                    'class TestClass',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function file_source_with_filters_should_be_rendered(): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/FileSource/filtered.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'filtered-files.md',
                contains: [
                    '# Filtered File Source Test',
                    'TestClass.php',
                    'class TestClass',
                ],
            )
            ->assertContext(
                document: 'filtered-files.md',
                contains: [
                    'function testMethod',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function file_source_with_tree_view_should_be_rendered(): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/FileSource/tree-view.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'tree-view.md',
                contains: [
                    '# Tree View File Source Test',
                    'TestClass.php',
                    'nested/',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function multiple_file_patterns_should_be_rendered(): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/FileSource/multiple-patterns.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'multiple-patterns.md',
                contains: [
                    '# Multiple File Patterns Test',
                    'TestClass.php',
                    'script.js',
                    'sample.txt',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function content_filtering_should_work(): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/FileSource/content-filter.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'content-filter.md',
                contains: [
                    '# Content Filter Test',
                    'TestClass.php',
                    'function testMethod',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function path_filtering_should_work(): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/FileSource/path-filter.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'path-filter.md',
                contains: [
                    '# Path Filter Test',
                    'NestedClass.php',
                    'class NestedClass',
                ],
            );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = $this->createTempDir();
    }

    protected function buildContext(
        string $workDir,
        ?string $configPath = null,
        ?string $inlineJson = null,
        ?string $envFile = null,
        string $command = 'generate',
        bool $asJson = true,
    ): CompilingResult {
        return (new ContextBuilder($this->getConsole()))->build(
            workDir: $workDir,
            configPath: $configPath,
            inlineJson: $inlineJson,
            envFile: $envFile,
            command: $command,
            asJson: $asJson,
        );
    }
}
