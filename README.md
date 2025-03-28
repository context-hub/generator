# Context Generator for LLM with MCP server

<p>
    <a href="https://docs.ctxgithub.com/"><img alt="Docs" src="https://img.shields.io/badge/docs-green"></a>
    <a href="https://raw.githubusercontent.com/context-hub/generator/refs/heads/main/json-schema.json"><img alt="Json schema" src="https://img.shields.io/badge/json_schema-blue"></a>
    <a href="https://packagist.org/packages/context-hub/generator"><img alt="License" src="https://img.shields.io/packagist/l/context-hub/generator"></a>
    <a href="https://packagist.org/packages/context-hub/generator"><img alt="Latest Version" src="https://img.shields.io/packagist/v/context-hub/generator"></a>
</p>

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

- **Seamless AI Integration**: With MCP support, [connect](https://docs.ctxgithub.com/mcp-server.html) Claude AI directly to your codebase, allowing
  for real-time, context-aware assistance without manual context sharing.

## How it works

1. Gathers code from files, directories, GitHub repositories, web pages, or custom text.
2. Targets specific files through pattern matching, content search, size, or date filters
3. Applies optional modifiers (like extracting PHP signatures without implementation details)
4. Organizes content into well-structured markdown documents
5. Saves context files ready to be shared with LLMs
6. Optionally serves context through an MCP server, allowing AI assistants like Claude to directly access project
   information

# Quick Start

Getting started with Context Generator is straightforward. Follow these simple steps to create your first context file
for LLMs.

## 1. Install Context Generator

Download and install the tool using our installation script:

```bash
curl -sSL https://raw.githubusercontent.com/context-hub/generator/main/download-latest.sh | sh
```

This installs the `ctx` command to your system (typically in `/usr/local/bin`).

> **Want more options?** See the complete [Installation Guide](https://docs.ctxgithub.com/getting-started.html) for alternative installation methods.

## 2. Initialize a Configuration File

Create a new configuration file in your project directory:

```bash
ctx init
```

This generates a `context.yaml` file with a basic structure to get you started.

> **Pro tip:** Run `ctx init --type=json` if you prefer JSON configuration format.
> Check the [Command Reference](https://docs.ctxgithub.com/getting-started/command-reference.html) for all available commands and options.

## 3. Describe Your Project Structure

Edit the generated `context.yaml` file to specify what code or content you want to include. For example:

```yaml
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
- Explore different source types like [GitHub](https://docs.ctxgithub.com/sources/github-source.html), [Git Diff](https://docs.ctxgithub.com/sources/git-diff-source.html),
  or [URL](https://docs.ctxgithub.com/sources/url-source.html)
- Apply [Modifiers](https://docs.ctxgithub.com/modifiers.html) to transform your content (like extracting PHP signatures)
- Discover how to use [Environment Variables](https://docs.ctxgithub.com/environment-variables.html) in your config
- Use [IDE Integration](https://docs.ctxgithub.com/getting-started/ide-integration.html) for autocompletion and validation

## 4. Build the Context

Generate your context file by running:

```bash
ctx
```

The tool will process your configuration and create the specified output file (`auth-context.md` in our example).

> **Tip**: Configure [Logging](https://docs.ctxgithub.com/advanced/logging.html) with `-v`, `-vv`, or `-vvv` for detailed output

## 5. Share with an LLM

Upload or paste the generated context file to your favorite LLM (like ChatGPT or Claude). Now you can ask specific
questions about your codebase, and the LLM will have the necessary context to provide accurate assistance.

Example prompt:

> I've shared my authentication system code with you. Can you help me identify potential security vulnerabilities in the
> user registration process?

> **Next steps:** Check out [Development with Context Generator](https://docs.ctxgithub.com/advanced/development-process.html) for best practices on
> integrating context generation into your AI-powered development workflow.

That's it! You're now ready to leverage LLMs with proper context about your codebase.

## 6. Connect to Claude AI (Optional)

For a more seamless experience, you can connect Context Generator directly to Claude AI using the MCP server:

There is a built-in MCP server that allows you to connect Claude AI directly to your codebase.

Point the MCP client to the Context Generator server:

```json
{
  "mcpServers": {
    "ctx": {
      "command": "ctx server -c /path/to/your/project"
    }
  }
}
```

> **Note:** Read more about [MCP Server](https://docs.ctxgithub.com/mcp-server.html) for detailed setup instructions.

Now you can ask Claude questions about your codebase without manually uploading context files!

## 7. IDEA Integration

For a more seamless experience, you can integrate Context Generator with IntelliJ IDEA:

### Setup

1. Open your project in IntelliJ IDEA.
2. Go to `File > Project Structure > Project Settings > Modules`.
3. Click on the `+` icon to add a new module.
4. Select `Import Module` and choose the `context-hub-generator.xml` file located in the `.idea` directory.
5. Follow the prompts to complete the module import.

### Usage

- To run `ctx` within IDEA, go to `Run > Edit Configurations`.
- Click on the `+` icon to add a new configuration.
- Select `PHPUnit` and name it `Run ctx`.
- Set the `Script` field to `$PROJECT_DIR$/ctx`.
- Set the `Interpreter` field to `PHP 7.4`.
- Click `OK` to save the configuration.

To show the tree view of config files:

- Go to `Run > Edit Configurations`.
- Click on the `+` icon to add a new configuration.
- Select `PHPUnit` and name it `Show Tree View`.
- Set the `Script` field to `$PROJECT_DIR$/ctx`.
- Set the `Arguments` field to `--tree-view`.
- Set the `Interpreter` field to `PHP 7.4`.
- Click `OK` to save the configuration.

Now you can run `ctx` and view the tree structure of your config files directly within IDEA.

## JSON Schema

For better editing experience, Context Generator provides a JSON schema for autocompletion and validation in your IDE:

```bash
# Show schema URL
ctx schema

# Download schema to current directory
ctx schema --download
```

> **Learn more:** See [IDE Integration](https://docs.ctxgithub.com/getting-started/ide-integration.html) for detailed setup instructions for VSCode,
> PhpStorm, and other editors.

## Full Documentation

For complete documentation, including all available features and configuration options, please visit:

https://docs.ctxgithub.com

---

### License

This project is licensed under the MIT License.
