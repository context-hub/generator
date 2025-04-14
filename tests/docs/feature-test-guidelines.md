# Guidelines for Using FeatureTestCases to Write Feature Tests

This document provides a comprehensive guide on how to use the `FeatureTestCases` abstract class to create feature tests for the Context Generator.

## 1. Basic Structure

The `FeatureTestCases` abstract class provides a foundation for creating feature tests that validate configuration loading and processing. To write a new feature test, extend this class and implement the required methods:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\YourNamespace;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Tests\Feature\FeatureTestCases;

final class YourFeatureTest extends FeatureTestCases
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('Path/to/your/config.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        // Your assertions here
    }
}
```

## 2. Creating Test Fixtures

Test fixtures are the configuration files your tests will load and validate:

- Place your test fixture YAML files in the `tests/fixtures/` directory
- Organize fixtures by feature (e.g., `tests/fixtures/Prompts/your_test_case.yaml`)
- Use the JSON schema from the project to ensure your YAML is valid
- Include a variety of configurations to test different edge cases

Example YAML fixture:

```yaml
$schema: 'https://raw.githubusercontent.com/context-hub/generator/refs/heads/main/json-schema.json'

variables:
  test_var: Test Value

prompts:
  - id: test-prompt
    description: A test prompt
    messages:
      - role: user
        content: This is a test prompt with {{test_var}}.
```

## 3. Implementing Required Methods

### a. Define `getConfigPath()`

This method should return the path to your test configuration file:

```php
protected function getConfigPath(): string
{
    return $this->getFixturesDir('YourFeature/your_config.yaml');
}
```

- Use `getFixturesDir()` to get the base fixtures directory
- Provide a relative path to your YAML file

### b. Implement `assertConfigItems()`

This is where your test assertions go. You'll receive:

- A `DocumentCompiler` instance
- A `ConfigRegistryAccessor` instance which gives you access to the parsed configuration

```php
protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
{
    // Get items from the configuration
    $prompts = $config->getPrompts();
    
    // Assert that expected items exist
    $this->assertTrue($prompts->has('test-prompt'));
    
    // Get a specific item and assert its properties
    $prompt = $prompts->get('test-prompt');
    $this->assertSame('A test prompt', $prompt->prompt->description);
    
    // Assert message content
    $this->assertCount(1, $prompt->messages);
    $this->assertStringContainsString('Test Value', $prompt->messages[0]->content->text);
}
```

## 4. Additional Testing Capabilities

### Testing Error Cases

If you expect your configuration to throw an exception during parsing, override the `compile()` method:

```php
#[Test]
public function compile(): void
{
    $this->expectException(PromptParsingException::class);
    $this->expectExceptionMessage('expected error message');
    
    parent::compile();
}
```

### Creating Temporary Files for Sub-tests

For testing specific error cases that would prevent the entire configuration from loading:

```php
#[Test]
public function testSpecificErrorCase(): void
{
    $configYaml = <<<YAML
    prompts:
      - id: error-prompt
        # Problematic configuration here
    YAML;
    
    $tempFile = $this->createTempFile($configYaml, '.yaml');
    
    $this->expectException(ExpectedException::class);
    
    // Test code that would load this configuration
}
```

## 5. Best Practices for Feature Tests

1. **Test One Aspect Per Class**: Create separate test classes for different aspects (basic functionality, error handling, edge cases)

2. **Comprehensive Assertions**: Don't just check that a configuration loads; validate all important properties

3. **Test Edge Cases**: Include tests for:
   - Minimal configurations (only required fields)
   - Complex configurations (all possible fields)
   - Error cases (invalid configurations)
   - Boundary conditions

4. **Readable Test Names**: Use descriptive test class and method names

5. **Clear Fixtures**: Add comments in your YAML fixtures to explain what you're testing

6. **Isolated Tests**: Each test should be independent and not rely on state from other tests

## 6. Example Test Cases

Here are examples of different types of feature tests:

### Basic Functionality Test

Tests that valid configurations are correctly parsed:

```php
final class BasicPromptTest extends FeatureTestCases
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('Prompts/basic_prompts.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        $prompt = $config->getPrompts()->get('test-prompt');
        $this->assertSame('test-prompt', $prompt->id);
        // More assertions...
    }
}
```

### Error Case Test

Tests that invalid configurations throw the expected exceptions:

```php
final class ErrorPromptTest extends FeatureTestCases
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('Prompts/error_prompts.yaml');
    }

    #[Test]
    public function compile(): void
    {
        $this->expectException(PromptParsingException::class);
        parent::compile();
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        // This should not be reached due to the exception
    }
}
```

## 7. Common Assertions for Prompt Tests

When testing prompts specifically, here are common assertions:

```php
// Test basic prompt properties
$this->assertSame('prompt-id', $prompt->id);
$this->assertSame('Description', $prompt->prompt->description);
$this->assertSame(PromptType::Prompt, $prompt->type);

// Test message content
$this->assertCount(2, $prompt->messages);
$this->assertSame(Role::User, $prompt->messages[0]->role);
$this->assertSame('Expected message text', $prompt->messages[0]->content->text);

// Test schema/arguments
$this->assertCount(2, $prompt->prompt->arguments);
$foundArg = null;
foreach ($prompt->prompt->arguments as $arg) {
    if ($arg->name === 'expected-arg') {
        $foundArg = $arg;
        break;
    }
}
$this->assertNotNull($foundArg);
$this->assertTrue($foundArg->required);

// Test variable replacement
$this->assertStringContainsString('replaced value', $prompt->messages[0]->content->text);
```
