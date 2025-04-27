<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

final class GenerateCommandTest extends ConsoleTestCase
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
    public function simple_config_should_be_rendered(string $command): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/simple.yaml'),
                command: $command,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'context.md',
                contains: [
                    '# Simple context document',
                    'Simple context',
                    '<simple>',
                    '</simple>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function multiple_documents_should_be_generated(): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/multiple-documents.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'first.md',
                contains: [
                    '# First document',
                    'This is the first document content',
                    '<first>',
                    '</first>',
                ],
            )
            ->assertContext(
                document: 'second.md',
                contains: [
                    '# Second document',
                    'This is the second document content',
                    '<second>',
                    '</second>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function mixed_sources_should_be_generated(): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/mixed-sources.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'mixed.md',
                contains: [
                    '# Mixed source types',
                    'This is a text source',
                    '<text_source>',
                    '</text_source>',
                    <<<'TREE'
                        └── dir2/
                            └── Test2Class.php
                            └── file.txt
                        TREE,
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function invalid_config_should_return_error(): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/invalid.yaml'),
            )
            ->assertNoDocumentsToCompile();
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function json_config_should_be_rendered(): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/config.json'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'json-test.md',
                contains: [
                    '# JSON configuration test',
                    'This content comes from a JSON configuration',
                    '<json_content>',
                    '</json_content>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function inline_json_should_be_used_instead_of_file(): void
    {
        $inlineJson = \file_get_contents($this->getFixturesDir('Console/GenerateCommand/inline-json.json'));

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/simple.yaml'), // This should be ignored
                inlineJson: $inlineJson,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'inline.md',
                contains: [
                    '# Inline JSON test',
                    'This content comes from inline JSON',
                    '<inline_content>',
                    '</inline_content>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function custom_env_file_should_be_used(): void
    {
        // Create an env file with variables
        $envFile = $this->createTempFile(
            "TEST_VAR=custom_value\n",
            '.env',
        );

        // Create a config that uses the env variable
        $envConfig = $this->createTempFile(
            <<<'YAML'
                documents:
                  - description: "Env variable test"
                    outputPath: "env-test.md"
                    sources:
                      - type: text
                        description: "Env content"
                        content: "{{TEST_VAR}}"
                        tag: "env_var"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $envConfig,
                envFile: $envFile,
            )
            ->assertContext(
                document: 'env-test.md',
                contains: [
                    '# Env variable test',
                    'custom_value',
                    '<env_var>',
                    '</env_var>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function variables_should_be_substituted_in_content(): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/variables.yaml'),
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'variables.md',
                contains: [
                    '# Test Project Documentation',
                    'Version: 1.0.0',
                    'This document demonstrates the use of variables in configuration',
                    '<variables>',
                    '</variables>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function config_imports_should_work(): void
    {
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $this->getFixturesDir('Console/GenerateCommand/import-config.yaml'),
            )
            ->assertContext(
                document: 'base.md',
                contains: [
                    '# Base Configuration',
                    'This content comes from the base configuration',
                    '<base>',
                    '</base>',
                ],
            )
            ->assertContext(
                document: 'import.md',
                contains: [
                    '# Imported Configuration',
                    'This document imports another configuration',
                    '<import>',
                    '</import>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function non_existing_config_should_return_error(): void
    {
        $this->buildContext(
            workDir: $this->outputDir,
            configPath: 'non-existing-config.yaml',
        )->assetFiledToLoadConfig();
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
