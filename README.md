# CTX: Your AI Coding Companion

> MCP-powered development toolkit that gives AI full access to your codebase

<p>
    <a href="https://docs.ctxllm.com"><img alt="Docs" src="https://img.shields.io/badge/docs-green?style=for-the-badge"></a>
    <a href="https://raw.githubusercontent.com/context-hub/generator/refs/heads/main/json-schema.json"><img alt="Json schema" src="https://img.shields.io/badge/json_schema-blue?style=for-the-badge"></a>
    <a href="https://discord.gg/YmFckwVkQM"><img src="https://img.shields.io/badge/discord-chat-magenta.svg?style=for-the-badge"></a>
    <a href="https://github.com/context-hub/generator/releases/latest"><img src="https://img.shields.io/github/downloads/context-hub/generator/total?style=for-the-badge"></a>
    <a href="https://t.me/spiralphp/2504"><img alt="Telegram" src="https://img.shields.io/badge/telegram-blue.svg?style=for-the-badge&logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZD0iTTEyIDI0YzYuNjI3IDAgMTItNS4zNzMgMTItMTJTMTguNjI3IDAgMTIgMCAwIDUuMzczIDAgMTJzNS4zNzMgMTIgMTIgMTJaIiBmaWxsPSJ1cmwoI2EpIi8+PHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik01LjQyNSAxMS44NzFhNzk2LjQxNCA3OTYuNDE0IDAgMCAxIDYuOTk0LTMuMDE4YzMuMzI4LTEuMzg4IDQuMDI3LTEuNjI4IDQuNDc3LTEuNjM4LjEgMCAuMzIuMDIuNDcuMTQuMTIuMS4xNS4yMy4xNy4zMy4wMi4xLjA0LjMxLjAyLjQ3LS4xOCAxLjg5OC0uOTYgNi41MDQtMS4zNiA4LjYyMi0uMTcuOS0uNSAxLjE5OS0uODE5IDEuMjI5LS43LjA2LTEuMjI5LS40Ni0xLjg5OC0uOS0xLjA2LS42ODktMS42NDktMS4xMTktMi42NzgtMS43OTgtMS4xOS0uNzgtLjQyLTEuMjA5LjI2LTEuOTA4LjE4LS4xOCAzLjI0Ny0yLjk3OCAzLjMwNy0zLjIyOC4wMS0uMDMuMDEtLjE1LS4wNi0uMjEtLjA3LS4wNi0uMTctLjA0LS4yNS0uMDItLjExLjAyLTEuNzg4IDEuMTQtNS4wNTYgMy4zNDgtLjQ4LjMzLS45MDkuNDktMS4yOTkuNDgtLjQzLS4wMS0xLjI0OC0uMjQtMS44NjgtLjQ0LS43NS0uMjQtMS4zNDktLjM3LTEuMjk5LS43OS4wMy0uMjIuMzMtLjQ0Ljg5LS42NjlaIiBmaWxsPSIjZmZmIi8+PGRlZnM+PGxpbmVhckdyYWRpZW50IGlkPSJhIiB4MT0iMTEuOTkiIHkxPSIwIiB4Mj0iMTEuOTkiIHkyPSIyMy44MSIgZ3JhZGllbnRVbml0cz0idXNlclNwYWNlT25Vc2UiPjxzdG9wIHN0b3AtY29sb3I9IiMyQUFCRUUiLz48c3RvcCBvZmZzZXQ9IjEiIHN0b3AtY29sb3I9IiMyMjlFRDkiLz48L2xpbmVhckdyYWRpZW50PjwvZGVmcz48L3N2Zz4K"></a>
    <a href="https://github.com/context-hub/generator/actions/workflows/testing.yml"><img src="https://img.shields.io/github/actions/workflow/status/context-hub/generator/testing.yml?style=for-the-badge&label=Tests"></a>
    <a href="https://zread.ai/context-hub/generator" target="_blank"><img src="https://img.shields.io/badge/Ask_Zread-_.svg?style=for-the-badge&color=00b0aa&labelColor=000000&logo=data%3Aimage%2Fsvg%2Bxml%3Bbase64%2CPHN2ZyB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIHZpZXdCb3g9IjAgMCAxNiAxNiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTQuOTYxNTYgMS42MDAxSDIuMjQxNTZDMS44ODgxIDEuNjAwMSAxLjYwMTU2IDEuODg2NjQgMS42MDE1NiAyLjI0MDFWNC45NjAxQzEuNjAxNTYgNS4zMTM1NiAxLjg4ODEgNS42MDAxIDIuMjQxNTYgNS42MDAxSDQuOTYxNTZDNS4zMTUwMiA1LjYwMDEgNS42MDE1NiA1LjMxMzU2IDUuNjAxNTYgNC45NjAxVjIuMjQwMUM1LjYwMTU2IDEuODg2NjQgNS4zMTUwMiAxLjYwMDEgNC45NjE1NiAxLjYwMDFaIiBmaWxsPSIjZmZmIi8%2BCjxwYXRoIGQ9Ik00Ljk2MTU2IDEwLjM5OTlIMi4yNDE1NkMxLjg4ODEgMTAuMzk5OSAxLjYwMTU2IDEwLjY4NjQgMS42MDE1NiAxMS4wMzk5VjEzLjc1OTlDMS42MDE1NiAxNC4xMTM0IDEuODg4MSAxNC4zOTk5IDIuMjQxNTYgMTQuMzk5OUg0Ljk2MTU2QzUuMzE1MDIgMTQuMzk5OSA1LjYwMTU2IDE0LjExMzQgNS42MDE1NiAxMy43NTk5VjExLjAzOTlDNS42MDE1NiAxMC42ODY0IDUuMzE1MDIgMTAuMzk5OSA0Ljk2MTU2IDEwLjM5OTlaIiBmaWxsPSIjZmZmIi8%2BCjxwYXRoIGQ9Ik0xMy43NTg0IDEuNjAwMUgxMS4wMzg0QzEwLjY4NSAxLjYwMDEgMTAuMzk4NCAxLjg4NjY0IDEwLjM5ODQgMi4yNDAxVjQuOTYwMUMxMC4zOTg0IDUuMzEzNTYgMTAuNjg1IDUuNjAwMSAxMS4wMzg0IDUuNjAwMUgxMy43NTg0QzE0LjExMTkgNS42MDAxIDE0LjM5ODQgNS4zMTM1NiAxNC4zOTg0IDQuOTYwMVYyLjI0MDFDMTQuMzk4NCAxLjg4NjY0IDE0LjExMTkgMS42MDAxIDEzLjc1ODQgMS42MDAxWiIgZmlsbD0iI2ZmZiIvPgo8cGF0aCBkPSJNNCAxMkwxMiA0TDQgMTJaIiBmaWxsPSIjZmZmIi8%2BCjxwYXRoIGQ9Ik00IDEyTDEyIDQiIHN0cm9rZT0iI2ZmZiIgc3Ryb2tlLXdpZHRoPSIxLjUiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIvPgo8L3N2Zz4K&logoColor=ffffff" alt="zread"/></a>
</p>

![Good morning, LLM](https://github.com/user-attachments/assets/8129f227-dc3f-4671-bc0e-0ecd2f3a1888)

## What is CTX?

**CTX** is a single ~20 MB binary with zero dependencies. No Node.js, no Python, no runtime — just download, connect
to your MCP client, and start coding with AI.

Connect it to [Claude Desktop](https://claude.ai/download), [Cursor](https://cursor.com),
[Cline](https://github.com/cline/cline), or any MCP-compatible client — and your AI gets direct access to read, write,
search, and modify files across your projects.

CTX is designed with **Claude Desktop** in mind and works best with it. Claude's deep understanding of code combined
with CTX's filesystem tools, custom commands, and multi-project support creates a seamless development experience —
like having a senior developer who knows your entire codebase sitting right next to you.

## Table of Contents

- [Key Features](#key-features)
- [Quick Start](#quick-start)
- [How It Works](#how-it-works)
- [Use Cases](#use-cases)
- [Full Documentation](https://docs.ctxllm.com)
- [License](#license)

## Key Features

### 🛠 MCP Server — AI Develops Directly in Your Project

CTX provides a built-in MCP server with powerful filesystem tools:

- **Read & write files** — AI creates, modifies, and analyzes code directly
- **Search across codebase** — text and regex search with context lines
- **PHP structure analysis** — class hierarchy, interfaces, and dependencies at a glance
- **Directory exploration** — smart filtering by patterns, dates, sizes, and content

### ⚡ Custom Tools — Turn Any Command into an AI Tool

Define project-specific commands that AI can execute through MCP:

```yaml
tools:
  - id: run-tests
    description: "Run project tests with coverage"
    type: run
    commands:
      - cmd: vendor/bin/phpunit
        args: [ "--coverage-html", "logs/coverage" ]

  - id: deploy
    description: "Deploy to staging"
    type: run
    schema:
      type: object
      properties:
        branch:
          type: string
          default: "main"
    commands:
      - cmd: ./deploy.sh
        args: [ "{{branch}}" ]
```

Tests, migrations, linters, deployments — anything your terminal can run, AI can trigger.

### 📁 Multi-Project Development

Work across multiple microservices simultaneously. AI sees all your projects and can develop cross-cutting features:

```yaml
projects:
  - name: backend-api
    path: ../backend
    description: "REST API service"

  - name: auth-service
    path: ../auth
    description: "Authentication microservice"

  - name: shared-lib
    path: ../packages/shared
    description: "Shared domain models"
```

Start a session, ask AI to list available projects, and develop features that span multiple services — all in one
conversation.

### 🎯 Smart Context Generation

Define exactly what context your AI needs. CTX collects code from files, git diffs, GitHub repos, URLs, and more — then
structures it into clean markdown documents:

```yaml
documents:
  - description: User Authentication System
    outputPath: auth.md
    sources:
      - type: file
        sourcePaths: [ src/Auth ]
        filePattern: "*.php"
      - type: git_diff
        commit: "last-week"
```

### 📐 Declarative Config with JSON Schema

Everything is configured through `context.yaml` with full JSON Schema support. Your AI assistant can generate and modify
these configs for you — just describe what you need.

```bash
ctx init    # Generate initial config
ctx generate  # Build context documents
ctx server  # Start MCP server
```

## Quick Start

### Install

**Linux / WSL:**

```bash
curl -sSL https://raw.githubusercontent.com/context-hub/generator/main/download-latest.sh | sh
```

**Windows:**

```bash
powershell -c "& ([ScriptBlock]::Create((irm 'https://raw.githubusercontent.com/context-hub/generator/main/download-latest.ps1'))) -AddToPath"
```

### Connect to Claude Desktop (or Any MCP Client)

The fastest way — auto-detect OS and configure your MCP client:

```bash
ctx mcp:config
```

Or add manually to your MCP client config:

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

For a specific project:

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

That's it. Your AI assistant now has full access to your project through MCP.

### Optional: Generate Context Documents

If you also want to generate static context files for copy-paste workflows:

```bash
cd your-project
ctx init       # Create context.yaml
ctx generate   # Build markdown contexts
```

## How It Works

CTX operates in two modes that complement each other:

**MCP Server Mode** — AI interacts with your codebase in real-time:

```
AI Assistant ←→ CTX MCP Server ←→ Your Project Files
                     ↕
              Custom Tools (tests, deploy, lint...)
              Multiple Projects
              Context Documents
```

**Context Generation Mode** — build structured documents for any LLM:

```
context.yaml → Sources → Filters → Modifiers → Markdown Documents
```

## Use Cases

### 🔧 AI-Powered Development (MCP)

Connect CTX to Claude Desktop or Cursor. Ask your AI to explore the codebase, understand architecture, write new
features, run tests, and fix issues — all without leaving the conversation.

### 🏗 Multi-Service Feature Development

Working on a feature that touches multiple microservices? Register all projects, and AI can read code from one service,
understand shared models, and implement changes across the entire stack.

### 📝 Context for Code Review

Generate context documents with recent git diffs, relevant source files, and architecture overview. Share with reviewers
or AI assistants for thorough, informed reviews.

### 🚀 Onboarding

New team member? Generate a comprehensive project overview — architecture, key interfaces, domain models — in seconds.
AI can then answer questions about the codebase with full context.

### 📚 Documentation Generation

Point CTX at your source code with modifiers like `php-signature` to extract API surfaces, then let AI generate
comprehensive documentation.

## Full Documentation

For complete documentation, including all features and configuration options:

https://docs.ctxllm.com

## Join Our Community

[![Join Discord](https://img.shields.io/discord/1419284404315881633?color=5865F2&label=Join%20Discord&logo=discord&logoColor=white&style=for-the-badge)](https://discord.gg/YmFckwVkQM)

**What you'll find:**

- 💡 Share and discover configurations and workflows
- 🛠️ Get help with setup and advanced usage
- 🚀 Showcase your AI development workflows
- 📢 First to know about new releases

---

### License

This project is licensed under the MIT License.
