# Guidelines for PHP Package Testing

## Test Organization and Structure

### Directory Structure

The test structure typically mirrors the main source code structure:

```
└── tests/
    ├── fixtures/            # Test data and sample files
    ├── src/                 # The actual test classes
    │   ├── Fetcher/         # Tests for Fetcher namespace classes
    │   ├── Lib/             # Tests for Lib namespace classes
    │   ├── Source/          # Tests for Source namespace classes
    │   └── TestCase.php     # Base test case class
    └── stubs/               # Mock objects and configurations
        └── config/          # Sample configuration files
```

### Test Naming Conventions

1. **Test Class Namespace**:
    - Use namespace `namespace Tests\` for all test classes
    - Provide attributes for coverage: `#[\PHPUnit\Framework\Attributes\CoversClass(VariableResolver::class)]`

1. **Test Class Names**:
    - Name test classes after the class they're testing, followed by `Test`
    - Example: `FileSourceFetcher` -> `FileSourceFetcherTest`

2. **Test Method Names**:
    - For PHPUnit with PHP 8+, use attributes: `#[\PHPUnit\Framework\Attributes\Test]`. We use PHP8.3
    - Use descriptive names that explain what is being tested:
        - `it_should_not_support_other_sources`
        - `it_should_throw_exception_for_invalid_source_type`
        - `testGetName`, `testApplyWithFileHeaderComment`

3. **Test Method Patterns**:
    - `it_should_[expected behavior]`: For behavior-driven tests
    - `test[MethodName]`: For testing specific methods
    - `test[StateUnderTest]_[ExpectedBehavior]`: For more complex scenarios

## Writing Effective Tests

### Test Case Setup

1. **Use the `setUp` method** to initialize common test dependencies:

```php
protected function setUp(): void
{
    $this->counter = new CharTokenCounter();
    $this->fixturesDir = dirname(__DIR__, 3) . '/fixtures/TokenCounter';
}
```

2. **Initialize test objects in the constructor** for PHP 8+ when using constructor property promotion.

### Handling Final Classes

**Important:** In this project, all concrete classes (those without an `Interface` suffix) are declared as `final` and cannot be mocked or doubled using PHPUnit's mocking system. This is a deliberate architectural decision to enforce proper dependency management and encapsulation.

#### Strategies for Testing with Final Classes:

1. **Use real instances** instead of mocks:

```php
// Instead of:
$this->dirs = $this->createMock(Directories::class);

// Use a real instance:
$this->dirs = new Directories(
    rootPath: '/test/root',
    outputPath: '/test/output',
    configPath: '/test/config',
    jsonSchemaPath: '/test/schema',
    envFilePath: null
);
```

2. **Consider using test-specific factory methods** for complex final classes:

```php
private function createTestDirectories(): Directories
{
    return new Directories(
        rootPath: '/test/root',
        outputPath: '/test/output',
        configPath: '/test/config',
        jsonSchemaPath: '/test/schema'
    );
}
```

3. **For immutable final classes** with methods that return new instances, use the actual methods rather than mocking them:

```php
// The real method will be called and return a new instance
$newDirs = $this->dirs->withConfigPath('/new/config/path');
```

4. **Create test-specific implementations of interfaces** when you need to control behavior:

```php
// Instead of mocking concrete classes, depend on interfaces and create test implementations
class TestFileSystem implements FilesInterface 
{
    // Implement methods with test-specific behavior
    public function exists(string $path): bool
    {
        return in_array($path, $this->existingPaths);
    }
    
    // Add methods to configure test behavior
    public function addExistingPath(string $path): void
    {
        $this->existingPaths[] = $path;
    }
}
```

5. **Use composition in tests** to build complex test scenarios with real objects:

```php
// Create a chain of real objects with controlled test data
$files = new TestFileSystem();
$dirs = new Directories('/test/root', '/test/output', '/test/config', '/test/schema');
$factory = new ConfigLoaderFactory($files, $dirs);
```

#### Creating Test-Specific Subclasses

For complex final classes that you need to control in tests, create test-specific subclasses that extend the final class and override specific methods:

```php
/**
 * Test-specific implementation of ConfigLoaderFactory that returns a predefined loader
 */
class TestConfigLoaderFactory extends ConfigLoaderFactory
{
    private ConfigLoaderInterface $testLoader;
    
    public function __construct(
        FilesInterface $files,
        Directories $dirs,
        ?LoggerInterface $logger = null,
        ConfigLoaderInterface $testLoader
    ) {
        parent::__construct($files, $dirs, $logger);
        $this->testLoader = $testLoader;
    }
    
    public function createForFile(Directories $dirs): ConfigLoaderInterface
    {
        return $this->testLoader;
    }
}
```

Then use this test-specific subclass in your tests:

```php
// Create a mock ConfigLoaderInterface
$loader = $this->createMock(ConfigLoaderInterface::class);
$loader->method('isSupported')->willReturn(true);
$loader->method('loadRawConfig')->willReturn(['key' => 'value']);

// Use the test subclass instead of trying to mock the final class
$testLoaderFactory = new TestConfigLoaderFactory($files, $dirs, $logger, $loader);
$resolver = new ImportResolver($dirs, $files, $testLoaderFactory, $logger);
```

### Test Assertions

1. **Be specific with assertions**:
    - Use `assertEquals()` for exact matches
    - Use `assertSame()` when type and value must match
    - Use `assertTrue()` or `assertFalse()` for boolean checks
    - Use `assertStringContainsString()` for partial string matches

2. **Test edge cases**:
    - Empty inputs: `testApplyWithEmptyKeywords()`
    - Invalid inputs: `testApplyWithInvalidPatterns()`
    - Boundary conditions: `testCalculateDirectoryCountWithEmptyDirectory()`

3. **Test exceptions**:

```php
$this->expectException(\InvalidArgumentException::class);
$this->expectExceptionMessage('Text source must have a "content" string property');

TextSource::fromArray(['content' => 123]);
```

## Using Fixtures

Fixtures are test data files or resources used across multiple tests:

1. **Organization**:
    - Store fixtures in `/tests/fixtures/` directory
    - Group related fixtures in subdirectories (e.g., `TokenCounter/`)
    - Include different file types (empty files, files with special characters)

2. **Usage**:

```php
$filePath = $this->fixturesDir . '/empty.txt';
$this->assertFileExists($filePath);
$count = $this->counter->countFile($filePath);
```

3. **Types of Fixtures**:
    - Sample input files (empty.txt, multibyte.txt)
    - Nested directory structures for testing recursive operations
    - Configuration files in various formats (JSON, YAML)

## Data Provider Patterns

For tests with multiple input/output combinations, use data providers:

```php
#[Test]
#[DataProvider('provideFilePatterns')]
public function testFilePatternValidation(mixed $input, bool $expectedValid): void
{
    // Test implementation
}

public static function provideFilePatterns(): \Generator
{
    yield 'valid string' => ['*.php', true];
    yield 'valid array' => [['*.php', '*.js'], true];
    yield 'invalid type' => [123, false];
    yield 'mixed array' => [['*.php', 123], false];
}
```

## Testing Configuration Files

1. **Store sample configs in `/tests/stubs/config/`**:
    - `valid-config.json` - Basic working configuration
    - `complex-config.json` - Advanced configuration with many options
    - `invalid-json.json` - Malformed JSON for error testing
    - `missing-required-fields.json` - Valid JSON but missing required properties
    - `not-json-file.yaml` - Different format for testing format detection

2. **Test Configuration Loading**:
    - Test valid configurations load correctly
    - Test error handling for invalid configurations
    - Test fallback behaviors

## Testing File Operations

1. **Test with real files when needed** (read-only operations)
2. **Use temporary directories** for write operations:

```php
private string $tempDir;

protected function setUp(): void
{
    $this->tempDir = sys_get_temp_dir() . '/test-' . uniqid();
    mkdir($this->tempDir, 0777, true);
}

protected function tearDown(): void
{
    $this->removeDirectory($this->tempDir);
}

private function removeDirectory(string $dir): void
{
    // Remove temporary files and directories
}
```

## Testing Output Formats

Verify output formatting is correct:

```php
$result = $this->treeBuilder->buildTree($files, $basePath);

$this->assertStringContainsString('└── src', $result);
$this->assertStringContainsString('file1.php', $result);
```