# Context Generator for LLM

<p>
    <a href="https://packagist.org/packages/butschster/context-generator"><img alt="License" src="https://img.shields.io/packagist/l/butschster/context-generator"></a>
    <a href="https://packagist.org/packages/butschster/context-generator"><img alt="Latest Version" src="https://img.shields.io/packagist/v/butschster/context-generator"></a>
    <a href="https://packagist.org/packages/butschster/context-generator"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/butschster/context-generator"></a>
</p>

Context Generator is a PHP tool that helps developers build structured context files from various sources:

- code files,
- URLs,
- and plain text.

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

### Using PHAR file

The simplest way to use Context Generator is by downloading the ready-to-use PHAR file directly. No Composer or
dependency installation required!

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

This approach is perfect for quickly trying out the tool or for environments where Composer isn't available.

### Using Composer

Install the package via Composer:

```bash
composer require butschster/context-generator --dev
```

## Requirements

- PHP 8.2 or higher
- PSR-18 HTTP client (for URL sources)
- Symfony Finder component

## Usage

### Basic Usage

Create a configuration file (default: `context.php` or `context.json`) in your project root:

```php
<?php

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\Source\FileSource;
use Butschster\ContextGenerator\Source\TextSource;
use Butschster\ContextGenerator\Source\UrlSource;

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
                filePattern: '*.php',
                excludePatterns: ['tests', 'vendor'],
                showTreeView: true,
            ),
            new TextSource(
                content: "# API Documentation\n\nThis document contains the API source code.",
                description: 'API Documentation Header',
            ),
        ),
    )
    ->register(
        Document::create(
            description: 'Website Content',
            outputPath: 'docs/website.md',
        )
        ->addSource(
            new UrlSource(
                urls: ['https://example.com/docs'],
                description: 'Documentation Website',
                selector: '.main-content',
            ),
        ),
    );
```

or

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
          "excludePatterns": [
            "tests",
            "vendor"
          ],
          "showTreeView": true
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
    }
  ]
}
```

> **Note:** We provide `json-schema.json` for JSON configuration validation. You can use it to validate your JSON
> configuration file or for autocompletion in your IDE.

Then run the command:

```bash
./vendor/bin/context-generator generate
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
    excludePatterns: ['tests', 'vendor'], // Patterns to exclude
    showTreeView: true,                    // Whether to show tree view (default: true)
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

### Combined Sources Example

```php
<?php

declare(strict_types=1);

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\Source\FileSource;
use Butschster\ContextGenerator\Source\TextSource;
use Butschster\ContextGenerator\Source\UrlSource;

return DocumentRegistry::create()
    ->register(
        Document::create(
            description: 'Complete Project Documentation',
            outputPath: 'docs/complete.txt',
        )
        ->addSource(
            new TextSource(
                content: "# Project Overview\nThis document contains the complete documentation...",
                description: 'Introduction',
            ),
            new FileSource(
                sourcePaths: [__DIR__ . '/src'],
                description: 'Source code',
            ),
            new UrlSource(
                urls: ['https://example.com/api-docs'],
                description: 'External API documentation',
            ),
        ),
    );
```

### License

This project is licensed under the MIT License.