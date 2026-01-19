# Acceptance Criteria

## Definition of Done

### Stage 1: FileWrite Refactoring ✅

- [x] `FileWrite/` directory structure created
- [x] `FileWriteResult` class implemented with `success()` and `error()` factory methods
- [x] `FileWriteHandler` class implemented with:
  - [x] `ExcludeRegistryInterface` injected
  - [x] Exclude check performed before any file operation
  - [x] All original functionality preserved
- [x] `FileWriteAction` refactored to delegate to handler
- [x] Excluded paths return appropriate error

> **Note:** `FileWriteRequest` kept in original `Dto/` location (not moved)

### Stage 2: FileRead Excludes ✅

- [x] `ExcludeRegistryInterface` added to `FileReadHandler` constructor
- [x] Exclude check added before file existence check
- [x] Single file read respects excludes
- [x] Multi-file read skips excluded files (via handler delegation)
- [x] Excluded paths return appropriate error

### Stage 3: FileReplaceContent Excludes ✅

- [x] `ExcludeRegistryInterface` added to `FileReplaceContentHandler` constructor
- [x] Exclude check added at beginning of `handle()` method
- [x] Excluded paths return appropriate error

### Stage 4: FileInsertContent Excludes ✅

- [x] `ExcludeRegistryInterface` added to `FileInsertContentHandler` constructor
- [x] Exclude check added at beginning of `handle()` method
- [x] Excluded paths return appropriate error

### Stage 5: FileDeleteContent Excludes ✅

- [x] `ExcludeRegistryInterface` added to `FileDeleteContentHandler` constructor
- [x] Exclude check added at beginning of `handle()` method
- [x] Excluded paths return appropriate error

---

## Measurable Success Criteria

### Functional Requirements

| ID | Requirement | Verification |
|----|-------------|--------------|
| FR-1 | All filesystem tools respect exclude patterns | Manual test with `.env` excluded |
| FR-2 | Excluded paths return consistent error message | Check error format across all tools |
| FR-3 | Non-excluded paths work normally | Regression test existing functionality |
| FR-4 | FileWrite follows Action/Handler pattern | Code review |

### Error Message Format

All tools must return errors in this format when accessing excluded paths:

```
Path '{relativePath}' is excluded by project configuration
```

### Test Coverage

| Component | Required Coverage |
|-----------|-------------------|
| `FileWriteHandler` | 100% of public methods |
| `FileWriteResult` | 100% of factory methods |
| `FileReadHandler` (exclude logic) | New exclude path tested |
| `FileReplaceContentHandler` (exclude logic) | New exclude path tested |
| `FileInsertContentHandler` (exclude logic) | New exclude path tested |
| `FileDeleteContentHandler` (exclude logic) | New exclude path tested |

---

## Testing Strategy

### Unit Tests

#### FileWriteHandler Tests

```
tests/Unit/McpServer/Action/Tools/Filesystem/FileWrite/
├── FileWriteHandlerTest.php
└── FileWriteResultTest.php
```

**Test Cases:**
- `test_write_to_excluded_path_returns_error`
- `test_write_to_directory_returns_error`
- `test_write_creates_parent_directory_when_enabled`
- `test_write_does_not_create_directory_when_disabled`
- `test_successful_write_returns_bytes_written`
- `test_write_failure_returns_error`

#### Handler Exclude Tests (for each handler)

**Test Cases:**
- `test_operation_on_excluded_path_returns_error`
- `test_operation_on_excluded_pattern_returns_error`
- `test_operation_on_allowed_path_succeeds`

### Integration Tests

**Scenarios:**
1. Configure excludes in `context.yaml`
2. Start MCP server
3. Attempt operations on excluded paths via all tools
4. Verify consistent error responses

### Edge Cases

| Edge Case | Expected Behavior |
|-----------|-------------------|
| Path matches multiple exclude patterns | Still excluded (first match wins) |
| Nested path under excluded directory | Excluded |
| Path with special characters | Pattern matching works correctly |
| Empty exclude list | All paths allowed |
| Glob pattern `*.env*` | Matches `.env`, `.env.local`, etc. |

---

## Implementation Considerations

### Potential Challenges

1. **Namespace changes for FileWrite** - May require updates to any code importing the old path
2. **Constructor signature changes** - DI container handles this, but tests need updates
3. **Consistent error messages** - All handlers must use identical format

### Performance Considerations

- `shouldExclude()` is O(n) where n = number of patterns
- For most projects, n < 20, so negligible impact
- Pattern matching uses simple string/glob operations

### Security Concerns

- Exclude patterns are defined in project config (trusted source)
- No user input directly affects exclude patterns
- Relative paths prevent path traversal attacks

### Backward Compatibility

- All existing functionality preserved
- Error messages are new (excluded paths previously allowed)
- No breaking changes to public API

---

## Code References

### Exclude Infrastructure

| File | Lines | Relevance |
|------|-------|-----------|
| `src/Config/Exclude/ExcludeRegistryInterface.php` | 1-25 | Interface to inject in all handlers |
| `src/Config/Exclude/ExcludeRegistry.php` | 40-55 | `shouldExclude()` implementation |
| `src/Config/Exclude/PathExclusion.php` | 1-40 | Exact path matching |
| `src/Config/Exclude/PatternExclusion.php` | 1-50 | Glob pattern matching |

### Pattern Examples (working implementations)

| File | Lines | Relevance |
|------|-------|-----------|
| `src/McpServer/Action/Tools/Filesystem/DirectoryListAction.php` | 38, 103-106 | Exclude check in loop |
| `src/McpServer/Action/Tools/Filesystem/FileSearch/FileSearchHandler.php` | 23-26, 56-59 | Exclude check pattern |

### Files Modified ✅

| File | Change |
|------|--------|
| `src/McpServer/Action/Tools/Filesystem/FileWriteAction.php` | Refactored to delegate to handler |
| `src/McpServer/Action/Tools/Filesystem/FileWrite/FileWriteHandler.php` | **NEW** - Core logic with excludes |
| `src/McpServer/Action/Tools/Filesystem/FileWrite/FileWriteResult.php` | **NEW** - Result DTO |
| `src/McpServer/Action/Tools/Filesystem/FileRead/FileReadHandler.php` | Added exclude check (lines 44-50) |
| `src/McpServer/Action/Tools/Filesystem/FileReplaceContent/FileReplaceContentHandler.php` | Added exclude check (lines 36-41) |
| `src/McpServer/Action/Tools/Filesystem/FileInsertContent/FileInsertContentHandler.php` | Added exclude check (lines 39-44) |
| `src/McpServer/Action/Tools/Filesystem/FileDeleteContent/FileDeleteContentHandler.php` | Added exclude check (lines 39-43) |

### Result DTO Patterns

| File | Relevance |
|------|-----------|
| `src/McpServer/Action/Tools/Filesystem/FileReplaceContent/FileReplaceResult.php` | Pattern for `FileWriteResult` |
| `src/McpServer/Action/Tools/Filesystem/FileRead/FileReadResult.php` | Error method signature |
| `src/McpServer/Action/Tools/Filesystem/FileInsertContent/FileInsertResult.php` | Success method pattern |
