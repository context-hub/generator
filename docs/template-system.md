# Template System Usage

The CTX template system provides intelligent project analysis and template-based configuration generation.

## Commands

### List Templates
```bash
# Show all available templates
ctx template:list

# Show detailed template information  
ctx template:list --detailed

# Filter by tags
ctx template:list --tag php --tag laravel

# Combine options
ctx template:list --detailed --tag php
```

### Initialize with Template
```bash
# Auto-detect project type and use appropriate template
ctx init

# Use specific template
ctx init laravel
ctx init generic-php

# Specify custom config filename
ctx init laravel --config-file=custom-context.yaml
```

## Available Templates

### Laravel Template (`laravel`)
- **Priority**: 100 (high)
- **Tags**: php, laravel, web, framework
- **Detection**: Looks for `composer.json`, `artisan` file, and Laravel directories
- **Generated Documents**:
  - `docs/laravel-overview.md` - Application overview
  - `docs/laravel-structure.md` - Directory structure with tree view

### Generic PHP Template (`generic-php`)  
- **Priority**: 10 (low)
- **Tags**: php, generic
- **Detection**: Looks for `composer.json` and `src` directory
- **Generated Documents**:
  - `docs/php-overview.md` - Project overview
  - `docs/php-structure.md` - Directory structure with tree view

## Analysis Process

When you run `ctx init` without specifying a template:

1. **Project Analysis**: Scans the current directory for project indicators
2. **Template Matching**: Matches detected patterns to available templates  
3. **Confidence Scoring**: Calculates confidence levels for matches
4. **Auto-Selection**: Uses high-confidence matches automatically
5. **Manual Selection**: Shows options for lower-confidence matches

### Analysis Output Example
```
$ ctx init
Analyzing project...
✅ Detected: laravel (confidence: 95%)
Using template: Laravel PHP Framework project template
✅ Config context.yaml created
```

## Extending the System

The template system is designed to be extensible:

- **Custom Analyzers**: Implement `ProjectAnalyzerInterface`
- **Custom Templates**: Implement `TemplateProviderInterface`  
- **Custom Template Sources**: File-based, remote, or database templates

Templates automatically integrate with the existing CTX configuration system, supporting all source types, modifiers, and features.
