# HTTP Tools

**CTX** MCP server includes HTTP tools that allow Claude to make HTTP requests to external APIs directly from your
conversation. These tools enable powerful AI-assisted workflows for fetching data, interacting with external services,
and integrating with third-party APIs.

## HTTP Tool Configuration

HTTP tools are defined in your configuration files using the `tools` section with a `type` of `http`:

```yaml
tools:
  - id: github-api
    type: http
    description: "Fetch information from GitHub API"
    schema:
      properties:
        repo:
          type: string
          description: "Repository name (e.g., context-hub/docs)"
          default: "context-hub/docs"
        token:
          type: string
          description: "GitHub API token"
      required: [ repo ]
    requests:
      - url: "https://api.github.com/repos/{{repo}}"
        method: GET
        headers:
          Authorization: "Bearer {{token}}"
          Accept: "application/vnd.github.v3+json"
```

Each HTTP tool contains:

- **id**: Unique identifier for the tool
- **type**: Must be `http` for HTTP tools
- **description**: Human-readable description
- **schema** (optional): Defines input parameters with descriptions and required fields
- **requests**: An array of HTTP requests to execute

## HTTP Request Configuration

Each request in the `requests` array can include:

- **url**: The URL to send the request to (can include template variables)
- **method**: HTTP method (GET, POST, etc.)
- **headers**: HTTP headers as key-value pairs
- **query**: Query parameters as key-value pairs
- **body**: Request body (for POST, PUT, etc.) as string or object

Template variables can be used in URLs, headers, query parameters, and body content using the format `{{variableName}}`.

## Variable Substitution

HTTP tools support variable substitution in all request components using the format `{{variableName}}`. When Claude
executes a tool with arguments, the MCP server replaces these placeholders with the provided values.

## Example HTTP Tool Configurations

### Basic API Request

```yaml
tools:
  - id: weather-api
    type: http
    description: "Get weather information"
    schema:
      properties:
        city:
          type: string
          description: "City name"
          default: "New York"
      required: [ city ]
    requests:
      - url: "https://api.example.com/weather"
        method: GET
        query:
          q: "{{city}}"
          units: "metric"
```

### Authentication with API Key

```yaml
tools:
  - id: search-api
    type: http
    description: "Search external API"
    schema:
      properties:
        query:
          type: string
          description: "Search query"
        api_key:
          type: string
          description: "API key"
      required: [ query, api_key ]
    requests:
      - url: "https://api.example.com/search"
        method: GET
        headers:
          X-API-Key: "{{api_key}}"
        query:
          q: "{{query}}"
```

### Multiple Requests Sequence

```yaml
tools:
  - id: user-data
    type: http
    description: "Get user data and recent activity"
    schema:
      properties:
        user_id:
          type: string
          description: "User ID"
      required: [ user_id ]
    requests:
      - url: "https://api.example.com/users/{{user_id}}"
        method: GET
      - url: "https://api.example.com/users/{{user_id}}/activity"
        method: GET
```

## Environment Variables Configuration

HTTP tools can be configured using environment variables:

| Variable              | Description                      | Default |
|-----------------------|----------------------------------|---------|
| `MCP_HTTP_TOOLS`      | Enable/disable HTTP tools        | `true`  |
| `MCP_HTTP_TIMEOUT`    | HTTP request timeout in seconds  | `30`    |
| `MCP_HTTP_USER_AGENT` | User agent for all HTTP requests | -       |

## Security Considerations

When using HTTP tools, consider the following security best practices:

1. **API Keys and Credentials**:
    - Never hardcode sensitive credentials in tool definitions
    - Use environment variables or secure storage for API keys
    - Consider using schema defaults for non-sensitive values only

2. **Access Control**:
    - Limit which external APIs can be accessed
    - Consider using an allow-list approach for external domains

3. **Rate Limiting**:
    - Be aware of API rate limits for external services
    - Implement client-side rate limiting if necessary

4. **Response Handling**:
    - Validate and sanitize responses before processing
    - Be cautious with handling error responses that might contain sensitive information

## Use Cases

HTTP tools can be used for a variety of purposes:

1. **Data Enrichment**
    - Fetch additional information to supplement conversations
    - Look up reference data from external sources

2. **Integration with External Systems**
    - Submit data to external APIs
    - Trigger workflows in other systems

3. **Real-time Information**
    - Get current weather, news, or other time-sensitive data
    - Check status of services or systems

4. **Authentication Verification**
    - Validate credentials against external services
    - Verify user permissions or access rights
