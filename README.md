# CTX: Professional AI Development for Every Developer

> Create LLM-ready contexts in minutes

<p>
    <a href="https://docs.ctxgithub.com/"><img alt="Docs" src="https://img.shields.io/badge/docs-green"></a>
    <a href="https://raw.githubusercontent.com/context-hub/generator/refs/heads/main/json-schema.json"><img alt="Json schema" src="https://img.shields.io/badge/json_schema-blue"></a>
    <a href="https://discord.gg/YmFckwVkQM"><img src="https://img.shields.io/badge/discord-chat-magenta.svg"></a>
    <a href="https://t.me/spiralphp/2504"><img alt="Telegram" src="https://img.shields.io/badge/telegram-blue.svg?logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZD0iTTEyIDI0YzYuNjI3IDAgMTItNS4zNzMgMTItMTJTMTguNjI3IDAgMTIgMCAwIDUuMzczIDAgMTJzNS4zNzMgMTIgMTIgMTJaIiBmaWxsPSJ1cmwoI2EpIi8+PHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik01LjQyNSAxMS44NzFhNzk2LjQxNCA3OTYuNDE0IDAgMCAxIDYuOTk0LTMuMDE4YzMuMzI4LTEuMzg4IDQuMDI3LTEuNjI4IDQuNDc3LTEuNjM4LjEgMCAuMzIuMDIuNDcuMTQuMTIuMS4xNS4yMy4xNy4zMy4wMi4xLjA0LjMxLjAyLjQ3LS4xOCAxLjg5OC0uOTYgNi41MDQtMS4zNiA4LjYyMi0uMTcuOS0uNSAxLjE5OS0uODE5IDEuMjI5LS43LjA2LTEuMjI5LS40Ni0xLjg5OC0uOS0xLjA2LS42ODktMS42NDktMS4xMTktMi42NzgtMS43OTgtMS4xOS0uNzgtLjQyLTEuMjA5LjI2LTEuOTA4LjE4LS4xOCAzLjI0Ny0yLjk3OCAzLjMwNy0zLjIyOC4wMS0uMDMuMDEtLjE1LS4wNi0uMjEtLjA3LS4wNi0uMTctLjA0LS4yNS0uMDItLjExLjAyLTEuNzg4IDEuMTQtNS4wNTYgMy4zNDgtLjQ4LjMzLS45MDkuNDktMS4yOTkuNDgtLjQzLS4wMS0xLjI0OC0uMjQtMS44NjgtLjQ0LS43NS0uMjQtMS4zNDktLjM3LTEuMjk5LS43OS4wMy0uMjIuMzMtLjQ0Ljg5LS42NjlaIiBmaWxsPSIjZmZmIi8+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJhIiB4MT0iMTEuOTkiIHkxPSIwIiB4Mj0iMTEuOTkiIHkyPSIyMy44MSIgZ3JhZGllbnRVbml0cz0idXNlclNwYWNlT25Vc2UiPjxzdG9wIHN0b3AtY29sb3I9IiMyQUFCRUUiLz48c3RvcCBvZmZzZXQ9IjEiIHN0b3AtY29sb3I9IiMyMjlFRDkiLz48L2xpbmVhckdyYWRpZW50PjwvZGVmcz48L3N2Zz4K"></a>
    <a href="https://packagist.org/packages/context-hub/generator"><img alt="License" src="https://img.shields.io/packagist/l/context-hub/generator"></a>
    <a href="https://packagist.org/packages/context-hub/generator"><img alt="Latest Version" src="https://img.shields.io/packagist/v/context-hub/generator"></a>
</p>

![Good morning, LLM](https://github.com/user-attachments/assets/8129f227-dc3f-4671-bc0e-0ecd2f3a1888)

## Table of Contents

- [Quick Start](#quick-start)
- [Full Documentation](https://docs.ctxgithub.com)
- [License](#license)

During development, your codebase constantly evolves. Files are added, modified, and removed. Each time you need to
continue working with an LLM, you need to regenerate context to provide updated information about your current codebase
state.

**CTX** is a context management tool that gives developers full control over what AI sees from their codebase. Instead
of letting AI tools guess what's relevant, you define exactly what context to provide - making your AI-assisted
development more predictable, secure, and efficient.

It helps developers organize contexts and automatically collect information from their codebase into structured
documents that can be easily shared with LLM.

For example, a developer describes what context they need:

```yaml
# context.yaml
documents:
  - description: User Authentication System
    outputPath: auth.md
    sources:
      - type: file
        description: Authentication Controllers
        sourcePaths:
          - src/Auth
        filePattern: "*.php"

      - type: file
        description: Authentication Models
        sourcePaths:
          - src/Models
        filePattern: "*User*.php"

  - description: Another Document
    outputPath: another-document.md
    sources:
      - type: file
        sourcePaths:
          - src/SomeModule
```

This configuration will gather all PHP files from the `src/Auth` directory and any PHP files containing "**User**" in
their name from the `src/Models` directory into a single context file `.context/auth.md`. This file can then be pasted
into a chat session or provided via the built-in [MCP server](https://docs.ctxgithub.com/mcp/).

### Why CTX?

Current AI coding tools automatically scan your entire codebase, which creates several issues:

- **Security risk**: Your sensitive files (env vars, tokens, private code) get uploaded to cloud services
- **Context dilution**: AI gets overwhelmed with irrelevant code, reducing output quality
- **No control**: You can't influence what the AI considers when generating responses
- **Expensive**: Premium tools charge based on how much they scan, not how much you actually need

### The CTX Approach

You know your code better than any AI. CTX puts you in control:

- ✅ Define exactly what context to share - no more, no less
- ✅ Keep sensitive data local - works with local LLMs or carefully curated cloud contexts
- ✅ Generate reusable, shareable contexts - commit configurations to your repo
- ✅ Improve code architecture - designing for AI context windows naturally leads to better modular code
- ✅ Works with any LLM - Claude, ChatGPT, local models, or future tools

## Quick Start

Download and install the tool using our installation script:

```bash
curl -sSL https://raw.githubusercontent.com/context-hub/generator/main/download-latest.sh | sh
```

This installs the `ctx` command to your system (typically in `/usr/local/bin`).

> **Want more options?** See the complete [Installation Guide](https://docs.ctxgithub.com/getting-started.html) for
> alternative installation methods.

## 5-Minute Setup

1. **Initialize your project:**

```bash
cd your-project

ctx init
```

This generates a `context.yaml` file with a basic configuration and shows your project structure, helping you understand
what contexts might be useful.

> Check the [Command Reference](https://docs.ctxgithub.com/getting-started/command-reference.html) for all available
> commands and options.

2. Create your first context:

```bash
ctx generate
```

3. Use with your favorite AI:

- Copy the generated markdown files to your AI chat
- Or use the built-in MCP server with Claude Desktop
- Or process locally with open-source models

## Real-World Use Cases

### 🚀 Onboarding New Team Member

```yaml
# Quick project overview for new developers
documents:
  - description: "Project Architecture Overview"
    outputPath: "docs/architecture.md"
    sources:
      - type: tree
        sourcePaths: [ "src" ]
        maxDepth: 2
      - type: file
        description: "Core interfaces and main classes"
        sourcePaths: [ "src" ]
        filePattern: "*Interface.php"
```

### 📝 Feature Development

```yaml
# Context for developing a new feature
documents:
  - description: "User Authentication System"
    outputPath: "contexts/auth-context.md"
    sources:
      - type: file
        sourcePaths: [ "src/Auth", "src/Models" ]
        filePattern: "*.php"
      - type: git_diff
        description: "Recent auth changes"
        commit: "last-week"
```

### 📚 Documentation Generation

```yaml
# Generate API documentation
documents:
  - description: "API Documentation"
    outputPath: "docs/api.md"
    sources:
      - type: file
        sourcePaths: [ "src/Controllers" ]
        modifiers: [ "php-signature" ]
        contains: [ "@Route", "@Api" ]
```

## Key Features

### 🎯 **Precise Context Control**

- Define exactly which files, directories, or code patterns to include
- Filter by content, file patterns, date ranges, or size
- Apply modifiers to extract only relevant parts (e.g., function signatures)

### 🔒 **Security by Design**

- **Local-first**: Generate contexts locally, choose what to share
- **No automatic uploads**: Unlike tools that scan everything, you control what gets sent
- **Works with local models**: Use completely offline with Ollama, LM Studio, etc.

### 🔄 **Version Control Integration**

- Context configurations are part of your project
- Team members get the same contexts
- Evolve contexts as your codebase changes
- Include git diffs to show recent changes

### 🛠 **Developer Experience**

- **Fast**: Generate contexts in seconds, not minutes of manual copying
- **Flexible**: Works with any AI tool or local model
- **Shareable**: Commit configurations, share with team
- **Extensible**: Plugin system for custom sources and modifiers

## Architecture

CTX follows a simple pipeline:

```
Configuration → Sources → Filters → Modifiers → Output
```

- **Sources**: Where to get content (files, GitHub, git diffs, URLs, etc.)
- **Filters**: What to include/exclude (patterns, content, dates, sizes)
- **Modifiers**: How to transform content (extract signatures, remove comments)
- **Output**: Structured markdown ready for AI consumption

## Connect to Claude Desktop (Optional)

For a more seamless experience, you can connect Context Generator directly to Claude AI using the MCP server.

```bash
# Auto-detect OS and generate configuration
ctx mcp:config
```

This command:

- 🔍 **Auto-detects your OS** (Windows, Linux, macOS, WSL)
- 🎯 **Generates the right config** for your environment
- 📋 **Provides copy-paste ready** JSON for Claude Desktop
- 🧭 **Includes setup instructions** and troubleshooting tips

**Global Registry Mode** (recommended for multiple projects):

```json
{
  "mcpServers": {
    "ctx": {
      "command": "ctx",
      "args": [
        "server"
      ]
    }
  }
}
```

If you prefer manual setup, point the MCP client to the Context Generator server:

```json
{
  "mcpServers": {
    "ctx": {
      "command": "ctx",
      "args": [
        "server",
        "-c",
        "/path/to/project"
      ]
    }
  }
}
```

> **Note:** Read more about [MCP Server](https://docs.ctxgithub.com/mcp/#setting-up) for detailed setup
> instructions and troubleshooting.

Now you can ask Claude questions about your codebase without manually uploading context files!

## Custom Tools

Define project-specific commands that can be executed through the MCP interface:

```yaml
tools:
  - id: run-tests
    description: "Run project tests with coverage"
    type: run
    commands:
      - cmd: npm
        args: [ "test", "--coverage" ]
```

## Full Documentation

For complete documentation, including all available features and configuration options, please visit:

https://docs.ctxgithub.com

## Join Our Community

Join hundreds of developers using CTX for professional AI-assisted coding:

[![Join Discord](https://img.shields.io/discord/1419284404315881633?color=5865F2&label=Join%20Discord&logo=discord&logoColor=white&style=for-the-badge)](https://discord.gg/YmFckwVkQM)

**What you'll find in our Discord:**

- 💡 Share and discover context configurations
- 🛠️ Get help with setup and advanced usage
- 🚀 Showcase your AI development workflows
- 🤝 Connect with like-minded developers
- 📢 First to know about new releases and features

---

### License

This project is licensed under the MIT License.

