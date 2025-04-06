# Custom Tools

The Custom Tools feature allows you to define project-specific commands that can be executed directly from the
configuration files. This enables easy integration of common development tasks, build processes, code analysis, and
more.

## Configuration Format

Custom tools are defined in the `tools` section of the configuration file. Here's the basic structure:

```yaml
tools:
   - id: tool-id                      # Unique identifier for the tool
     description: 'Tool description'  # Human-readable description
     env: # Optional environment variables for all commands
        KEY1: value1
        KEY2: value2
     commands: # List of commands to execute
        - cmd: executable              # Command to run
          args: # Command arguments (array)
             - arg1
             - arg2
          workingDir: path/to/dir      # Optional working directory (relative to project root)
          env: # Optional environment variables for this command
             KEY1: value1
             KEY2: value2
```

## Example Use Cases

### 1. Code Style Fixing

```yaml
tools:
   - id: cs-fixer
     description: 'Fix code style issues'
     commands:
        - cmd: composer
          args: [ 'cs:fix' ]
```

### 2. Static Analysis

```yaml
tools:
   - id: phpstan
     description: 'Run static analysis'
     commands:
        - cmd: vendor/bin/phpstan
          args: [ 'analyse', 'src', '--level', '8' ]
```

### 3. Multi-Step Processes

```yaml
tools:
   - id: test-suite
     description: 'Run full test suite with coverage'
     commands:
        - cmd: composer
          args: [ 'install', '--no-dev' ]
        - cmd: vendor/bin/phpunit
          args: [ '--coverage-html', 'coverage' ]
        - cmd: vendor/bin/infection
          args: [ '--min-msi=80' ]
```

### 4. Deployment

```yaml
tools:
   - id: deploy-staging
     description: 'Deploy to staging environment'
     commands:
        - cmd: bash
          args: [ 'deploy.sh', 'staging' ]
          env:
             DEPLOY_TOKEN: "${STAGING_TOKEN}"
```

## Security Considerations

The custom tools feature includes several security measures:

1. **Environment Variable Controls**:
    - `MCP_CUSTOM_TOOLS_ENABLE`: Enable/disable the custom tools feature (default: `true`)
    - `MCP_TOOL_MAX_RUNTIME`: Maximum runtime for a command in seconds (default: `30`)

## Environment Configuration

### Environment Variables

| Variable                  | Description                              | Default |
|---------------------------|------------------------------------------|---------|
| `MCP_CUSTOM_TOOLS_ENABLE` | Enable/disable custom tools              | `true`  |
| `MCP_TOOL_MAX_RUNTIME`    | Maximum runtime for a command in seconds | `30`    |

## Best Practices

1. **Keep Commands Simple**: Break complex operations into multiple commands
2. **Use Environment Variables**: Avoid hardcoding secrets in tool configurations
3. **Set Appropriate Timeouts**: Adjust the `max_runtime` for long-running commands
4. **Test Thoroughly**: Test custom tools before implementing them in production
5. **Consider Security**: Be cautious about what commands are allowed and who can execute them
