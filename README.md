# Context Generator

A tool for generating contextual documentation from your codebase. This library helps you create comprehensive
documentation by extracting content from various sources like files, URLs, and text.

## Overview

Context Generator allows you to:

- Create documentation from multiple source types (files, URLs, plain text)
- Clean and process HTML content from web sources
- Organize documentation into logical documents
- Configure your documentation structure using PHP

## Installation

Install the package via Composer:

```bash
composer require butschster/context-generator
```

## Usage

### Basic Usage

Create a configuration file (default: `kb.php`) in your project root:

```php
<?php

declare(strict_types=1);

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\Source\FileSource;

return DocumentRegistry::create()
    ->register(
        Document::create(
            description: 'My Project Documentation',
            outputPath: 'docs/documentation.txt',
        )->addSource(
            new FileSource(
                sourcePaths: [
                    __DIR__ . '/src',
                ],
                filePattern: '*.php',
            ),
        ),
    );
```

Then run the command:

```bash
./vendor/bin/context-generator generate
```

### Example with FileSource

```php
<?php

declare(strict_types=1);

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\Source\FileSource;

return DocumentRegistry::create()
    ->register(
        Document::create(
            description: 'API Controllers',
            outputPath: 'docs/api-controllers.txt',
        )->addSource(
            new FileSource(
                sourcePaths: [
                    __DIR__ . '/src/Controller',
                ],
                description: 'API Controller Classes',
                filePattern: '*Controller.php',
                excludePatterns: [
                    'Test',
                    'Legacy',
                ],
            ),
        ),
    );
```

### Example with UrlSource

```php
<?php

declare(strict_types=1);

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\Source\UrlSource;

return DocumentRegistry::create()
    ->register(
        Document::create(
            description: 'External API Documentation',
            outputPath: 'docs/external-apis.txt',
        )->addSource(
            new UrlSource(
                urls: [
                    'https://api.example.com/docs',
                    'https://dev.example.com/api/reference',
                ],
                description: 'Third-party API documentation',
            ),
        ),
    );
```

### Example with TextSource

```php
<?php

declare(strict_types=1);

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\Source\TextSource;

return DocumentRegistry::create()
    ->register(
        Document::create(
            description: 'Project Overview',
            outputPath: 'docs/overview.txt',
        )->addSource(
            new TextSource(
                content: <<<TEXT
                # Project Goals
                This project aims to provide a robust solution for...
                
                ## Key Features
                - Feature 1: Description
                - Feature 2: Description
                TEXT,
                description: 'Project goals and key features',
            ),
        ),
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

## Configuration

### Source Types

#### FileSource

Creates documentation from files and directories.

| Parameter       | Type          | Description                           | Default  |
|-----------------|---------------|---------------------------------------|----------|
| sourcePaths     | string\|array | Paths to source files or directories  | Required |
| description     | string        | Human-readable description            | ''       |
| filePattern     | string        | Pattern to match files (glob pattern) | '*.php'  |
| excludePatterns | array         | Patterns to exclude files             | []       |

#### UrlSource

Creates documentation from web URLs.

| Parameter   | Type   | Description                | Default  |
|-------------|--------|----------------------------|----------|
| urls        | array  | URLs to fetch content from | Required |
| description | string | Human-readable description | ''       |

#### TextSource

Creates documentation from plain text.

| Parameter   | Type   | Description                | Default  |
|-------------|--------|----------------------------|----------|
| content     | string | Text content               | Required |
| description | string | Human-readable description | ''       |

### Document

Represents a single documentation file with multiple sources.

| Parameter   | Type   | Description                         | Default  |
|-------------|--------|-------------------------------------|----------|
| description | string | Human-readable document description | Required |
| outputPath  | string | Path where to write the output      | Required |

### Command Options

When running the command, you can specify these options:

| Option            | Description             | Default                  |
|-------------------|-------------------------|--------------------------|
| --config-name, -c | Configuration file name | 'kb.php'                 |
| --output-path, -o | Output directory path   | 'runtime/knowledge-base' |

## HTML Cleaning

When using UrlSource, the HTML content is cleaned to extract meaningful text:

- Removes script, style, nav, footer, and other non-content elements
- Cleans up elements with IDs/classes like 'ad', 'banner', 'sidebar', etc.
- Extracts content from main content containers
- Normalizes whitespace and cleans up the resulting text

### Coding Standards

- Follow PSR-12 coding standards
- Write unit tests for new features
- Keep documentation up-to-date
