# Variables System Guide

## Overview

CTX provides a powerful variable substitution system that allows dynamic values in your configuration files. Variables
can come from multiple sources and are resolved at runtime, making configurations flexible and environment-aware.

## Variable Syntax

CTX supports two variable syntaxes:

```yaml
# Dollar-brace syntax (recommended)
api_key: ${OPENAI_API_KEY}

# Double-brace syntax
api_key: {{OPENAI_API_KEY}}
```

Both syntaxes work identically. The dollar-brace syntax (`${VAR}`) is recommended as it's more common in configuration
files.

### Default Values

You can specify default values for variables that may not be set:

```yaml
# Uses "localhost" if RAG_QDRANT_HOST is not defined
host: ${RAG_QDRANT_HOST:-localhost}

# Uses port 6333 if RAG_QDRANT_PORT is not defined  
port: ${RAG_QDRANT_PORT:-6333}
```

## Variable Sources

Variables are resolved from multiple sources in the following priority order (highest to lowest):

### 1. Configuration Variables (Highest Priority)

Variables defined directly in `context.yaml`:

```yaml
variables:
  project_name: "My Project"
  version: "2.0.0"
  api_base_url: "https://api.example.com"

documents:
  - description: "${project_name} v${version} Documentation"
    outputPath: "docs/overview.md"
    sources:
      - type: url
        urls:
          - "${api_base_url}/docs"
```

### 2. Environment Variables (Medium Priority)

Variables from `.env` files or system environment:

```bash
# .env file
OPENAI_API_KEY=sk-...
RAG_QDRANT_HOST=localhost
RAG_QDRANT_PORT=6333
GITHUB_TOKEN=ghp_...
```

```yaml
# context.yaml
rag:
  vectorizer:
    api_key: ${OPENAI_API_KEY}
  store:
    qdrant:
      endpoint_url: http://${RAG_QDRANT_HOST}:${RAG_QDRANT_PORT}
```

### 3. Predefined System Variables (Lowest Priority)

Built-in variables available automatically:

| Variable         | Description         | Example Value               |
|------------------|---------------------|-----------------------------|
| `${USER}`        | Current system user | `john`                      |
| `${HOME}`        | User home directory | `/home/john`                |
| `${OS}`          | Operating system    | `Linux`                     |
| `${DATETIME}`    | Current date/time   | `2024-01-15 10:30:00`       |
| `${DATE}`        | Current date        | `2024-01-15`                |
| `${TIME}`        | Current time        | `10:30:00`                  |
| `${ROOT_PATH}`   | Project root path   | `/path/to/project`          |
| `${OUTPUT_PATH}` | Output directory    | `/path/to/project/.context` |

## Loading Environment Files

### CLI Commands

All CTX commands support the `--env` (`-e`) option to load a specific `.env` file:

```bash
# Load .env file (default)
ctx generate

# Load specific env file
ctx generate --env=.env.local
ctx generate -e .env.production

# MCP Server with env file
ctx server --env=.env.local

# RAG commands with env file
ctx rag:index docs --env=.env.local
ctx rag:status -e .env
```

### Default Behavior

When no `--env` option is provided:

- CLI commands look for `.env` in the project root directory
- If `.env` exists, it's loaded automatically
- If `.env` doesn't exist, only system environment variables are used

### Environment File Format

Standard `.env` format is supported:

```bash
# .env or .env.local

# API Keys
OPENAI_API_KEY=sk-proj-...
ANTHROPIC_API_KEY=sk-ant-...
GITHUB_TOKEN=ghp_...

# RAG Configuration
RAG_QDRANT_HOST=localhost
RAG_QDRANT_PORT=6333
RAG_COLLECTION=my_knowledge_base

# Feature Flags
MCP_FILE_OPERATIONS=true
DEBUG_MODE=false

# Paths
CUSTOM_OUTPUT_PATH=/var/ctx/output
```

## Variable Resolution Architecture

### How Variables Are Resolved

```
┌─────────────────────────────────────────────────────────────┐
│                    VariableResolver                          │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │              CompositeVariableProvider                  │ │
│  │                                                         │ │
│  │  ┌─────────────────┐  Priority: 1 (Highest)            │ │
│  │  │ConfigVariable   │  Variables from context.yaml      │ │
│  │  │Provider         │  variables: section               │ │
│  │  └─────────────────┘                                   │ │
│  │           │                                             │ │
│  │           ▼                                             │ │
│  │  ┌─────────────────┐  Priority: 2                      │ │
│  │  │DotEnvVariable   │  Variables from .env files        │ │
│  │  │Provider         │  and system environment           │ │
│  │  └─────────────────┘                                   │ │
│  │           │                                             │ │
│  │           ▼                                             │ │
│  │  ┌─────────────────┐  Priority: 3 (Lowest)             │ │
│  │  │PredefinedVariable│  Built-in system variables       │ │
│  │  │Provider         │  (USER, DATE, etc.)               │ │
│  │  └─────────────────┘                                   │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### Resolution Process

1. **Parse**: Find all `${VAR_NAME}` patterns in the text
2. **Lookup**: Check each provider in priority order
3. **Replace**: Substitute the first found value
4. **Fallback**: If not found in any provider, keep original `${VAR_NAME}` text

## Practical Examples

### Example 1: Multi-Environment Configuration

```yaml
# context.yaml
variables:
  env: ${APP_ENV:-development}

import:
  - path: "config/base.yaml"
  - path: "config/${env}.yaml"

documents:
  - description: "API Documentation (${env})"
    outputPath: "docs/api-${env}.md"
```

```yaml
# config/base.yaml
variables:
  api_timeout: 30
  retry_count: 3
```

```yaml
# config/production.yaml  
variables:
  api_timeout: 10
  retry_count: 5
```

### Example 2: RAG with Environment Variables

```yaml
# context.yaml
rag:
  enabled: true
  store:
    driver: qdrant
    qdrant:
      endpoint_url: ${RAG_QDRANT_URL:-http://localhost:6333}
      api_key: ${RAG_QDRANT_API_KEY:-}
      collection: ${RAG_COLLECTION:-ctx_knowledge}
  vectorizer:
    platform: openai
    model: text-embedding-3-small
    api_key: ${OPENAI_API_KEY}
```

```bash
# .env.local
OPENAI_API_KEY=sk-proj-...
RAG_QDRANT_URL=http://qdrant.internal:6333
RAG_QDRANT_API_KEY=my-secret-key
RAG_COLLECTION=project_docs
```

### Example 3: GitHub Integration with Token

```yaml
# context.yaml
documents:
  - description: "External Library Docs"
    outputPath: "vendor-docs.md"
    sources:
      - type: github
        repository: "owner/repo"
        branch: main
        sourcePaths: [ "docs" ]
        githubToken: ${GITHUB_TOKEN}
```

### Example 4: Dynamic Output Paths

```yaml
# context.yaml
variables:
  version: "1.2.3"
  build_date: ${DATE}

documents:
  - description: "Release Documentation"
    outputPath: "releases/${version}/docs-${build_date}.md"
    sources:
      - type: text
        content: |
          # Release ${version}
          Built on: ${build_date}
          By: ${USER}
```

## Security Best Practices

### 1. Never Commit Secrets

```bash
# .gitignore
.env
.env.local
.env.*.local
```

### 2. Use Environment-Specific Files

```
.env              # Shared defaults (no secrets)
.env.local        # Local development (gitignored)
.env.production   # Production values (gitignored)
```

### 3. Document Required Variables

```yaml
# context.yaml
# Required environment variables:
# - OPENAI_API_KEY: OpenAI API key for embeddings
# - GITHUB_TOKEN: GitHub token for private repos (optional)

rag:
  vectorizer:
    api_key: ${OPENAI_API_KEY}
```

### 4. Validate Before Use

```bash
# Check if required variables are set
if [ -z "$OPENAI_API_KEY" ]; then
    echo "Error: OPENAI_API_KEY is not set"
    exit 1
fi

ctx rag:index docs
```

## Debugging Variables

### Check Resolved Values

Use verbose mode to see variable resolution:

```bash
ctx generate -v
# or
ctx generate --verbose
```

### Test Variable Resolution

Create a simple test configuration:

```yaml
# test-vars.yaml
variables:
  test_var: "from_config"

documents:
  - description: "Variable Test"
    outputPath: "test.md"
    sources:
      - type: text
        content: |
          Config var: ${test_var}
          Env var: ${TEST_ENV_VAR:-not_set}
          System var: ${USER}
```

```bash
TEST_ENV_VAR=from_env ctx generate -c test-vars.yaml
```

## Troubleshooting

### Variable Not Replaced

If `${VAR_NAME}` appears in output unchanged:

1. **Check spelling**: Variable names are case-sensitive
2. **Check source**: Ensure the variable is defined in config, .env, or environment
3. **Check priority**: A higher-priority source may be overriding
4. **Check .env loading**: Use `--env` flag to specify the correct file

### .env File Not Loading

1. **Check path**: File must be relative to project root
2. **Check format**: Ensure valid `.env` syntax (no quotes around values usually needed)
3. **Check permissions**: File must be readable

### Default Value Not Working

Ensure correct syntax for defaults:

```yaml
# Correct
value: ${VAR:-default}

# Incorrect (missing hyphen)
value: ${VAR:default}
```
