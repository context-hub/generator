# Stage 4: Integration Testing

## Overview

Verify end-to-end functionality using MCP inspector tests. This stage creates test fixtures with actual directory structures and validates that path-based projects work correctly with MCP tools.

Focus areas:
- Tools work correctly with path-based projects
- Override behavior (local over global) works
- Mixed configurations behave as expected
- No regression in existing functionality

## Files

**MODIFY**:
- `tests/src/McpInspector/Tools/MultiProjectToolTest.php` — Add path-based tests

**CREATE** (Test Fixtures):
- Test directory structure created dynamically in tests

## Code References

### MCP Inspector Test Pattern
```
tests/src/McpInspector/Tools/MultiProjectToolTest.php:1-100
```
Shows how to use `$this->inspector->callTool()` and assertion helpers.

### Test Case Base
```
tests/src/McpInspector/McpInspectorTestCase.php:1-150
```
Shows setup, teardown, and helper methods like `createFile()`.

### Fixture Creation
```
tests/src/McpInspector/Tools/FileReadToolTest.php:20-40
```
Shows how to create test files and directories.

## Implementation Details

### Test Strategy

Since MCP inspector tests run against actual file system, we need to:

1. Create temporary project directories in test setup
2. Create context.yaml with path-based project definitions
3. Verify tools work with those projects
4. Clean up after tests

### New Test Methods

```php
<?php

// Add to MultiProjectToolTest.php

#[Test]
public function file_read_works_with_path_based_project(): void
{
    // Arrange - Create a sibling project directory
    $siblingProjectPath = $this->createSiblingProject('test-sibling');
    $this->createFileInProject($siblingProjectPath, 'data.txt', 'Sibling project data');
    
    // Create context.yaml with path-based project
    $this->createContextYamlWithPathProject('sibling', '../test-sibling');
    
    // Restart server to pick up new config
    $this->restartInspector();

    // Act
    $result = $this->inspector->callTool('file-read', [
        'path' => 'data.txt',
        'project' => 'sibling',
    ]);

    // Assert
    $this->assertInspectorSuccess($result);
    $this->assertContentContains($result, 'Sibling project data');
}

#[Test]
public function path_based_project_overrides_global_alias(): void
{
    // This test verifies that if both exist:
    // - Global alias "my-lib" -> /path/a
    // - Local path "my-lib" -> ../local-lib (resolves to /path/b)
    // The local path wins
    
    // Arrange
    $localLibPath = $this->createSiblingProject('local-lib');
    $this->createFileInProject($localLibPath, 'marker.txt', 'LOCAL VERSION');
    
    // Note: Global alias would point elsewhere, but local path overrides
    $this->createContextYamlWithPathProject('my-lib', '../local-lib');
    $this->restartInspector();

    // Act
    $result = $this->inspector->callTool('file-read', [
        'path' => 'marker.txt',
        'project' => 'my-lib',
    ]);

    // Assert
    $this->assertInspectorSuccess($result);
    $this->assertContentContains($result, 'LOCAL VERSION');
}

#[Test]
public function mixed_configuration_path_and_alias(): void
{
    // Arrange - Path-based project
    $pathProjectDir = $this->createSiblingProject('path-project');
    $this->createFileInProject($pathProjectDir, 'path-file.txt', 'From path project');
    
    // Create context.yaml with both path-based and alias-based projects
    $this->createContextYaml([
        'projects' => [
            ['name' => 'local-proj', 'path' => '../path-project'],
            // 'ctx' is a global alias that should still work
        ],
    ]);
    $this->restartInspector();

    // Act - Read from path-based project
    $result = $this->inspector->callTool('file-read', [
        'path' => 'path-file.txt',
        'project' => 'local-proj',
    ]);

    // Assert
    $this->assertInspectorSuccess($result);
    $this->assertContentContains($result, 'From path project');
}

#[Test]
public function invalid_path_project_is_skipped(): void
{
    // Arrange - Create context.yaml with non-existent path
    $this->createContextYaml([
        'projects' => [
            ['name' => 'ghost', 'path' => '../non-existent-dir'],
        ],
    ]);
    $this->restartInspector();

    // Act - Try to use the invalid project
    $result = $this->inspector->callTool('file-read', [
        'path' => 'test.txt',
        'project' => 'ghost',
    ]);

    // Assert - Should fail with "not available" (project wasn't registered)
    $this->assertToolError($result);
    $this->assertContentContains($result, 'not available');
}

// Helper methods to add to the test class

private function createSiblingProject(string $name): string
{
    $path = \dirname($this->getTestRootPath()) . '/' . $name;
    
    if (!\is_dir($path)) {
        \mkdir($path, 0755, true);
    }
    
    // Track for cleanup
    $this->tempDirectories[] = $path;
    
    return $path;
}

private function createFileInProject(string $projectPath, string $file, string $content): void
{
    $filePath = $projectPath . '/' . $file;
    $dir = \dirname($filePath);
    
    if (!\is_dir($dir)) {
        \mkdir($dir, 0755, true);
    }
    
    \file_put_contents($filePath, $content);
}

private function createContextYamlWithPathProject(string $name, string $path): void
{
    $this->createContextYaml([
        'projects' => [
            ['name' => $name, 'path' => $path],
        ],
    ]);
}

private function createContextYaml(array $config): void
{
    $yaml = \Symfony\Component\Yaml\Yaml::dump($config, 4);
    $this->createFile('context.yaml', $yaml);
}
```

### Test Scenarios Summary

| Test | Scenario | Expected |
|------|----------|----------|
| `file_read_works_with_path_based_project` | Use tool with path-based project | Success, reads from resolved path |
| `path_based_project_overrides_global_alias` | Same name, path vs alias | Path wins |
| `mixed_configuration_path_and_alias` | Both types in config | Both work independently |
| `invalid_path_project_is_skipped` | Non-existent path | Project not registered, tool fails |

### Cleanup Considerations

Tests must clean up created directories to avoid test pollution:

```php
protected function tearDown(): void
{
    // Clean up sibling project directories
    foreach ($this->tempDirectories as $dir) {
        $this->recursiveDelete($dir);
    }
    
    parent::tearDown();
}

private function recursiveDelete(string $path): void
{
    if (\is_dir($path)) {
        $files = \array_diff(\scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $this->recursiveDelete($path . '/' . $file);
        }
        \rmdir($path);
    } elseif (\file_exists($path)) {
        \unlink($path);
    }
}
```

## Definition of Done

- [ ] Test `file_read_works_with_path_based_project` passes
- [ ] Test `path_based_project_overrides_global_alias` passes
- [ ] Test `mixed_configuration_path_and_alias` passes
- [ ] Test `invalid_path_project_is_skipped` passes
- [ ] All existing `MultiProjectToolTest` tests still pass
- [ ] Test cleanup works (no leftover directories)
- [ ] Full MCP inspector test suite passes: `./vendor/bin/phpunit --testsuite mcp-inspector`

## Dependencies

**Requires**: Stage 3 (Parser with path resolution)

**Enables**: Stage 5 (Documentation)
