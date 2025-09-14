# CTX: The missing link between your codebase and your LLM.

<p>
    <a href="https://docs.ctxgithub.com/"><img alt="Docs" src="https://img.shields.io/badge/docs-green"></a>
    <a href="https://raw.githubusercontent.com/context-hub/generator/refs/heads/main/json-schema.json"><img alt="Json schema" src="https://img.shields.io/badge/json_schema-blue"></a>
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

**CTX** helps developers organize contexts and automatically collect information from their codebase into structured
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

### How it works

**CTX automatically builds structured context documents from:**

- [Code files and directories](https://docs.ctxgithub.com/sources/file-source.html)
- [GitHub repositories](https://docs.ctxgithub.com/sources/github-source.html)
- [Git commit changes and diffs](https://docs.ctxgithub.com/sources/git-diff-source.html)
- W[eb pages (URLs) with CSS selectors](https://docs.ctxgithub.com/sources/url-source.html)
- Plain text
- and more!

**Process:**

- Collects code from specified sources
- Filters files through pattern matching, content search, size, or date criteria
- Applies modifiers (e.g., extracting function signatures without implementation)
- Organizes content into structured markdown documents
- Saves context files ready for LLM consumption
- Optionally serves context through MCP server for direct AI assistant access

> Here is a [Quickstart guide](https://docs.ctxgithub.com/quick-start.html) to get you started with CTX.

### The Problem CTX Solves

**Without such a tool, you would need to:**

- Manually search for all files that were changed
- Copy their contents each time
- Explain the codebase structure repeatedly
- Spend significant time maintaining context consistency

This repetitive process becomes frustrating and can discourage continued development, as you end up doing the same
context-gathering work over and over instead of writing code.

Since CTX describes contexts, this process becomes automated.

---

## Quick Start

Getting started with CTX is straightforward. Follow these simple steps to create your first context file.

## 1. Install CTX

Download and install the tool using our installation script:

```bash
curl -sSL https://raw.githubusercontent.com/context-hub/generator/main/download-latest.sh | sh
```

This installs the `ctx` command to your system (typically in `/usr/local/bin`).

> **Want more options?** See the complete [Installation Guide](https://docs.ctxgithub.com/getting-started.html) for
> alternative installation methods.

## 2. Initialize a Configuration File

Create a new configuration file in your project directory:

```bash
ctx init
```

This generates a `context.yaml` file with a basic structure to get you started.

> Check the [Command Reference](https://docs.ctxgithub.com/getting-started/command-reference.html) for all available
> commands and options.

## 3. Describe Your Project Structure

Edit the generated `context.yaml` file to specify what code or content you want to include.

**For example:**

```yaml
$schema: 'https://raw.githubusercontent.com/context-hub/generator/refs/heads/main/json-schema.json'

documents:
  - description: "User Authentication System"
    outputPath: "auth-context.md"
    sources:
      - type: file
        description: "Authentication Controllers"
        sourcePaths:
          - src/Auth
        filePattern: "*.php"

      - type: file
        description: "Authentication Models"
        sourcePaths:
          - src/Models
        filePattern: "*User*.php"
```

This configuration will gather all PHP files from the `src/Auth` directory and any PHP files containing "User" in their
name from the `src/Models` directory.

#### Need more advanced configuration?

- Learn about [Document Structure](https://docs.ctxgithub.com/documents.html) and properties
- Explore different source types
  like [GitHub](https://docs.ctxgithub.com/sources/github-source.html), [Git Diff](https://docs.ctxgithub.com/sources/git-diff-source.html),
  or [URL](https://docs.ctxgithub.com/sources/url-source.html)
- Apply [Modifiers](https://docs.ctxgithub.com/modifiers.html) to transform your content (like extracting PHP
  signatures)
- Discover how to use [Environment Variables](https://docs.ctxgithub.com/environment-variables.html) in your config
- Use [IDE Integration](https://docs.ctxgithub.com/getting-started/ide-integration.html) for autocompletion and
  validation

## 4. Build the Context

Generate your context file by running:

```bash
ctx
```

CTX will process your configuration and create the specified output file (`auth-context.md` in our example).

> **Tip**: Configure [Logging](https://docs.ctxgithub.com/advanced/logging.html) with `-v`, `-vv`, or `-vvv` for
> detailed output

## 5. Share with an LLM

Upload or paste the generated context file to your favorite LLM (like ChatGPT or Claude). Now you can ask specific
questions about your codebase, and the LLM will have the necessary context to provide accurate assistance.

Example prompt:

> I've shared my authentication system code with you. Can you help me identify potential security vulnerabilities in the
> user registration process?

> **Next steps:** Check
> out [Development with Context Generator](https://docs.ctxgithub.com/advanced/development-process.html) for best
> practices on integrating context generation into your AI-powered development workflow.

That's it! You're now ready to leverage LLMs with proper context about your codebase.

## 6. Connect to Claude AI (Optional)

For a more seamless experience, you can connect Context Generator directly to Claude AI using the MCP server:

There is a built-in MCP server that allows you to connect Claude AI directly to your codebase.

Point the MCP client to the Context Generator server:

```json
{
  "mcpServers": {
    "ctx": {
      "command": "ctx",
      "args": [
        "server",
        "-c",
        "/path/to/your/project"
      ]
    }
  }
}
```

> **Note:** Read more about [MCP Server](https://docs.ctxgithub.com/mcp-server.html#setting-up) for detailed setup
> instructions.

Now you can ask Claude questions about your codebase without manually uploading context files!

## Full Documentation

For complete documentation, including all available features and configuration options, please visit:

https://docs.ctxgithub.com

---

### License

This project is licensed under the MIT License.
