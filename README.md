# Context Generator for LLM

<p>
    <a href="https://packagist.org/packages/butschster/context-generator"><img alt="License" src="https://img.shields.io/packagist/l/butschster/context-generator"></a>
    <a href="https://packagist.org/packages/butschster/context-generator"><img alt="Latest Version" src="https://img.shields.io/packagist/v/butschster/context-generator"></a>
    <a href="https://packagist.org/packages/butschster/context-generator"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/butschster/context-generator"></a>
    <a href="https://raw.githubusercontent.com/butschster/context-generator/refs/heads/main/json-schema.json"><img alt="Json schema" src="https://img.shields.io/badge/json_schema-blue"></a>
</p>


Context Generator is a tool designed to solve a common problem when working with LLMs like ChatGPT,
Claude: providing sufficient context about your codebase.

### How it works

It automates the process of building context files from various sources:

- code files,
- GitHub repositories,
- Web pages (URLs) with CSS selectors,
- and plain text.

1. Gathers code from files, directories, GitHub repositories, web pages, or custom text.
2. Targets specific files through pattern matching, content search, size, or date filters
3. Applies optional modifiers (like extracting PHP signatures without implementation details)
4. Organizes content into well-structured markdown documents
5. Saves context files ready to be shared with LLMs

It was created to solve a common problem: efficiently providing AI language models like ChatGPT, Claude with necessary
context about your codebase.

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

- **Document** - is the primary output unit that generator produces. It represents a complete, formatted context
  file that you'll share with an LLM.
- **Source** - represents where content is collected from. Sources are the building blocks of documents, and each source
  provides specific content to be included in the final output.
- **modifiers** - transform source content before it's included in the final document. They provide a way to
  clean up, simplify, or enhance raw content to make it more useful for LLM contexts.

Understanding each component helps you create effective configurations.

Create a `context.json` file in your project root:

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
          "filePattern": [
            "*.php",
            "*.md",
            "*.json"
          ],
          "excludePatterns": [
            "tests",
            "vendor"
          ],
          "showTreeView": true,
          "modifiers": [
            "php-signature"
          ]
        },
        {
          "type": "text",
          "description": "API Documentation Header",
          "content": "# API Documentation\n\nThis document contains the API source code."
        }
      ]
    },
    {
      "description": "Website Content",
      "outputPath": "docs/website.md",
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
    },
    {
      "description": "GitHub Repository",
      "outputPath": "docs/github-repo.md",
      "sources": [
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
      ]
    }
  ]
}
```

> **Note:** We
>
provide [json-schema.json](https://raw.githubusercontent.com/butschster/context-generator/refs/heads/main/json-schema.json)
> for JSON configuration validation. You can use it to validate your JSON configuration file or for autocompletion in
> your IDE.

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

In this example:

- `sourcePaths`: Specifies the directories to scan (can be a single path or an array)
- `filePattern`: Defines which files to include (wildcards supported)
- `notPath`: Specifies directories or patterns to exclude
- `showTreeView`: When true, displays a directory tree visualization
- `modifiers`: Applies transformations to the content (e.g., stripping implementation details)

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

### URL Source

Fetch content from websites:

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

### Text Source

Include custom text content:

```json
{
  "type": "text",
  "description": "Custom Notes",
  "content": "# Project Notes\n\nThis is additional context for the AI."
}
```

## Running Context Generator

Once your configuration is in place, run:

```bash
context-generator generate
```

This will process your configuration and generate the specified documents.

## Environment Variables

You can use environment variables in your configuration using the `${VARIABLE_NAME}` syntax. For example:

```json
{
  "type": "github",
  "repository": "owner/repo",
  "githubToken": "${GITHUB_TOKEN}"
}
```

This will use the value of the `GITHUB_TOKEN` environment variable.

For PHP Developers - Integration Guide

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
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\Source\FileSource;
use Butschster\ContextGenerator\Source\TextSource;
use Butschster\ContextGenerator\Source\UrlSource;
use Butschster\ContextGenerator\Source\GithubSource;

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
use Butschster\ContextGenerator\Source\FileSource;

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
use Butschster\ContextGenerator\Source\GithubSource;

new GithubSource(
    repository: 'owner/repo',             // GitHub repository in format "owner/repo"
    sourcePaths: 'src',                   // Path(s) within the repository (string or array)
    branch: 'main',                       // Branch or tag to fetch from (default: main)
    description: 'Repository files',      // Optional description
    filePattern: '*.php',                 // Pattern to match files (default: *.php)
    excludePatterns: ['tests', 'vendor'], // Patterns to exclude
    showTreeView: true,                   // Whether to show directory tree (default: true)
    githubToken: '${GITHUB_TOKEN}',       // GitHub API token for private repos (can use env vars)
    modifiers: ['php-signature'],         // Optional content modifiers to apply
);
```

### UrlSource

The `Butschster\ContextGenerator\Source\UrlSource` allows you to fetch content from websites and extract specific
sections using CSS selectors.
It also cleans the HTML content to remove unnecessary elements and converts into markdown format.

```php
use Butschster\ContextGenerator\Source\UrlSource;

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
use Butschster\ContextGenerator\Source\TextSource;

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