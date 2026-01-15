# Exclude System Guide

## Overview

The exclude system allows you to filter out files and directories from CTX operations. When configured, excluded paths
are hidden from MCP tool results (like `directory-list`) and document generation sources.

## Configuration

Add an `exclude` section to your `context.yaml`:

```yaml
exclude:
  patterns:
    - ".env*"           # Glob pattern - matches .env, .env.local, .env.production
    - "*.log"           # All log files
    - "*.tmp"           # Temporary files
    - "**/*.bak"        # Backup files in any directory
  paths:
    - "vendor"          # Exact directory path
    - "node_modules"    # Node dependencies
    - "cache"           # Cache directory
    - ".git"            # Git directory
```

## Pattern Types

### Glob Patterns (`patterns`)

Use glob patterns for flexible matching with wildcards:

| Pattern        | Matches                                 |
|----------------|-----------------------------------------|
| `*.log`        | `app.log`, `error.log`                  |
| `.env*`        | `.env`, `.env.local`, `.env.production` |
| `**/*.tmp`     | `data.tmp`, `cache/session.tmp`         |
| `test?.php`    | `test1.php`, `testA.php`                |
| `{*.md,*.txt}` | `README.md`, `notes.txt`                |

Supported wildcards:

- `*` - matches any characters except `/`
- `**` - matches any characters including `/`
- `?` - matches single character
- `[abc]` - matches character set
- `{a,b}` - matches alternatives

### Path Exclusions (`paths`)

Use paths for exact directory/file matching:

```yaml
exclude:
  paths:
    - "vendor"              # Excludes vendor/ and all contents
    - "tests/fixtures"      # Excludes specific subdirectory
    - "config/secrets.php"  # Excludes specific file
```

Path exclusions match:

- Exact path matches
- Any file/directory within the specified path

## Affected Components

### MCP Tools

The `directory-list` tool respects exclude configuration:

```bash
# Files matching exclude patterns won't appear in results
ctx server
# Then call directory-list tool - excluded files are hidden
```

### Document Generation

Source finders use exclusions when gathering files:

```yaml
documents:
  - description: "Source code"
    outputPath: code.md
    sources:
      - type: file
        sourcePaths: ./src
        # Files matching global excludes are automatically filtered
```

## Source Files Reference

### Core Exclude System

| File                                              | Description                          |
|---------------------------------------------------|--------------------------------------|
| `src/Config/Exclude/ExcludeRegistryInterface.php` | Interface for exclusion registry     |
| `src/Config/Exclude/ExcludeRegistry.php`          | Registry storing exclusion patterns  |
| `src/Config/Exclude/ExcludeParserPlugin.php`      | Parses `exclude` section from config |
| `src/Config/Exclude/ExcludeBootloader.php`        | Registers exclude components         |

### Pattern Matching

| File                                               | Description                              |
|----------------------------------------------------|------------------------------------------|
| `src/Config/Exclude/PatternExclusion.php`          | Glob pattern matching (uses PathMatcher) |
| `src/Config/Exclude/PathExclusion.php`             | Exact path matching                      |
| `src/Config/Exclude/AbstractExclusion.php`         | Base class for exclusions                |
| `src/Config/Exclude/ExclusionPatternInterface.php` | Interface for exclusion patterns         |
| `src/Config/Import/PathMatcher.php`                | Converts glob patterns to regex          |

### MCP Integration

| File                                                            | Description                            |
|-----------------------------------------------------------------|----------------------------------------|
| `src/McpServer/Action/Tools/Filesystem/DirectoryListAction.php` | Uses ExcludeRegistry to filter results |

### Source Finders

| File                                 | Description                       |
|--------------------------------------|-----------------------------------|
| `src/Source/File/SymfonyFinder.php`  | Local file source with exclusions |
| `src/Source/Github/GithubFinder.php` | GitHub source with exclusions     |
| `src/Source/Gitlab/GitlabFinder.php` | GitLab source with exclusions     |

## Tests

| File                                                 | Description                          |
|------------------------------------------------------|--------------------------------------|
| `tests/src/McpInspector/Tools/ExcludeConfigTest.php` | MCP integration tests for exclusions |

## How It Works

```
context.yaml
     │
     ▼
ExcludeParserPlugin.parse()
     │
     ├─► PatternExclusion (for patterns: [...])
     │        │
     │        └─► PathMatcher (glob → regex)
     │
     └─► PathExclusion (for paths: [...])
              │
              └─► String matching
     │
     ▼
ExcludeRegistry
     │
     ▼
shouldExclude(path) → true/false
     │
     ▼
DirectoryListAction / SymfonyFinder / etc.
     │
     └─► Filters out excluded paths from results
```

## Examples

### Hide Sensitive Files

```yaml
exclude:
  patterns:
    - ".env*"
    - "*.pem"
    - "*.key"
  paths:
    - "secrets"
    - "credentials"
```

### Hide Build Artifacts

```yaml
exclude:
  patterns:
    - "*.map"
    - "*.min.js"
    - "*.min.css"
  paths:
    - "dist"
    - "build"
    - ".cache"
```

### Development Exclusions

```yaml
exclude:
  patterns:
    - "*.log"
    - "*.tmp"
    - "*.swp"
  paths:
    - "vendor"
    - "node_modules"
    - ".git"
    - ".idea"
    - ".vscode"
```

## Debugging

Enable debug logging to see which paths are excluded:

```php
// In ExcludeRegistry, logging shows:
$this->logger?->debug('Path excluded by pattern', [
    'path' => $path,
    'pattern' => $pattern->getPattern(),
]);
```

Check registered patterns:

```php
$registry = $container->get(ExcludeRegistryInterface::class);
foreach ($registry->getPatterns() as $pattern) {
    echo $pattern->getPattern() . "\n";
}
```
