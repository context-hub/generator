# Prompt Tagging and Filtering System

This document explains how to use the prompt tagging system and import filtering functionality to better organize your
prompts and selectively import prompts from external sources.

## Tagging Prompts

You can add tags to your prompts in the YAML configuration, which helps organize and categorize them:

```yaml
prompts:
  - id: python-helper
    description: "Helps with Python code and concepts"
    tags: [ "python", "coding", "development" ]
    messages:
      - role: user
        content: "You are a Python coding assistant..."
```

Tags can be used to:

- Categorize prompts by domain (e.g., "writing", "coding", "data")
- Indicate skill level (e.g., "beginner", "advanced")
- Mark special usage (e.g., "debug", "refactor", "analyze")
- Group by purpose (e.g., "brainstorming", "summarization")

## Import Filtering

The import filtering system allows you to selectively import prompts from external sources based on criteria like IDs or
tags.

### Filter by IDs

Import specific prompts by their IDs:

```yaml
import:
  - type: url
    url: "https://prompts-repository.example.com/prompts.yaml"
    filter:
      ids: [ "python-helper", "php-debug", "js-refactor" ]
```

This will only import the specified prompts and ignore all others from the source.

### Filter by Tags

Import prompts based on tag criteria:

```yaml
import:
  - path: "./local-prompts.yaml"
    type: local
    filter:
      tags:
        include: [ "coding", "debugging" ]
        exclude: [ "advanced" ]
      match: "any"  # Can be "all" for AND logic
```

- `include`: Only prompts with these tags will be imported
- `exclude`: Prompts with these tags will be excluded, even if they match the include criteria
- `match`: Determines how include tags are matched:
    - `any`: Import if the prompt has ANY of the include tags (OR logic)
    - `all`: Import only if the prompt has ALL of the include tags (AND logic)

### Combined Filtering

You can combine ID and tag filtering in a single import:

```yaml
import:
  - path: "./another-collection.yaml"
    type: local
    filter:
      ids:
        - creative-writing
        - summarization-prompt
      tags:
        include:
          - content
        exclude:
          - technical
      match: "any"  # This applies to the overall filter strategy
```

With combined filtering, prompts are imported if they match either the ID criteria OR the tag criteria (with
`match: "any"`), or if they match both criteria (with `match: "all"`).

## Practical Use Cases

### Creating Domain-Specific Collections

Import only prompts related to a specific domain:

```yaml
import:
  - url: "https://example.com/all-prompts.yaml"
    filter:
      tags:
        include: [ "writing", "content-generation" ]
```

### Importing by Skill Level

Import prompts based on skill level, excluding advanced ones:

```yaml
import:
  - url: "https://example.com/coding-prompts.yaml"
    filter:
      tags:
        include: [ "coding" ]
        exclude: [ "advanced" ]
```

### Creating Curated Collections

Import a curated set of prompts by their IDs:

```yaml
import:
  - url: "https://example.com/prompt-repository.yaml"
    filter:
      ids:
        - python-helper
        - summarization-prompt
        - brainstorming
```

## Best Practices

1. **Consistent Tagging**: Develop a consistent tagging scheme for your prompts
2. **Tag Hierarchy**: Consider using hierarchical tags (e.g., "coding:python", "coding:javascript")
3. **Import Strategy**: Use ID filtering for precise control and tag filtering for categorical imports
4. **Documentation**: Document the tags used in your prompt collection for easier navigation
