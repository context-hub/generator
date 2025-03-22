# Context Generator for LLM

<p>
    <a href="https://docs.ctxgithub.com/"><img alt="Docs" src="https://img.shields.io/badge/docs-green"></a>
    <a href="https://raw.githubusercontent.com/butschster/context-generator/refs/heads/main/json-schema.json"><img alt="Json schema" src="https://img.shields.io/badge/json_schema-blue"></a>
    <a href="https://packagist.org/packages/butschster/context-generator"><img alt="License" src="https://img.shields.io/packagist/l/butschster/context-generator"></a>
    <a href="https://packagist.org/packages/butschster/context-generator"><img alt="Latest Version" src="https://img.shields.io/packagist/v/butschster/context-generator"></a>
</p>

# Context Generator for AI-Powered Development

## What is Context Generator?

Context Generator is a tool designed to solve a common problem when working with LLMs like ChatGPT, Claude: **providing
sufficient context about your codebase.**

> There is an article about Context Generator
> on [Medium](https://medium.com/@butschster/context-not-prompts-2-0-the-evolution-9c4a84214784) that explains the
> motivation behind the project and the problem it solves.

It automates the process of building context files from various sources:

- Code files,
- GitHub repositories,
- Git commit changes and diffs
- Web pages (URLs) with CSS selectors,
- and plain text.

It was created to solve a common problem: **efficiently providing AI language models like ChatGPT, Claude with necessary
context about your codebase.**

## Why You Need This

When working with AI-powered development tools context is everything.

- **Code Refactoring Assistance**: Want AI help refactoring a complex class? Context Generator builds a properly
  formatted document containing all relevant code files.

- **Multiple Iteration Development**: Working through several iterations with an AI helper requires constantly updating
  the context. Context Generator automates this process.

- **Documentation Generation:** Transform your codebase into comprehensive documentation by combining source code with
  custom explanations. Use AI to generate user guides, API references, or developer documentation based on your actual
  code.

## How it works

1. Gathers code from files, directories, GitHub repositories, web pages, or custom text.
2. Targets specific files through pattern matching, content search, size, or date filters
3. Applies optional modifiers (like extracting PHP signatures without implementation details)
4. Organizes content into well-structured markdown documents
5. Saves context files ready to be shared with LLMs

# Installation

We provide two versions of Context Generator:

- a native binary
- a PHAR file.

The native binary is the recommended version, because it does not require PHP to be installed on your system. You can
use it on Linux and MacOS.

The PHAR file can be used on any system with PHP 8.2 or higher.

## Using bash (Recommended)

### Requirements

- Linux or MacOS

The easiest way to install Context Generator is by using our installation script. This automatically downloads the
latest version and sets it up for immediate use.

```bash
# Install to /usr/local/bin (will be added to PATH in most Linux distributions)
curl -sSL https://raw.githubusercontent.com/butschster/context-generator/main/download-latest.sh | sh
```

**What the script does**

- Detects the latest version
- Downloads the binary file from GitHub releases
- Installs it (`ctx`) to your bin directory (default: `/usr/local/bin`)
- Makes it executable

After installation, you can use it by simply running the command to generate context:

```bash
ctx
```

## Simple Configuration Example

Create a `context.yaml` file in your project root:

```yaml
documents:
  - description: API Documentation
    outputPath: docs/api.md
    sources:
      - type: text
        description: API Documentation Header
        content: |
          # API Documentation

          This document contains the API source code.

      - type: file
        description: API Controllers
        sourcePaths:
          - src/Controller
        filePattern: "*.php"

      - type: url
        description: API Reference
        urls:
          - https://api.example.com/docs
```

## Full Documentation

For complete documentation, including all available features and configuration options, please visit:

https://docs.ctxgithub.com

---

### License

This project is licensed under the MIT License.
