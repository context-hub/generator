<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

final class ExcludeTest extends ConsoleTestCase
{
    private string $outputDir;

    /**
     * Data provider for command variations
     */
    public static function commandsProvider(): \Generator
    {
        yield 'generate' => ['generate'];
        yield 'build' => ['build'];
        yield 'compile' => ['compile'];
    }

    /**
     * Test that global exclusion patterns work correctly
     */
    #[Test]
    #[DataProvider('commandsProvider')]
    public function global_exclusion_patterns_should_work(string $command): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/FileSource/exclude-test.yaml'),
                command: $command,
            )
            ->assertDocumentsCompiled()
            // TestClass.php should be included
            ->assertContext(
                document: 'exclude-test.md',
                contains: [
                    'TestClass.php',
                    'class TestClass',
                ],
            )
            // JavaScript files should be excluded by pattern
            ->assertContext(
                document: 'exclude-test.md',
                notContains: [
                    'script.js',
                    'function helloWorld',
                ],
            )
            // Text files should be excluded by pattern
            ->assertContext(
                document: 'exclude-test.md',
                notContains: [
                    'sample.txt',
                    'This is a sample text file',
                ],
            )
            // Nested directory should be excluded by path
            ->assertContext(
                document: 'exclude-test.md',
                notContains: [
                    'NestedClass.php',
                    'class NestedClass',
                ],
            );
    }

    /**
     * Set up the test
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = $this->createTempDir();
    }

    /**
     * Helper method to build context
     */
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
