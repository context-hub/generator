# Feature Request: Filesystem Tools Exclude Support & FileWrite Refactoring

## Summary

**Goal:** Extend exclude pattern support to all MCP filesystem tools and refactor FileWrite to follow established patterns.

**Status: ✅ COMPLETED**

All 7 filesystem tools now respect exclude patterns consistently.

## Documents

| Document | Description |
|----------|-------------|
| [01-feature-overview.md](./01-feature-overview.md) | Business context, user benefit, current vs expected behavior |
| [02-technical-architecture.md](./02-technical-architecture.md) | Architecture, class diagrams, sequence diagrams, directory structure |
| [03-implementation-stages.md](./03-implementation-stages.md) | Stage-by-stage implementation plan with code references |
| [04-acceptance-criteria.md](./04-acceptance-criteria.md) | Definition of done, testing strategy, edge cases |

## Quick Reference

### Tools Status

| Tool | Has Excludes |
|------|--------------|
| `directory-list` | ✅ |
| `file-search` | ✅ |
| `file-read` | ✅ |
| `file-write` | ✅ |
| `file-replace-content` | ✅ |
| `file-insert-content` | ✅ |
| `file-delete-content` | ✅ |

### Architecture Consistency

| Tool | Handler |
|------|---------|
| `file-search` | ✅ FileSearchHandler |
| `file-read` | ✅ FileReadHandler |
| `file-write` | ✅ FileWriteHandler |
| `file-replace-content` | ✅ FileReplaceContentHandler |
| `file-insert-content` | ✅ FileInsertContentHandler |
| `file-delete-content` | ✅ FileDeleteContentHandler |

## Implementation Checklist

### Stage 1: FileWrite Refactoring ✅
- [x] Create `FileWrite/FileWriteResult.php`
- [x] Create `FileWrite/FileWriteHandler.php` with `ExcludeRegistryInterface`
- [x] Refactor `FileWriteAction.php` to delegate to handler

> **Note:** `FileWriteRequest` kept in original `Dto/` location

### Stage 2: FileRead Excludes ✅
- [x] Add `ExcludeRegistryInterface` to `FileReadHandler` constructor
- [x] Add exclude check in `read()` method

### Stage 3: FileReplaceContent Excludes ✅
- [x] Add `ExcludeRegistryInterface` to `FileReplaceContentHandler` constructor
- [x] Add exclude check in `handle()` method

### Stage 4: FileInsertContent Excludes ✅
- [x] Add `ExcludeRegistryInterface` to `FileInsertContentHandler` constructor
- [x] Add exclude check in `handle()` method

### Stage 5: FileDeleteContent Excludes ✅
- [x] Add `ExcludeRegistryInterface` to `FileDeleteContentHandler` constructor
- [x] Add exclude check in `handle()` method

## Key Code References

### Pattern to Follow

```php
// From FileSearchHandler - the pattern all handlers should use
public function __construct(
    private FilesInterface $files,
    #[Proxy] private DirectoriesInterface $dirs,
    private ExcludeRegistryInterface $excludeRegistry,  // <- Add this
    private LoggerInterface $logger,
) {}

// In handle/read method:
if ($this->excludeRegistry->shouldExclude($relativePath)) {
    return Result::error(
        \sprintf("Path '%s' is excluded by project configuration", $relativePath),
    );
}
```

### Files to Reference

| Purpose | File |
|---------|------|
| Interface to inject | `src/Config/Exclude/ExcludeRegistryInterface.php` |
| Working example | `src/McpServer/Action/Tools/Filesystem/FileSearch/FileSearchHandler.php:23-26, 56-59` |
| Handler pattern | `src/McpServer/Action/Tools/Filesystem/FileReplaceContent/FileReplaceContentHandler.php` |
| Result DTO pattern | `src/McpServer/Action/Tools/Filesystem/FileReplaceContent/FileReplaceResult.php` |

## Error Message Format

All tools must return this exact format for excluded paths:

```
Path '{relativePath}' is excluded by project configuration
```
