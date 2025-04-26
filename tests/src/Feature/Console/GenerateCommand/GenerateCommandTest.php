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
            ->assertSuccessfulCompiled()
            ->assertContextContains(
                document: 'context.md',
                strings: [
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
            ->assertSuccessfulCompiled()
            ->assertContextContains(
                document: 'first.md',
                strings: [
                    '# First document',
                    'This is the first document content',
                    '<first>',
                    '</first>',
                ],
            )
            ->assertContextContains(
                document: 'second.md',
                strings: [
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
            ->assertSuccessfulCompiled()
            ->assertContextContains(
                document: 'mixed.md',
                strings: [
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
            ->assertSuccessfulCompiled()
            ->assertContextContains(
                document: 'json-test.md',
                strings: [
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
            ->assertSuccessfulCompiled()
            ->assertContextContains(
                document: 'inline.md',
                strings: [
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
            ->assertContextContains(
                document: 'env-test.md',
                strings: [
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
            ->assertSuccessfulCompiled()
            ->assertContextContains(
                document: 'variables.md',
                strings: [
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
            ->assertContextContains(
                document: 'base.md',
                strings: [
                    '# Base Configuration',
                    'This content comes from the base configuration',
                    '<base>',
                    '</base>',
                ],
            )
            ->assertContextContains(
                document: 'import.md',
                strings: [
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
        $args = [];


        if ($configPath !== null) {
            $args['--config-file'] = $configPath;
        }

        if ($inlineJson !== null) {
            $args['--inline'] = $inlineJson;
        }

        if ($workDir !== null) {
            $args['--work-dir'] = $workDir;
        }

        if ($envFile !== null) {
            $args['--env'] = $envFile;
        }

        if ($asJson) {
            $args['--json'] = true;
        }

        $output = $this->runCommand(
            command: $command,
            args: $args,
        );

        $output = \trim($output);
        $data = \json_decode($output, true);

        if (!$data) {
            throw new \RuntimeException('Failed to decode JSON output: ' . \json_last_error_msg());
        }

        return new CompilingResult($data);
    }
}
