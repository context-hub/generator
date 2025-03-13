# Context Generator for LLM

<p>
    <a href="https://packagist.org/packages/butschster/context-generator"><img alt="License" src="https://img.shields.io/packagist/l/butschster/context-generator"></a>
    <a href="https://packagist.org/packages/butschster/context-generator"><img alt="Latest Version" src="https://img.shields.io/packagist/v/butschster/context-generator"></a>
    <a href="https://packagist.org/packages/butschster/context-generator"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/butschster/context-generator"></a>
    <a href="https://raw.githubusercontent.com/butschster/context-generator/refs/heads/main/json-schema.json"><img alt="Json schema" src="https://img.shields.io/badge/json_schema-blue"></a>
</p>

- [Context Generator for LLM](#context-generator-for-llm)
    - [How it works](#how-it-works)
    - [Why You Need This](#why-you-need-this)
    - [Installation](#installation)
        - [Requirements](#requirements)
        - [Using bash (Recommended)](#using-bash-recommended)
        - [Using PHAR file](#using-phar-file)
    - [Configuration](#configuration)
        - [JSON Configuration](#json-configuration)
    - [Document Properties](#document-properties)
    - [Source Types](#source-types)
        - [File Source](#file-source)
        - [GitHub Source](#github-source)
        - [Git Diff Source](#git-diff-source)
        - [URL Source](#url-source)
        - [Text Source](#text-source)
    - [Modifiers](#modifiers)
        - [PHP Signature Modifier](#php-signature-modifier)
        - [PHP Content Filter Modifier](#php-content-filter-modifier)
    - [Sanitizer Modifier](#sanitizer-modifier)
    - [PHP-Docs (AstDocTransformer) Modifier](#php-docs-astdoctransformer-modifier)
    - [Environment Variables](#environment-variables)
    - [Complete Example](#complete-example)
    - [For PHP Developers - Integration Guide](#for-php-developers---integration-guide)
        - [For PHP Developers. Advanced Usage](#for-php-developers-advanced-usage)
        - [Requirements](#requirements-1)
        - [Basic Usage](#basic-usage-3)
        - [Source Types](#source-types-1)
        - [Content Modifiers](#content-modifiers)
    - [License](#license)

Context Generator is a tool designed to solve a common problem when working with LLMs like ChatGPT,
Claude: providing sufficient context about your codebase.

It automates the process of building context files from various sources:

- code files,
- GitHub repositories,
- Git commit changes and diffs
- Web pages (URLs) with CSS selectors,
- and plain text.

It was created to solve a common problem: efficiently providing AI language models like ChatGPT, Claude with necessary
context about your codebase.

### How it works

1. Gathers code from files, directories, GitHub repositories, web pages, or custom text.
2. Targets specific files through pattern matching, content search, size, or date filters
3. Applies optional modifiers (like extracting PHP signatures without implementation details)
4. Organizes content into well-structured markdown documents
5. Saves context files ready to be shared with LLMs

## Why You Need This

When working with AI-powered development tools context is everything.

- **Code Refactoring Assistance**: Want AI help refactoring a complex class? Context Generator builds a properly
  formatted document containing all relevant code files.

- **Multiple Iteration Development**: Working through several iterations with an AI helper requires constantly updating
  the context. Context Generator automates this process.

- **Documentation Generation:** Transform your codebase into comprehensive documentation by combining source code with
  custom explanations. Use AI to generate user guides, API references, or developer documentation based on your actual
  code.

## Installation

### Requirements

- PHP 8.2 or higher

### Using bash (Recommended)

The easiest way to install Context Generator is by using our installation script. This automatically downloads the
latest version and sets it up for immediate use.

```bash
# Install to /usr/local/bin (will be added to PATH in most Linux distributions)
curl -sSL https://raw.githubusercontent.com/butschster/context-generator/main/download-latest.sh | sh
```

**What the script does**

- Detects the latest version
- Downloads the PHAR file from GitHub releases
- Installs it to your bin directory (default: `/usr/local/bin`)
- Makes it executable

After installation, you can use Context Generator by simply running:

```bash
context-generator
```

If you want to check the version, run:

```bash
context-generator version
```

### Using PHAR file

Alternatively, you can download the ready-to-use PHAR file directly:

Download and make executable:

```bash
wget https://github.com/butschster/context-generator/releases/download/1.0.2/context-generator.phar
chmod +x context-generator.phar
```

Move it to a convenient location for easy access:

```bash
# Option 1: Move to current project
mv context-generator.phar ./context-generator

# Option 2: Move to a directory in your PATH (for system-wide access)
mv context-generator.phar /usr/local/bin/context-generator
```

### Configuration

Configuration is built around three core concepts:

- **Document**: The primary output unit produced by the generator - a complete, formatted context file to share with
  LLMs
- **Source**: Where content is collected from - files, GitHub, URLs, text, or git diffs
- **Modifiers**: Transform source content before inclusion - clean up, simplify, or enhance raw content

### JSON Configuration

Create a `context.json` file in your project root:

> **Note:** We
>
provide [json-schema.json](https://raw.githubusercontent.com/butschster/context-generator/refs/heads/main/json-schema.json)
> for JSON configuration validation. You can use it to validate your JSON configuration file or for autocompletion in
> your IDE.

```json
{
  "documents": [
    {
      "description": "API Documentation",
      "outputPath": "docs/api.md",
      "overwrite": true,
      "sources": [
        {
          "type": "text",
          "description": "API Documentation Header",
          "content": "# API Documentation\n\nThis document contains the API source code."
        },
        {
          "type": "file",
          "description": "API Source Files",
          "sourcePaths": [
            "src/Api"
          ],
          "filePattern": [
            "*.php",
            "*.json"
          ],
          "excludePatterns": [
            "tests",
            "vendor"
          ],
          "showTreeView": true
        }
      ]
    }
  ]
}
```

As you can see it's pretty simple.

## Document Properties

| Property      | Type    | Default  | Description                                |
|---------------|---------|----------|--------------------------------------------|
| `description` | string  | required | Human-readable description of the document |
| `outputPath`  | string  | required | Path where the document will be saved      |
| `overwrite`   | boolean | `true`   | Whether to overwrite existing files        |
| `sources`     | array   | required | List of content sources for this document  |

## Source Types

### File Source

The file source allows you to include files from your local filesystem. It's the most commonly used source type for
analyzing your codebase.

#### Basic Usage

```json
{
  "type": "file",
  "description": "Source Code",
  "sourcePaths": [
    "src"
  ],
  "filePattern": "*.php",
  "notPath": [
    "tests",
    "vendor"
  ],
  "showTreeView": true,
  "modifiers": [
    "php-signature"
  ]
}
```

#### Parameters

| Parameter                        | Type          | Default  | Description                                                              |
|----------------------------------|---------------|----------|--------------------------------------------------------------------------|
| `type`                           | string        | required | Must be `"file"`                                                         |
| `description`                    | string        | `""`     | Human-readable description of the source                                 |
| `sourcePaths`                    | string\|array | required | Path(s) to directory or files to include                                 |
| `filePattern`                    | string\|array | `"*.*"`  | File pattern(s) to match                                                 |
| `notPath` (or `excludePatterns`) | array         | `[]`     | Patterns to exclude files                                                |
| `path`                           | string\|array | `[]`     | Patterns to include only files in specific paths                         |
| `contains`                       | string\|array | `[]`     | Patterns to include only files containing specific content               |
| `notContains`                    | string\|array | `[]`     | Patterns to exclude files containing specific content                    |
| `size`                           | string\|array | `[]`     | Size constraints for files (e.g., `"> 10K"`, `"< 1M"`)                   |
| `date`                           | string\|array | `[]`     | Date constraints for files (e.g., `"since yesterday"`, `"> 2023-01-01"`) |
| `ignoreUnreadableDirs`           | boolean       | `false`  | Whether to ignore unreadable directories                                 |
| `showTreeView`                   | boolean       | `true`   | Whether to display a directory tree visualization                        |
| `modifiers`                      | array         | `[]`     | Content modifiers to apply                                               |

#### Multiple Source Paths

You can include files from multiple directories:

```json
{
  "type": "file",
  "description": "Multiple Source Directories",
  "sourcePaths": [
    "src/Controllers",
    "src/Models",
    "config"
  ],
  "filePattern": "*.php",
  "showTreeView": true
}
```

#### Multiple File Patterns

Include different file types:

```json
{
  "type": "file",
  "description": "Multiple File Types",
  "sourcePaths": [
    "src"
  ],
  "filePattern": [
    "*.php",
    "*.json",
    "*.md"
  ],
  "showTreeView": true
}
```

#### Path-Based Filtering

Target specific subdirectories or files:

```json
{
  "type": "file",
  "description": "Only Controller Files",
  "sourcePaths": [
    "src"
  ],
  "path": "Controller",
  "filePattern": "*.php",
  "showTreeView": true
}
```

This will only include files with "Controller" in their path. You can also use an array:

```json
{
  "type": "file",
  "description": "Controllers and Services",
  "sourcePaths": [
    "src"
  ],
  "path": [
    "Controller",
    "Service"
  ],
  "filePattern": "*.php",
  "showTreeView": true
}
```

#### Content-Based Filtering

Include or exclude files based on their content:

```json
{
  "type": "file",
  "description": "Repository Classes",
  "sourcePaths": [
    "src"
  ],
  "contains": "class Repository",
  "filePattern": "*.php",
  "showTreeView": true
}
```

You can also exclude files containing specific content:

```json
{
  "type": "file",
  "description": "Non-Deprecated Classes",
  "sourcePaths": [
    "src"
  ],
  "notContains": "@deprecated",
  "filePattern": "*.php",
  "showTreeView": true
}
```

Use arrays for multiple patterns:

```json
{
  "type": "file",
  "description": "Service Classes",
  "sourcePaths": [
    "src"
  ],
  "contains": [
    "class Service",
    "implements ServiceInterface"
  ],
  "notContains": [
    "@deprecated",
    "@internal"
  ],
  "filePattern": "*.php",
  "showTreeView": true
}
```

#### Size-Based Filtering

Filter files by their size:

```json
{
  "type": "file",
  "description": "Medium-Sized Files",
  "sourcePaths": [
    "src"
  ],
  "size": [
    "> 1K",
    "< 50K"
  ],
  "filePattern": "*.php",
  "showTreeView": true
}
```

Size modifiers support these formats:

- `k`, `ki` for kilobytes (e.g., `10k`, `5ki`)
- `m`, `mi` for megabytes (e.g., `1m`, `2mi`)
- `g`, `gi` for gigabytes (e.g., `1g`, `1gi`)

Operators include:

- `>`, `>=`, `<`, `<=` for comparisons

#### Date-Based Filtering

Include files based on their modification date:

```json
{
  "type": "file",
  "description": "Recently Modified Files",
  "sourcePaths": [
    "src"
  ],
  "date": "since 2 weeks ago",
  "filePattern": "*.php",
  "showTreeView": true
}
```

Date modifiers support:

- Relative dates: `yesterday`, `last week`, `2 days ago`, etc.
- Absolute dates: `2023-01-01`, `2023/01/01`, etc.
- Operators: `>` (after), `<` (before), `>=`, `<=`, `==`
- Alternative syntax: `since` (for `>`), `until` or `before` (for `<`)

Examples:

```json
{
  "date": "since yesterday"
}
```

```json
{
  "date": [
    "> 2023-05-01",
    "< 2023-06-01"
  ]
}
```

#### Combined Filtering (Advanced Example)

You can combine multiple filters for precise targeting:

```json
{
  "type": "file",
  "description": "Recently Modified Controller Classes",
  "sourcePaths": [
    "src"
  ],
  "filePattern": "*.php",
  "path": "Controller",
  "contains": "namespace App\\Controller",
  "notContains": "@deprecated",
  "size": "< 100K",
  "date": "since 1 month ago",
  "ignoreUnreadableDirs": true,
  "showTreeView": true,
  "modifiers": [
    "php-signature"
  ]
}
```

This will:

1. Scan the `src` directory for PHP files
2. Only include files with "Controller" in their path
3. Only include files containing "namespace App\Controller"
4. Exclude files containing "@deprecated"
5. Only include files smaller than 100KB
6. Only include files modified in the last month
7. Skip directories that can't be read due to permissions
8. Show a directory tree in the output
9. Apply the PHP signature modifier to simplify method implementations

### GitHub Source

Pull files directly from a GitHub repository:

```json
{
  "type": "github",
  "description": "Repository Source Files",
  "repository": "owner/repo",
  "sourcePaths": [
    "src"
  ],
  "branch": "main",
  "filePattern": "*.php",
  "excludePatterns": [
    "tests",
    "vendor"
  ],
  "showTreeView": true,
  "githubToken": "${GITHUB_TOKEN}",
  "modifiers": [
    "php-signature"
  ]
}
```

#### Parameters

| Parameter                        | Type          | Default  | Description                                                                         |
|----------------------------------|---------------|----------|-------------------------------------------------------------------------------------|
| `type`                           | string        | required | Must be `"github"`                                                                  |
| `description`                    | string        | `""`     | Human-readable description of the source                                            |
| `repository`                     | string        | required | GitHub repository in format `"owner/repo"`                                          |
| `sourcePaths`                    | string\|array | required | Path(s) within the repository to include                                            |
| `branch`                         | string        | `"main"` | Branch or tag to fetch from                                                         |
| `filePattern`                    | string\|array | `"*.*"`  | File pattern(s) to match                                                            |
| `excludePatterns`                | array         | `[]`     | Patterns to exclude files                                                           |
| `notPath` (or `excludePatterns`) | array         | `[]`     | Patterns to exclude files                                                           |
| `path`                           | string\|array | `[]`     | Patterns to include only files in specific paths                                    |
| `contains`                       | string\|array | `[]`     | Patterns to include only files containing specific content                          |
| `notContains`                    | string\|array | `[]`     | Patterns to exclude files containing specific content                               |
| `showTreeView`                   | boolean       | `true`   | Whether to display a directory tree visualization                                   |
| `githubToken`                    | string        | `null`   | GitHub API token for private repositories (can use env var pattern `${TOKEN_NAME}`) |
| `modifiers`                      | array         | `[]`     | Content modifiers to apply                                                          |

#### Multiple Source Paths

You can include files from multiple directories:

```json
{
  "type": "github",
  "description": "Multiple Source Directories",
  "sourcePaths": [
    "src/Controllers",
    "src/Models",
    "config"
  ],
  "filePattern": "*.php",
  "showTreeView": true
}
```

#### Multiple File Patterns

Include different file types:

```json
{
  "type": "github",
  "description": "Multiple File Types",
  "sourcePaths": [
    "src"
  ],
  "filePattern": [
    "*.php",
    "*.json",
    "*.md"
  ],
  "showTreeView": true
}
```

#### Path-Based Filtering

Target specific subdirectories or files:

```json
{
  "type": "github",
  "description": "Only Controller Files",
  "sourcePaths": [
    "src"
  ],
  "path": "Controller",
  "filePattern": "*.php",
  "showTreeView": true
}
```

This will only include files with "Controller" in their path. You can also use an array:

```json
{
  "type": "github",
  "description": "Controllers and Services",
  "sourcePaths": [
    "src"
  ],
  "path": [
    "Controller",
    "Service"
  ],
  "filePattern": "*.php",
  "showTreeView": true
}
```

#### Content-Based Filtering

Include or exclude files based on their content:

```json
{
  "type": "github",
  "description": "Repository Classes",
  "sourcePaths": [
    "src"
  ],
  "contains": "class Repository",
  "filePattern": "*.php",
  "showTreeView": true
}
```

You can also exclude files containing specific content:

```json
{
  "type": "github",
  "description": "Non-Deprecated Classes",
  "sourcePaths": [
    "src"
  ],
  "notContains": "@deprecated",
  "filePattern": "*.php",
  "showTreeView": true
}
```

Use arrays for multiple patterns:

```json
{
  "type": "github",
  "description": "Service Classes",
  "sourcePaths": [
    "src"
  ],
  "contains": [
    "class Service",
    "implements ServiceInterface"
  ],
  "notContains": [
    "@deprecated",
    "@internal"
  ],
  "filePattern": "*.php",
  "showTreeView": true
}
```

### Git Diff Source

The source allows you to include changes from Git commits, providing a streamlined way to show recent code changes:

```json
{
  "type": "git_diff",
  "description": "Recent Git Changes",
  "commit": "last",
  "filePattern": "*.php",
  "notPath": [
    "tests",
    "vendor"
  ],
  "path": "src",
  "contains": "class",
  "notContains": "@deprecated",
  "showStats": true
}
```

#### Parameters

| Parameter     | Type          | Default    | Description                                                |
|---------------|---------------|------------|------------------------------------------------------------|
| `type`        | string        | required   | Must be `"git_diff"`                                       |
| `description` | string        | `""`       | Human-readable description of the source                   |
| `repository`  | string        | `"."`      | Path to the git repository                                 |
| `commit`      | string        | `"staged"` | Git commit range or preset                                 |
| `filePattern` | string\|array | `"*.*"`    | File pattern(s) to match                                   |
| `notPath`     | array         | `[]`       | Patterns to exclude files                                  |
| `path`        | string\|array | `[]`       | Patterns to include only files in specific paths           |
| `contains`    | string\|array | `[]`       | Patterns to include only files containing specific content |
| `notContains` | string\|array | `[]`       | Patterns to exclude files containing specific content      |
| `showStats`   | boolean       | `true`     | Whether to show commit stats in output                     |
| `modifiers`   | array         | `[]`       | Content modifiers to apply                                 |
|               |               |            |                                                            |

#### Commit Range Presets

Supports many convenient presets for `commit` parameter:

```json
{
  "type": "git_diff",
  "commit": "last-week",
  "filePattern": "*.php"
}
```

| Preset                | Description                          | Git Command Equivalent                 |     |
|-----------------------|--------------------------------------|----------------------------------------|-----|
| `"last"`              | Last commit                          | `HEAD~1..HEAD`                         |     |
| `"last-2"`            | Last 2 commits                       | `HEAD~2..HEAD`                         |     |
| `"last-3"`            | Last 3 commits                       | `HEAD~3..HEAD`                         |     |
| `"last-5"`            | Last 5 commits                       | `HEAD~5..HEAD`                         |     |
| `"last-10"`           | Last 10 commits                      | `HEAD~10..HEAD`                        |     |
| `"today"`             | Changes from today                   | `HEAD@{0:00:00}..HEAD`                 |     |
| `"last-24h"`          | Changes in last 24 hours             | `HEAD@{24.hours.ago}..HEAD`            |     |
| `"yesterday"`         | Changes from yesterday               | `HEAD@{1.days.ago}..HEAD@{0.days.ago}` |     |
| `"last-week"`         | Changes from last week               | `HEAD@{1.week.ago}..HEAD`              |     |
| `"last-2weeks"`       | Changes from last 2 weeks            | `HEAD@{2.weeks.ago}..HEAD`             |     |
| `"last-month"`        | Changes from last month              | `HEAD@{1.month.ago}..HEAD`             |     |
| `"last-quarter"`      | Changes from last quarter            | `HEAD@{3.months.ago}..HEAD`            |     |
| `"last-year"`         | Changes from last year               | `HEAD@{1.year.ago}..HEAD`              |     |
| `"unstaged"`          | Unstaged changes                     | `` (empty string)                      |     |
| `"staged"`            | Staged changes                       | `--cached`                             |     |
| `"wip"`               | Work in progress (last commit)       | `HEAD~1..HEAD`                         |     |
| `"main-diff"`         | Changes since diverging from main    | `main..HEAD`                           |     |
| `"master-diff"`       | Changes since diverging from master  | `master..HEAD`                         |     |
| `"develop-diff"`      | Changes since diverging from develop | `develop..HEAD`                        |     |
| `"stash"`             | Latest stash                         | `stash@{0}`                            |     |
| `"stash-last"`        | Latest stash                         | `stash@{0}`                            |     |
| `"stash-1"`           | Second most recent stash             | `stash@{1}`                            |     |
| `"stash-2"`           | Third most recent stash              | `stash@{2}`                            |     |
| `"stash-3"`           | Fourth most recent stash             | `stash@{3}`                            |     |
| `"stash-all"`         | All stashes                          | `stash@{0}..stash@{100}`               |     |
| `"stash-latest-2"`    | Latest 2 stashes                     | `stash@{0}..stash@{1}`                 |     |
| `"stash-latest-3"`    | Latest 3 stashes                     | `stash@{0}..stash@{2}`                 |     |
| `"stash-latest-5"`    | Latest 5 stashes                     | `stash@{0}..stash@{4}`                 |     |
| `"stash-before-pull"` | Stash with "before pull" in message  | `stash@{/before pull}`                 |     |
| `"stash-wip"`         | Stash with "WIP" in message          | `stash@{/WIP}`                         |     |
| `"stash-untracked"`   | Stash with "untracked" in message    | `stash@{/untracked}`                   |     |
| `"stash-index"`       | Stash with "index" in message        | `stash@{/index}`                       |     |

#### Advanced Commit Specifications

You can use more specific Git expressions:

```json
{
  "type": "git_diff",
  "repository": ".",
  "commit": "abc1234",
  "filePattern": "*.php"
}
```

```json
{
  "type": "git_diff",
  "repository": ".",
  "commit": "abc1234:path/to/file.php",
  "filePattern": "*.php"
}
```

```json
{
  "type": "git_diff",
  "repository": ".",
  "commit": "v1.0.0..v2.0.0",
  "filePattern": "*.php"
}
```

```json
{
  "type": "git_diff",
  "repository": ".",
  "commit": "since:2023-01-15",
  "filePattern": "*.php"
}
```

```json
{
  "type": "git_diff",
  "repository": ".",
  "commit": "date:2023-01-15",
  "filePattern": "*.php"
}
```

### URL Source

Fetch content from websites with optional CSS selector support.

```json
{
  "type": "url",
  "description": "Documentation Website",
  "urls": [
    "https://example.com/docs"
  ],
  "selector": ".main-content"
}
```

#### Parameters

| Parameter     | Type   | Default  | Description                                                   |
|---------------|--------|----------|---------------------------------------------------------------|
| `type`        | string | required | Must be `"url"`                                               |
| `description` | string | `""`     | Human-readable description of the source                      |
| `urls`        | array  | required | URLs to fetch content from                                    |
| `selector`    | string | `null`   | CSS selector to extract specific content (null for full page) |

### Text Source

Include custom text content like headers, notes, or instructions.

```json
{
  "type": "text",
  "description": "Custom Notes",
  "content": "# Project Notes\n\nThis is additional context for the AI."
}
```

#### Parameters

| Parameter     | Type   | Default  | Description                              |
|---------------|--------|----------|------------------------------------------|
| `type`        | string | required | Must be `"text"`                         |
| `description` | string | `""`     | Human-readable description of the source |
| `content`     | string | required | Text content to include                  |

## Modifiers

Modifiers transform source content before it's included in the final document. They provide a way to clean up, simplify,
or enhance raw content to make it more useful for LLM contexts.

### PHP Signature Modifier

Extracts PHP class signatures without implementation details.

**Identifier**: `"php-signature"`

```json
{
  "documents": [
    {
      "description": "API Documentation",
      "outputPath": "docs/api.md",
      "sources": [
        {
          "type": "file",
          "description": "API Source Files",
          "sourcePaths": [
            "src/Api"
          ],
          "filePattern": "*.php",
          "modifiers": [
            "php-signature"
          ]
        }
      ]
    }
  ]
}
```

This modifier transforms:

```php
class Example 
{
    private $property;
    
    public function doSomething($param)
    {
        // Implementation...
        return $result;
    }
    
    private function helperMethod()
    {
        // Implementation...
    }
}
```

Into:

```php
class Example 
{
    public function doSomething($param) 
    {
        /* ... */
    }
}
```

### PHP Content Filter Modifier

The modifier allows you to selectively include or exclude PHP class elements such as methods, properties, constants, and
annotations based on configurable criteria.

> Please note that this modifier is specifically designed for PHP files and will not work with other file types.

#### Features

- Filter methods, properties, and constants by name or pattern
- Include or exclude elements based on visibility (public, protected, private)
- Control whether method bodies are kept or replaced with placeholders
- Optionally keep or remove documentation comments
- Optionally keep or remove PHP 8 attributes
- Filter elements using regular expression patterns

#### JSON Configuration

```json
{
  "documents": [
    {
      "description": "API Documentation",
      "outputPath": "docs/api.md",
      "sources": [
        {
          "type": "file",
          "description": "API Source Files",
          "sourcePaths": [
            "src/Api"
          ],
          "filePattern": "*.php",
          "modifiers": [
            {
              "name": "php-content-filter",
              "options": {
                "method_visibility": [
                  "public"
                ],
                "exclude_methods": [
                  "__construct"
                ],
                "keep_method_bodies": false
              }
            }
          ]
        }
      ]
    }
  ]
}
```

#### Options

| Option                       | Type    | Default                              | Description                                                                     |
|------------------------------|---------|--------------------------------------|---------------------------------------------------------------------------------|
| `include_methods`            | array   | `[]`                                 | Method names to include (empty means include all unless exclude_methods is set) |
| `exclude_methods`            | array   | `[]`                                 | Method names to exclude                                                         |
| `include_properties`         | array   | `[]`                                 | Property names to include                                                       |
| `exclude_properties`         | array   | `[]`                                 | Property names to exclude                                                       |
| `include_constants`          | array   | `[]`                                 | Constant names to include                                                       |
| `exclude_constants`          | array   | `[]`                                 | Constant names to exclude                                                       |
| `method_visibility`          | array   | `["public", "protected", "private"]` | Method visibilities to include                                                  |
| `property_visibility`        | array   | `["public", "protected", "private"]` | Property visibilities to include                                                |
| `constant_visibility`        | array   | `["public", "protected", "private"]` | Constant visibilities to include                                                |
| `keep_method_bodies`         | boolean | `false`                              | Whether to keep method bodies or replace with placeholders                      |
| `method_body_placeholder`    | string  | `"/* ... */"`                        | Placeholder for method bodies when keep_method_bodies is false                  |
| `keep_doc_comments`          | boolean | `true`                               | Whether to keep doc comments                                                    |
| `keep_attributes`            | boolean | `true`                               | Whether to keep PHP 8+ attributes                                               |
| `include_methods_pattern`    | string  | `null`                               | Regular expression pattern for methods to include                               |
| `exclude_methods_pattern`    | string  | `null`                               | Regular expression pattern for methods to exclude                               |
| `include_properties_pattern` | string  | `null`                               | Regular expression pattern for properties to include                            |
| `exclude_properties_pattern` | string  | `null`                               | Regular expression pattern for properties to exclude                            |

I'd be happy to provide complete documentation for the AstDocTransformer ('php-docs') and ContextSanitizerModifier ('
sanitizer') modifiers to enhance the README. Here's what I'll add to the existing documentation:

## Sanitizer Modifier

It helps you clean up or obfuscate sensitive information in your code before sharing it. It applies configurable
sanitization rules to protect private details.

### Basic Usage

```json
{
  "documents": [
    {
      "description": "Sanitized API Documentation",
      "outputPath": "docs/sanitized-api.md",
      "sources": [
        {
          "type": "file",
          "description": "API Classes",
          "sourcePaths": [
            "src/Auth"
          ],
          "filePattern": "*.php",
          "modifiers": [
            {
              "name": "sanitizer",
              "options": {
                "rules": [
                  {
                    "type": "keyword",
                    "keywords": [
                      "password",
                      "secret",
                      "api_key"
                    ],
                    "replacement": "[REDACTED]"
                  },
                  {
                    "type": "regex",
                    "usePatterns": [
                      "email",
                      "api-key",
                      "jwt"
                    ]
                  }
                ]
              }
            }
          ]
        }
      ]
    }
  ]
}
```

### Rule Types

#### 1. Keyword Removal Rule

Removes or replaces text containing specific keywords.

```json
{
  "type": "keyword",
  "name": "remove-sensitive",
  "keywords": [
    "password",
    "secret",
    "private_key"
  ],
  "replacement": "[REDACTED]",
  "caseSensitive": false,
  "removeLines": true
}
```

| Parameter       | Type    | Default        | Description                                        |
|-----------------|---------|----------------|----------------------------------------------------|
| `name`          | string  | auto-generated | Unique rule identifier                             |
| `keywords`      | array   | required       | List of keywords to search for                     |
| `replacement`   | string  | `"[REMOVED]"`  | Replacement text                                   |
| `caseSensitive` | boolean | `false`        | Whether matching should be case-sensitive          |
| `removeLines`   | boolean | `true`         | Whether to remove entire lines containing keywords |

#### 2. Regex Replacement Rule

Applies regular expression patterns to find and replace content.

```json
{
  "type": "regex",
  "patterns": {
    "/access_token\\s*=\\s*['\"]([^'\"]+)['\"]/": "access_token='[REDACTED]'",
    "/password\\s*=\\s*['\"]([^'\"]+)['\"]/": "password='[REDACTED]'"
  },
  "usePatterns": [
    "credit-card",
    "email",
    "api-key"
  ]
}
```

| Parameter     | Type   | Default        | Description                                   |
|---------------|--------|----------------|-----------------------------------------------|
| `name`        | string | auto-generated | Unique rule identifier                        |
| `patterns`    | object | {}             | Object mapping regex patterns to replacements |
| `usePatterns` | array  | []             | Predefined pattern aliases (see below)        |

#### 3. Comment Insertion Rule

Inserts comments into the code to mark it as sanitized or add disclaimers.

```json
{
  "type": "comment",
  "fileHeaderComment": "This file has been sanitized for security purposes.",
  "classComment": "Sanitized class - sensitive information removed.",
  "methodComment": "Sanitized method - implementation details omitted.",
  "frequency": 10,
  "randomComments": [
    "Sanitized for security",
    "Internal details removed",
    "Sensitive data redacted"
  ]
}
```

| Parameter           | Type    | Default        | Description                                        |
|---------------------|---------|----------------|----------------------------------------------------|
| `name`              | string  | auto-generated | Unique rule identifier                             |
| `fileHeaderComment` | string  | `""`           | Comment to insert at the top of file               |
| `classComment`      | string  | `""`           | Comment to insert before class definitions         |
| `methodComment`     | string  | `""`           | Comment to insert before method definitions        |
| `frequency`         | integer | `0`            | How often to insert random comments (0 = disabled) |
| `randomComments`    | array   | `[]`           | Array of random comments to insert                 |

### Predefined Pattern Aliases

The regex rule type supports these built-in pattern aliases:

| Alias             | Description                 |
|-------------------|-----------------------------|
| `credit-card`     | Credit card numbers         |
| `email`           | Email addresses             |
| `api-key`         | API keys and tokens         |
| `ip-address`      | IP addresses                |
| `jwt`             | JWT tokens                  |
| `phone-number`    | Phone numbers               |
| `password-field`  | Password fields in code     |
| `url`             | URLs                        |
| `social-security` | Social security numbers     |
| `aws-key`         | AWS access keys             |
| `private-key`     | Private key headers         |
| `database-conn`   | Database connection strings |

These modifiers give you powerful tools to both transform your code into well-structured documentation and ensure
sensitive information is properly sanitized before sharing.

## PHP-Docs (AstDocTransformer) Modifier

The `php-docs` modifier transforms PHP code into structured markdown documentation. It parses classes, methods,
properties, and constants to generate API documentation that cannot be converted into code. It helps
you to generate a code for team members, LLMs, or other documentation purposes without
exposing sensitive information or implementation details.

### Basic Usage

```json
{
  "documents": [
    {
      "description": "API Documentation",
      "outputPath": "docs/api.md",
      "sources": [
        {
          "type": "file",
          "description": "API Classes",
          "sourcePaths": [
            "src/Api"
          ],
          "filePattern": "*.php",
          "modifiers": [
            "php-docs"
          ]
        }
      ]
    }
  ]
}
```

### Advanced Configuration

For more control, you can provide configuration options:

```json
{
  "documents": [
    {
      "description": "API Documentation",
      "outputPath": "docs/api.md",
      "sources": [
        {
          "type": "file",
          "description": "API Classes",
          "sourcePaths": [
            "src/Api"
          ],
          "filePattern": "*.php",
          "modifiers": [
            {
              "name": "php-docs",
              "options": {
                "include_private_methods": false,
                "include_protected_methods": true,
                "include_implementations": false,
                "class_heading_level": 2,
                "extract_routes": true
              }
            }
          ]
        }
      ]
    }
  ]
}
```

### Options

| Option                         | Type    | Default | Description                                                      |
|--------------------------------|---------|---------|------------------------------------------------------------------|
| `include_private_methods`      | boolean | `false` | Whether to include private methods in the documentation          |
| `include_protected_methods`    | boolean | `true`  | Whether to include protected methods in the documentation        |
| `include_private_properties`   | boolean | `false` | Whether to include private properties in the documentation       |
| `include_protected_properties` | boolean | `true`  | Whether to include protected properties in the documentation     |
| `include_implementations`      | boolean | `true`  | Whether to include method implementations in code blocks         |
| `include_property_defaults`    | boolean | `true`  | Whether to include property default values                       |
| `include_constants`            | boolean | `true`  | Whether to include class constants                               |
| `code_block_format`            | string  | `"php"` | Language identifier for code blocks                              |
| `class_heading_level`          | integer | `1`     | Heading level for class names (1-6)                              |
| `extract_routes`               | boolean | `true`  | Whether to extract route information from annotations/attributes |
| `keep_doc_comments`            | boolean | `true`  | Whether to preserve PHPDoc comments in the output                |

## Environment Variables

You can use environment variables in your configuration using the `${VARIABLE_NAME}` syntax:

```json
{
  "type": "github",
  "repository": "owner/repo",
  "githubToken": "${GITHUB_TOKEN}"
}
```

This will use the value of the `GITHUB_TOKEN` environment variable.

## Complete Example

A comprehensive configuration example with multiple document types and sources:

```json
{
  "documents": [
    {
      "description": "API Documentation",
      "outputPath": "docs/api.md",
      "sources": [
        {
          "type": "text",
          "description": "Header",
          "content": "# API Documentation\n\nThis document provides an overview of the API."
        },
        {
          "type": "file",
          "description": "API Controllers",
          "sourcePaths": [
            "src/Controller"
          ],
          "filePattern": "*.php",
          "contains": "Controller",
          "notContains": "@deprecated",
          "showTreeView": true,
          "modifiers": [
            "php-signature"
          ]
        },
        {
          "type": "github",
          "description": "API Client Library",
          "repository": "owner/api-client",
          "sourcePaths": [
            "src"
          ],
          "branch": "main",
          "filePattern": "*.php",
          "githubToken": "${GITHUB_TOKEN}"
        }
      ]
    },
    {
      "description": "Recent Changes",
      "outputPath": "docs/recent-changes.md",
      "sources": [
        {
          "type": "git_diff",
          "description": "Changes from last week",
          "repository": ".",
          "commit": "last-week",
          "filePattern": [
            "*.php",
            "*.md"
          ],
          "notPath": [
            "vendor"
          ]
        }
      ]
    },
    {
      "description": "Documentation",
      "outputPath": "docs/docs.md",
      "sources": [
        {
          "type": "url",
          "description": "Documentation Website",
          "urls": [
            "https://example.com/docs"
          ],
          "selector": ".main-content"
        }
      ]
    }
  ]
}
```

# For PHP Developers - Integration Guide

This section is for PHP developers who want to integrate Context Generator into their applications or extend its
functionality.

## For PHP Developers. Advanced Usage

Install via Composer:

```bash
composer require butschster/context-generator --dev
```

## Requirements

- PHP 8.2 or higher
- PSR-18 HTTP client (for URL sources)
- Symfony Finder component

## Basic Usage

Create a PHP configuration file (`context.php`):

```php
<?php

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\Document\DocumentRegistry;
use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\Source\Text\TextSource;

return (new DocumentRegistry())
    ->register(
        Document::create(
            description: 'API Documentation',
            outputPath: 'docs/api.md',
        )
        ->addSource(
            new FileSource(
                sourcePaths: __DIR__ . '/src/Api',
                description: 'API Source Files',
                filePattern: ['*.php', '*.md', '*.json'],
                notPath: ['tests', 'vendor'],
                showTreeView: true,
                modifiers: ['php-signature'],
            ),
            new TextSource(
                content: "# API Documentation\n\nThis document contains the API source code.",
                description: 'API Documentation Header',
            ),
        ),
    );
```

## Source Types

### FileSource

The `Butschster\ContextGenerator\Source\FileSource` allows you to include content from files and directories:

```php
use Butschster\ContextGenerator\Source\File\FileSource;

new FileSource(
    sourcePaths: __DIR__ . '/src',        // Path to directory or file
    description: 'Source Code',           // Optional description
    filePattern: '*.php',                 // File pattern to match (default: *.php)
    notPath: ['tests', 'vendor'], // Patterns to exclude
    showTreeView: true,                    // Whether to show tree view (default: true)
    modifiers: ['php-signature'],         // Optional content modifiers to apply
);
```

### GithubSource

The `Butschster\ContextGenerator\Source\GithubSource` allows you to include content directly from a GitHub repository:

```php
use Butschster\ContextGenerator\Source\Github\GithubSource;

new GithubSource(
    repository: 'owner/repo',             // GitHub repository in format "owner/repo"
    sourcePaths: 'src',                   // Path(s) within the repository (string or array)
    branch: 'main',                       // Branch or tag to fetch from (default: main)
    description: 'Repository files',      // Optional description
    filePattern: '*.php',                 // Pattern to match files (default: *.php)
    notPath: ['tests', 'vendor'], // Patterns to exclude
    showTreeView: true,                   // Whether to show directory tree (default: true)
    githubToken: '${GITHUB_TOKEN}',       // GitHub API token for private repos (can use env vars)
    modifiers: ['php-signature'],         // Optional content modifiers to apply
);
```

### CommitDiffSource

The `Butschster\ContextGenerator\Source\CommitDiffSource` allows you to include git diff content from commits or staged
changes:

```php
use Butschster\ContextGenerator\Source\GitDiff\CommitDiffSource;

new CommitDiffSource(
    repository: __DIR__,                  // Path to git repository (use current directory with __DIR__)
    description: 'Recent changes',        // Optional description
    commit: 'last',                       // Commit range (preset, hash, or expression)
    filePattern: '*.php',                 // Pattern to match files (default: *.php)
    notPath: ['tests', 'vendor'],         // Patterns to exclude
    path: 'src/Controller',               // Path filter to include
    contains: 'namespace App',            // Content pattern to include
    notContains: '@deprecated',           // Content pattern to exclude
    showStats: true,                      // Whether to show git stats (default: true)
);
```

### UrlSource

The `Butschster\ContextGenerator\Source\UrlSource` allows you to fetch content from websites and extract specific
sections using CSS selectors.
It also cleans the HTML content to remove unnecessary elements and converts into markdown format.

```php
use Butschster\ContextGenerator\Source\Url\UrlSource;

new UrlSource(
    urls: ['https://example.com/docs'],  // URLs to fetch
    description: 'Documentation',        // Optional description
    selector: '.main-content'            // Optional CSS selector to extract specific content
);
```

### TextSource

The `Butschster\ContextGenerator\Source\TextSource` allows you to include plain text content like headers or notes or
additional instructions.

```php
use Butschster\ContextGenerator\Source\Text\TextSource;

new TextSource(
    content: <<<TEXT
        # Project Goals
        This project aims to provide a robust solution for...
        
        ## Key Features
        - Feature 1: Description
        - Feature 2: Description
        TEXT,
    description: 'Project goals and key features',
);
```

## Content Modifiers

Content modifiers allow you to transform the source content before it's included in the document. Currently, Context
Generator includes the following modifiers:

### Php files Signature Modifier

The php-signature modifier extracts PHP class signatures without implementation details. This is useful for providing
API documentation without cluttering the context with implementation details.

The modifier will transform:

```php
class Example 
{
    private $property;
    
    public function doSomething($param)
    {
        // Implementation...
        return $result;
    }
    
    private function helperMethod()
    {
        // Implementation...
    }
}
```

Into:

```php
class Example 
{
    public function doSomething($param) 
    {
        /* ... */
    }
}
```

### License

This project is licensed under the MIT License.