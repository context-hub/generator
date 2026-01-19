# Feature Overview

## Description

Extend exclude pattern support to all MCP filesystem tools to provide consistent security and access control across all
file operations.

Currently, exclude patterns (defined in project configuration) only work for:

- `directory-list` tool
- `file-search` tool

This feature will extend exclude support to:

- `file-read` - prevent reading excluded files
- `file-write` - prevent writing to excluded paths
- `file-replace-content` - prevent modifying excluded files
- `file-insert-content` - prevent inserting into excluded files
- `file-delete-content` - prevent deleting from excluded files

## Business Value

1. **Security**: Prevent accidental exposure or modification of sensitive files (`.env`, credentials, secrets)
2. **Consistency**: All filesystem tools behave the same way regarding excludes
3. **Developer Experience**: Clear, predictable behavior across all operations
4. **Configuration Reuse**: Single exclude configuration applies everywhere

## User Benefit

- Configure excludes once, enforced everywhere
- No risk of AI assistant accidentally reading/modifying sensitive files
- Consistent error messages when accessing excluded paths

## Target Audience

- Developers using CTX MCP server for AI-assisted development
- Teams with sensitive files that should never be accessed by AI tools
- Projects with strict file access policies

## Implementation Status

All filesystem tools now respect exclude patterns:

```yaml
# context.yaml
exclude:
  - .env
  - .env.*
  - secrets/
  - vendor/
```

| Tool                   | Respects Excludes | Status |
|------------------------|-------------------|--------|
| `directory-list`       | ✅ Yes             | ✅ Done |
| `file-search`          | ✅ Yes             | ✅ Done |
| `file-read`            | ✅ Yes             | ✅ Done |
| `file-write`           | ✅ Yes             | ✅ Done |
| `file-replace-content` | ✅ Yes             | ✅ Done |
| `file-insert-content`  | ✅ Yes             | ✅ Done |
| `file-delete-content`  | ✅ Yes             | ✅ Done |

## Current Behavior

All tools return clear error when attempting to access excluded paths:

```
Error: Path '.env' is excluded by project configuration
```

## FileWrite Architecture (Completed)

The `FileWriteAction` class has been refactored to follow the established Action/Handler separation pattern:

1. ✅ `FileWrite/FileWriteHandler.php` - Core logic with exclude support
2. ✅ `FileWrite/FileWriteResult.php` - Result DTO
3. ✅ `FileWriteAction.php` - Thin action delegating to handler
