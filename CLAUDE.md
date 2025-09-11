# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **CTX** (Context Generator), a PHP 8.3+ tool for generating contextual documentation from codebases for use with
LLMs. It allows developers to create structured context files from various sources including local files, Git
repositories, GitHub/GitLab repos, web pages, and MCP servers.

## Development Commands

### Primary Commands

- `composer install` - Install dependencies
- `php ctx` or `./ctx` - Run the context generator (main entry point)
- `php ctx init` - Initialize a new context.yaml configuration file
- `php ctx generate` - Generate context files from configuration (alias: `build`, `compile`)
- `php ctx server` - Start the MCP server for Claude AI integration
- `php ctx schema` - Generate JSON schema for configuration validation

### Development & Testing

- `composer test` - Run PHPUnit tests
- `composer test:cc` - Run tests with coverage report
- `composer cs-check` - Check code style with PHP-CS-Fixer (dry run)
- `composer cs-fix` - Fix code style issues
- `composer psalm` - Run Psalm static analysis
- `composer psalm:ci` - Run Psalm with CI formatting
- `composer refactor` - Run Rector for automated refactoring
- `composer refactor:ci` - Run Rector in dry-run mode

### Build Commands

The project uses Box to create PHAR executables:

- Build output goes to `.build/phar/ctx.phar`
- Configuration in `box.json`

## Architecture Overview

### Core Application Structure

- **Spiral Framework**: Built on Spiral PHP framework using dependency injection
- **Console Commands**: Located in `src/Console/` extending `BaseCommand`
- **Bootloaders**: Application services bootstrapped via bootloaders in `src/Application/Bootloader/`
- **Entry Point**: `ctx` script → `app.php` → Spiral application kernel

### Key Components

#### Configuration System (`src/Config/`)

- **YAML-based configuration** with JSON schema validation
- **Import system** supports importing from URLs and local paths
- **Environment variables** supported throughout configuration
- **Modular parsing** with plugin system for extensibility

#### Source System (`src/Source/`)

Multiple source types for gathering content:

- **File sources** - Local files with pattern matching and filtering
- **Git sources** - Git diffs, commits, and repository content
- **GitHub/GitLab sources** - Remote repository integration
- **URL sources** - Web page content with CSS selectors
- **Text sources** - Plain text content
- **Composer sources** - Package information and dependencies
- **Tree sources** - Directory structure visualization

#### Content Processing (`src/Lib/`)

- **Content blocks** - Modular content representation (code, text, comments, etc.)
- **Renderers** - Convert content blocks to markdown output
- **Modifiers** - Transform content (PHP signatures, sanitization, filtering)
- **Token counting** - Track content size for LLM context limits

#### Document Compilation (`src/Document/`)

- **Document compiler** processes configuration and generates output files
- **Error handling** with detailed source-level error reporting
- **Multi-document support** with different output paths

#### MCP Server Integration (`src/McpServer/`)

- **Model Context Protocol server** for direct Claude AI integration
- **Resource and prompt management**
- **Project-based organization** for multi-project support

### Key Patterns

- **Immutable DTOs**: Heavy use of readonly classes and value objects
- **Interface segregation**: Clear interfaces for all major components
- **Factory pattern**: Factories for creating configured instances
- **Registry pattern**: Centralized registration of sources, modifiers, etc.
- **Bootloader pattern**: Spiral-style service registration and configuration

## Configuration Structure

The main configuration file is `context.yaml` with JSON schema at `json-schema.json`. Key sections:

- **documents**: Array of document configurations with sources and output paths
- **sources**: Various content sources (file, git, github, url, text, etc.)
- **modifiers**: Content transformations (php-signature, sanitizer, etc.)
- **import**: Include other configuration files or URLs
- **exclude**: Global exclusion patterns and paths

## Using Context Configuration

The project includes a `context.yaml` file that defines how to generate contextual documentation:

### Generating Context Files

- `ctx` - Generate all context files defined in context.yaml
- `ctx generate` - Same as above (explicit command)

### Current Context Documents

Look at generated context files in `.context/` to see what context files are generated.
The configuration generates these context files:

- `project-structure.md` - Tree view of src/ directory with file sizes
- `core/interfaces.md` - All PHP interface files
- `console/commands.md` - Console commands extending BaseCommand
- `changes.md` - Git diff of unstaged changes

### Context Configuration Features

- **Import system**: Automatically imports `src/**/context.yaml` files and external URL configurations
- **Global exclusions**: Excludes tests, vendor, config files, and development artifacts
- **Modular documents**: Each document targets specific code patterns (interfaces, commands, etc.)
- **Change tracking**: Automatically includes current development changes via git diff

Use `ctx` to generate up-to-date context files before sharing code with LLMs.

## Testing

- Tests located in `tests/src/`
- PHPUnit configuration in `phpunit.xml`
- Coverage reports generated to `.build/phpunit/logs/`

## Code Quality

- **PHP-CS-Fixer** for code style enforcement
- **Psalm** for static analysis with strict type checking
- **Rector** for automated refactoring to modern PHP standards
- **Strict typing**: All files use `declare(strict_types=1)`

## Development Lessons Learned

### Key Observations from Spec-Flow Implementation

#### 1. **PHP Enum Naming Conventions**
- **Mistake**: Initially used `SCREAMING_SNAKE_CASE` for PHP enum cases
- **Correction**: PHP enums should use `camelCase` format (e.g., `FlowStatus::InProgress`)
- **Rule**: Follow modern PHP 8.1+ enum conventions, not legacy constant patterns

#### 2. **Namespace Consistency**  
- **Mistake**: Started with `CTX\SpecFlow\*` namespace
- **Correction**: Must follow existing codebase pattern: `Butschster\ContextGenerator\SpecFlow\*`
- **Rule**: Always check existing namespace patterns before creating new modules

#### 3. **MCP Integration Patterns**
- **Mistake**: Created console commands + shell execution approach
- **Correction**: Use direct PHP action classes with MCP attributes
- **Rule**: CTX uses attribute-based MCP actions, not shell command wrappers
- **Pattern**: `#[Tool(name: 'tool_name')] + #[Post(path: '/tools/tool_name')]`

#### 4. **Tool Provider Anti-Pattern**
- **Mistake**: Created `SpecFlowToolProvider` implementing `ToolProviderInterface`
- **Correction**: CTX auto-discovers tools via attributes, no manual registration needed  
- **Rule**: Let CTX's attribute scanning handle tool discovery automatically