# Markdown Import Example

This directory demonstrates how to use markdown imports with CTX.

## Usage

```yaml
import:
  - type: local
    path: ./prompts  # Directory containing markdown files
    format: md       # Process as markdown with metadata
```

## Markdown File Structure

### With YAML Frontmatter

Each markdown file can have YAML frontmatter with metadata:

```markdown
---
type: prompt
title: "Code Review Helper"
description: "Helps with code review tasks"
tags: ["code-review", "development"]
role: assistant
schema:
  properties:
    language:
      type: string
      description: "Programming language"
    focus:
      type: string
      description: "What to focus on"
---

# Code Review Assistant

I'll help you review code with focus on {{focus}} for {{language}} projects.

Please provide the code you'd like me to review.
```

### Without Frontmatter (Header Title Auto-Detection)

Files without frontmatter will automatically extract the title from the first header:

```markdown
# Database Best Practices

This document outlines best practices for database design and management.

## Schema Design
- Use consistent naming conventions
- Normalize data appropriately
```

In this case, "Database Best Practices" will be automatically used as the title/description.

## Title/Description Priority

The system uses this priority order for determining titles and descriptions:

1. **`description`** field in frontmatter (highest priority)
2. **`title`** field in frontmatter
3. **First `#` header** in content (auto-extracted)
4. **Generated from filename** (lowest priority)

## Supported Metadata Fields

### For Prompts (`type: prompt`)
- `id`: Unique identifier (auto-generated from filename if not provided)
- `title`/`description`: Human readable description
- `tags`: Array or comma-separated string of tags
- `role`: Message role (user/assistant)
- `schema`: JSON schema for prompt arguments
- `messages`: Array of message objects (auto-generated from content if not provided)
- `extend`: Template inheritance configuration

### For Documents (`type: document`)
- `id`: Unique identifier
- `title`/`description`: Document description
- `outputPath`: Where to save the generated document
- `overwrite`: Whether to overwrite existing files
- `tags`: Array or comma-separated string of tags
- `sources`: Array of source configurations (auto-generated from content if not provided)

### For Resources (no type or `type: resource`)
- Will be converted to documents with text sources containing the markdown content
- Title will be extracted from the first header if no frontmatter is present

## Examples

This directory contains examples of all supported formats:

- **`prompts/python-helper.md`** - Full frontmatter with schema
- **`prompts/code-review-checklist.md`** - No frontmatter, title from header
- **`docs/api-docs.md`** - Document type with metadata
- **`resources/guidelines.md`** - Frontmatter without explicit type
- **`resources/database-practices.md`** - No frontmatter, title from header
