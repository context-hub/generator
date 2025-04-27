<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

/**
 * Tests for tools functionality in configuration files.
 *
 * This test suite verifies that tools can be properly defined in configuration
 * files and that they are correctly processed by the generate command.
 */
final class ToolsTest extends ConsoleTestCase
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
    public function basic_run_tool_should_be_configured(string $command): void
    {
        // Create a configuration with a basic run tool
        $configFile = $this->createTempFile(
            <<<'YAML'
                tools:
                  - id: test-command
                    description: "A simple test command tool"
                    type: "run"
                    schema:
                      properties:
                        param:
                          type: "string"
                          description: "Command parameter"
                      required:
                        - param
                    commands:
                      - cmd: "echo"
                        args:
                          - "Hello"
                          - "{{param}}"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $configFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertToolExists('test-command')
            ->assertTool('test-command', [
                'type' => 'run',
                'description' => 'A simple test command tool',
            ])
            ->assertToolSchema(
                'test-command',
                [
                    'param' => [
                        'type' => 'string',
                        'description' => 'Command parameter',
                    ],
                ],
                ['param'],
            )
            ->assertToolCommands('test-command', [
                [
                    'cmd' => 'echo',
                    'args' => ['Hello', '{{param}}'],
                ],
            ]);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function http_tool_should_be_configured(string $command): void
    {
        // Create a configuration with an HTTP tool
        $configFile = $this->createTempFile(
            <<<'YAML'
                tools:
                  - id: test-api
                    description: "A simple HTTP API tool"
                    type: "http"
                    schema:
                      properties:
                        token:
                          type: "string"
                          description: "API token"
                        query:
                          type: "string"
                          description: "Search query"
                      required:
                        - token
                    requests:
                      - url: "https://api.example.com/data"
                        method: "GET"
                        headers:
                          Content-Type: "application/json"
                          Authorization: "Bearer {{token}}"
                        query:
                          q: "{{query}}"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $configFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertToolExists('test-api')
            ->assertTool('test-api', [
                'type' => 'http',
                'description' => 'A simple HTTP API tool',
            ])
            ->assertToolSchema(
                'test-api',
                [
                    'token' => [
                        'type' => 'string',
                        'description' => 'API token',
                    ],
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query',
                    ],
                ],
                ['token'],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function multiple_tools_should_be_configured(string $command): void
    {
        // Create a configuration with multiple tools
        $configFile = $this->createTempFile(
            <<<'YAML'
                tools:
                  - id: command-tool
                    description: "Command execution tool"
                    type: "run"
                    commands:
                      - cmd: "ls"
                        args:
                          - "-la"
                
                  - id: api-tool
                    description: "API interaction tool"
                    type: "http"
                    requests:
                      - url: "https://api.example.com/users"
                        method: "GET"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $configFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertToolCount(2)
            ->assertToolExists('command-tool')
            ->assertToolExists('api-tool')
            ->assertTool('command-tool', [
                'type' => 'run',
                'description' => 'Command execution tool',
            ])
            ->assertTool('api-tool', [
                'type' => 'http',
                'description' => 'API interaction tool',
            ]);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function tool_with_conditional_arguments_should_be_configured(string $command): void
    {
        // Create a configuration with conditional arguments
        $configFile = $this->createTempFile(
            <<<'YAML'
                tools:
                  - id: conditional-args
                    description: "Tool with conditional arguments"
                    type: "run"
                    schema:
                      properties:
                        debug:
                          type: "boolean"
                          description: "Enable debug mode"
                        format:
                          type: "string"
                          description: "Output format"
                    commands:
                      - cmd: "program"
                        args:
                          - name: "--debug"
                            when: "{{debug}}"
                          - name: "--format={{format}}"
                            when: "{{format}}"
                          - "execute"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $configFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertToolExists('conditional-args')
            ->assertTool('conditional-args', [
                'type' => 'run',
                'description' => 'Tool with conditional arguments',
            ])
            ->assertToolSchema(
                'conditional-args',
                [
                    'debug' => [
                        'type' => 'boolean',
                        'description' => 'Enable debug mode',
                    ],
                    'format' => [
                        'type' => 'string',
                        'description' => 'Output format',
                    ],
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function tool_with_environment_variables_should_be_configured(string $command): void
    {
        // Create a configuration with environment variables
        $configFile = $this->createTempFile(
            <<<'YAML'
                tools:
                  - id: env-vars-tool
                    description: "Tool with environment variables"
                    type: "run"
                    env:
                      APP_ENV: "testing"
                      DEBUG: "true"
                    commands:
                      - cmd: "app"
                        args:
                          - "run"
                        env:
                          COMMAND_SPECIFIC: "value"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $configFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertToolExists('env-vars-tool')
            ->assertTool('env-vars-tool', [
                'type' => 'run',
                'description' => 'Tool with environment variables',
                'env' => [
                    'APP_ENV' => 'testing',
                    'DEBUG' => 'true',
                ],
            ]);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function tool_with_complex_schema_should_be_configured(string $command): void
    {
        // Create a configuration with complex schema
        $configFile = $this->createTempFile(
            <<<'YAML'
                tools:
                  - id: complex-schema-tool
                    description: "Tool with complex schema"
                    type: "run"
                    schema:
                      properties:
                        name:
                          type: "string"
                          description: "Project name"
                        version:
                          type: "string"
                          description: "Project version"
                          default: "1.0.0"
                        options:
                          type: "object"
                          properties:
                            debug:
                              type: "boolean"
                              description: "Enable debug mode"
                            logLevel:
                              type: "string"
                              description: "Log level"
                              enum: ["error", "warning", "info", "debug"]
                          required:
                            - logLevel
                      required:
                        - name
                    commands:
                      - cmd: "project"
                        args:
                          - "build"
                          - "{{name}}@{{version}}"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $configFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertToolExists('complex-schema-tool')
            ->assertToolSchema(
                'complex-schema-tool',
                [
                    'name' => [
                        'type' => 'string',
                        'description' => 'Project name',
                    ],
                    'version' => [
                        'type' => 'string',
                        'description' => 'Project version',
                        'default' => '1.0.0',
                    ],
                ],
                ['name'],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function tool_should_be_imported(string $command): void
    {
        // Create a base config file with tools to import
        $baseConfig = $this->createTempFile(
            <<<'YAML'
                tools:
                  - id: imported-tool
                    description: "Tool imported from another file"
                    type: "run"
                    commands:
                      - cmd: "imported"
                        args:
                          - "command"
                YAML,
            '.yaml',
        );

        // Create a main config that imports the base config
        $mainConfig = $this->createTempFile(
            <<<YAML
                import:
                  - path: {$this->getRelativePath($baseConfig)}
                    type: local
                
                tools:
                  - id: local-tool
                    description: "Tool defined in main config"
                    type: "run"
                    commands:
                      - cmd: "local"
                        args:
                          - "command"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfig,
                command: $command,
            )
            ->assertSuccess()
            ->assertImported(path: $this->getRelativePath($baseConfig), type: 'local')
            ->assertToolExists('imported-tool')
            ->assertToolExists('local-tool')
            ->assertToolCount(2);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function invalid_tool_should_be_reported(string $command): void
    {
        // Create a configuration with an invalid tool (missing required fields)
        $configFile = $this->createTempFile(
            <<<'YAML'
                tools:
                  - id: invalid-tool
                    # Missing description
                    type: "run"
                    # Missing commands required for run type
                YAML,
            '.yaml',
        );

        // Execute the command which should fail or report an error
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $configFile,
                command: $command,
            )
            ->assertSuccess()
            ->assertToolCount(0);
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

    private function getRelativePath(string $absolutePath): string
    {
        // Convert absolute path to relative path for use in YAML configurations
        // This ensures the test is independent of the absolute paths on the test system
        return \basename($absolutePath);
    }
}
