# Guidelines for Writing GenerateCommand Tests

This document provides comprehensive guidelines for writing tests for the `GenerateCommand` and related functionality in
the CTX testing suite.

## 1. JSON Response Structure

When using the `generate` command with the `--json` flag, it returns a JSON response with the following structure:

```json
{
  "status": "success|error",
  "message": "Documents compiled successfully|Error message",
  "result": [
    {
      "output_path": "/path/to/output/directory",
      "context_path": "document-name.md",
      "errors": []
    }
    // Additional documents...
  ],
  "imports": [
    {
      "type": "local|url",
      "path": "path/to/imported/config.yaml",
      "pathPrefix": null
      |
      "prefix"
    }
    // Additional imports...
  ],
  "prompts": [
    {
      "id": "prompt-id",
      "type": "prompt|template",
      "description": "Prompt description",
      "schema": {
        "properties": {
          "paramName": {
            "description": "Parameter description"
          }
        },
        "required": [
          "paramName"
        ]
      },
      "messages": [
        {
          "role": "user|assistant",
          "content": "Message content with {{variables}}"
        }
      ],
      "extend": [
        {
          "id": "template-id",
          "arguments": {
            "varName": "value"
          }
        }
      ]
    }
    // Additional prompts...
  ],
  "tools": [
    {
      "id": "tool-id",
      "description": "Tool description",
      "type": "run|http",
      "schema": {
        "properties": {
          "paramName": {
            "type": "string|number|boolean",
            "description": "Parameter description"
          }
        },
        "required": [
          "paramName"
        ]
      },
      "commands": [
        {
          "cmd": "command-name",
          "args": [
            "arg1",
            "arg2"
          ]
        }
      ],
      "requests": [
        {
          "url": "https://api.example.com/endpoint",
          "method": "GET|POST",
          "headers": {
            "Content-Type": "application/json",
            "Authorization": "Bearer {{TOKEN}}"
          }
        }
      ]
    }
    // Additional tools...
  ]
}
```

Understanding this structure is crucial for writing effective tests, as you'll need to validate various parts of this
response.

## 2. Test Organization

Tests for the `GenerateCommand` should be stored in the following location:

```
tests/src/Feature/Console/GenerateCommand/
```

The test files should follow these naming conventions:

- `GenerateCommandTest.php` - General tests for the command
- `FileSourceTest.php` - Tests for file sources
- `GithubSourceTest.php` - Tests for GitHub sources
- `GitlabSourceTest.php` - Tests for GitLab sources
- `UrlSourceTest.php` - Tests for URL sources
- `LocalImportTest.php` - Tests for local imports
- `UrlImportTest.php` - Tests for URL imports
- `PromptsTest.php` - Tests for prompts functionality
- `ToolsTest.php` - Tests for tools functionality

Each test class should extend `Tests\Feature\Console\ConsoleTestCase` to access the common testing functionality.

## 3. Test Fixtures

### What Are Fixtures

Fixtures are predefined test data or configurations used to create a consistent test environment. For the
`GenerateCommand` tests, fixtures include:

- YAML/JSON configuration files
- Mock file structures
- Mock API responses

### Where to Store Fixtures

Fixtures should be stored in the following location:

```
tests/fixtures/Console/GenerateCommand/
```

For specific source types, use subdirectories:

```
tests/fixtures/Console/GenerateCommand/FileSource/
tests/fixtures/Console/GenerateCommand/GithubSource/
tests/fixtures/Console/GenerateCommand/GitlabSource/
tests/fixtures/Console/GenerateCommand/UrlSource/
tests/fixtures/Console/GenerateCommand/Prompts/
tests/fixtures/Console/GenerateCommand/Tools/
```

### Creating Fixtures

For static fixtures, create YAML or JSON files in the appropriate directory:

```yaml
# Example: tests/fixtures/Console/GenerateCommand/simple.yaml
documents:
  - description: "Simple context document"
    outputPath: "context.md"
    sources:
      - type: text
        description: "Simple text source"
        content: "Simple context"
        tag: "simple"
```

For dynamic fixtures, use the `createTempFile()` method in your test:

```php
$configFile = $this->createTempFile(
    <<<'YAML'
        documents:
          - description: "Dynamic fixture"
            outputPath: "dynamic.md"
            sources:
              - type: text
                description: "Dynamic content"
                content: "Generated during test"
                tag: "dynamic"
    YAML,
    '.yaml',
);
```

## 4. Using CompilingResult for Assertions

The `CompilingResult` class provides a fluent interface for making assertions about the command output. Here's how to
use it:

### Basic Usage

```php
$this
    ->buildContext(
        workDir: $this->outputDir,
        configPath: $this->getFixturesDir('Console/GenerateCommand/simple.yaml'),
    )
    ->assertSuccessfulCompiled()
    ->assertContext(
        document: 'context.md',
        contains: [
            '# Simple context document',
            'Simple context',
            '<simple>',
            '</simple>',
        ],
    );
```

### Document Assertions

| Method                                           | Description                           |
|--------------------------------------------------|---------------------------------------|
| `assertSuccessfulCompiled()`                     | Check that compilation was successful |
| `assertNoDocumentsToCompile()`                   | Check that no documents were found    |
| `assertContext(document, contains, notContains)` | Check document content                |
| `assertMissedContext(document)`                  | Check document wasn't generated       |
| `assertDocumentError(document, contains)`        | Check for specific errors             |

### Import Assertions

| Method                       | Description                                |
|------------------------------|--------------------------------------------|
| `assertImported(path, type)` | Check that a specific import was processed |

### Prompt Assertions

| Method                                                     | Description                                      |
|------------------------------------------------------------|--------------------------------------------------|
| `assertPromptExists(id)`                                   | Check that a prompt with the specified ID exists |
| `assertPrompt(id, properties)`                             | Check prompt properties                          |
| `assertPromptMessages(id, messageContents)`                | Check prompt message content                     |
| `assertPromptExtends(id, templateId)`                      | Check that a prompt extends a template           |
| `assertPromptTemplateArguments(id, templateId, arguments)` | Check template arguments                         |
| `assertPromptSchema(id, properties, required)`             | Check prompt schema structure                    |
| `assertPromptCount(count)`                                 | Check the number of prompts                      |
| `assertNoPrompts()`                                        | Check that no prompts were found                 |

### Tool Assertions

| Method                                       | Description                                    |
|----------------------------------------------|------------------------------------------------|
| `assertToolExists(id)`                       | Check that a tool with the specified ID exists |
| `assertTool(id, properties)`                 | Check tool properties                          |
| `assertToolSchema(id, properties, required)` | Check tool schema structure                    |
| `assertToolCommands(id, expectedCommands)`   | Check commands in a run-type tool              |
| `assertToolRequests(id, expectedRequests)`   | Check requests in an http-type tool            |
| `assertToolCount(count)`                     | Check the number of tools                      |
| `assertNoTools()`                            | Check that no tools were found                 |

### Example Prompt Test

```php
public function basic_prompts_should_be_compiled(): void
{
    $this
        ->buildContext(
            workDir: $this->outputDir,
            configPath: $this->getFixturesDir('Console/GenerateCommand/Prompts/basic.yaml'),
        )
        ->assertSuccessfulCompiled()
        ->assertPromptExists('test-prompt')
        ->assertPrompt('test-prompt', [
            'type' => 'prompt',
            'description' => 'A simple test prompt',
        ])
        ->assertPromptMessages('test-prompt', [
            'Hello {{name}}, this is a test prompt.',
        ])
        ->assertPromptSchema('test-prompt', 
            [
                'name' => ['description' => "User's name"],
            ],
            ['name'],
        );
}
```

### Example Tool Test

```php
public function basic_tools_should_be_compiled(): void
{
    $this
        ->buildContext(
            workDir: $this->outputDir,
            configPath: $this->getFixturesDir('Console/GenerateCommand/Tools/basic.yaml'),
        )
        ->assertSuccessfulCompiled()
        ->assertToolExists('test-command')
        ->assertTool('test-command', [
            'type' => 'run',
            'description' => 'A simple test command',
        ])
        ->assertToolSchema('test-command',
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

public function http_tools_should_be_compiled(): void
{
    $this
        ->buildContext(
            workDir: $this->outputDir,
            configPath: $this->getFixturesDir('Console/GenerateCommand/Tools/http.yaml'),
        )
        ->assertSuccessfulCompiled()
        ->assertToolExists('test-api')
        ->assertTool('test-api', [
            'type' => 'http',
            'description' => 'A simple HTTP API tool',
        ])
        ->assertToolRequests('test-api', [
            [
                'method' => 'GET',
                'url' => 'https://api.example.com/data',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer {{token}}',
                ],
            ],
        ]);
}
```

## 5. Testing Best Practices

### Use Data Providers

Use PHPUnit data providers to test the same functionality with different commands:

```php
public static function commandsProvider(): \Generator
{
    yield 'generate' => ['generate'];
    yield 'build' => ['build'];
    yield 'compile' => ['compile'];
}

#[Test]
#[DataProvider('commandsProvider')]
public function test_name(string $command): void
{
    // Test implementation
}
```

### Mock External Services

For tests that interact with external services, use mock classes:

- `MockHttpClient` for URL sources/imports
- `MockGithubClient` for GitHub sources
- `MockGitlabClient` for GitLab sources

Example:

```php
// Register mock
$this->mockGithubClient = new MockGithubClient();
$this->getContainer()->bindSingleton(GithubClientInterface::class, $this->mockGithubClient);

// Configure mock
$this->mockGithubClient->addFile(
    repository: 'owner/repo',
    path: 'src/TestClass.php',
    content: '<?php class TestClass { /* ... */ }',
);
```

### Clean Up Temporary Resources

Use the `setUp()` and `tearDown()` methods to manage temporary resources:

```php
#[\Override]
protected function setUp(): void
{
    parent::setUp();
    $this->outputDir = $this->createTempDir();
}
```

The base `TestCase` class automatically cleans up registered temporary files and directories.

### Test Multiple Scenarios

Test both successful and error scenarios:

- Valid configurations
- Invalid configurations
- Missing resources
- Permission issues
- Edge cases

## 6. Common Test Patterns

### Testing New Sources

When testing a new source type:

1. Create a basic test checking minimum configuration
2. Test with filters (path, content, etc.)
3. Test with tree view enabled
4. Test with error cases (missing required params)
5. Test with different output formats

### Testing Command Options

Test the various command options:

- `--config-file` with different paths
- `--inline` with JSON configuration
- `--work-dir` with different directories
- `--env` with environment variables
- `--json` for JSON output format

### Testing Imports

When testing imports:

1. Test basic import functionality
2. Test path prefixes
3. Test selective document imports
4. Test wildcard patterns
5. Test variable substitution
6. Test error handling (404, invalid configs)

### Testing Tools

When testing tools:

1. Test basic 'run' type tools with simple commands
2. Test tools with command arguments and conditional arguments
3. Test 'http' type tools with different request methods
4. Test tools with environment variables
5. Test tools with complex schemas
6. Test tool error handling (validation errors, execution errors)
7. Test the interaction between tools and other config sections (like variables)
