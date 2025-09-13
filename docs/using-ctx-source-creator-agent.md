# Using the CTX Source Creator Agent

The `ctx-source-creator` agent is a specialized Claude Code agent designed to help you create new source types for the
CTX context generator system.

## When to Use This Agent

Use the `ctx-source-creator` agent when you need to:

- 🔌 **Create custom data source integrations** (APIs, databases, external services)
- 📄 **Build specialized content processors** for unique file formats
- 🏗️ **Implement new source types** following CTX architectural patterns
- 🚀 **Extend the context generation system** with custom functionality

## How to Activate

The agent will be automatically suggested when you:

1. **Mention source creation tasks**: "I need to create a new source type for..."
2. **Request CTX system extension**: "How do I add support for..."
3. **Ask about custom integrations**: "I want to integrate with..."
4. **Work on context generation**: "Help me extend CTX to support..."

You can also explicitly request the agent: "Use the ctx-source-creator agent to help me create a database source type"

## What the Agent Provides

The `ctx-source-creator` agent offers:

### **🎯 Complete Implementation**

- Source class extending BaseSource
- Factory class for instance creation
- Fetcher class for content retrieval
- Bootloader for system registration

### **🔧 System Integration**

- Kernel registration in `src/Application/Kernel.php`
- JSON schema updates in `json-schema.json`
- Proper enum and validation definitions

### **✅ Quality Assurance**

- Code style compliance
- Comprehensive error handling
- Structured logging implementation
- Test configuration creation

### **📚 Documentation**

- Complete implementation guide
- Usage examples and patterns
- Best practices and common pitfalls

## Example Interaction

```
User: I need to create a source type that fetches data from a REST API

Agent: I'll help you create a REST API source type for CTX. This will involve:

1. **Planning the source structure** - What API endpoints, authentication, data processing
2. **Implementing the four core classes** - ApiSource, ApiSourceFactory, ApiSourceFetcher, ApiSourceBootloader
3. **Adding configuration validation** - URL, headers, authentication parameters
4. **Integrating with the system** - Kernel registration and JSON schema updates
5. **Creating test configurations** - Example API calls and validation

Let me start by creating the source structure...
[Proceeds to implement complete solution]
```

## Source Types the Agent Can Create

### **🟢 Data Integration Sources**

- **REST API Source** - HTTP/HTTPS API calls with authentication
- **Database Source** - SQL/NoSQL database connections
- **RSS/Feed Source** - News feeds and syndication
- **GraphQL Source** - GraphQL endpoint integration

### **🟡 File Processing Sources**

- **CSV Source** - Structured data processing
- **XML Source** - XML document parsing
- **Binary Source** - Binary file content extraction
- **Archive Source** - ZIP/TAR archive processing

### **🔴 Advanced Integration Sources**

- **Cloud Storage Source** - AWS S3, Google Cloud Storage
- **Message Queue Source** - RabbitMQ, Apache Kafka
- **Search Engine Source** - Elasticsearch, Solr
- **Cache Source** - Redis, Memcached integration

### **🟣 Specialized Sources**

- **Template Source** - Dynamic template processing
- **Script Source** - Shell command execution
- **Monitoring Source** - System metrics and logs
- **AI/ML Source** - Model predictions and analysis

## Agent Workflow

The agent follows a systematic approach:

### **1. Requirements Analysis**

- Understands the data source requirements
- Identifies necessary properties and configuration
- Plans the integration approach

### **2. Architecture Planning**

- Designs the source class structure
- Plans content processing workflow
- Identifies dependencies and utilities needed

### **3. Implementation**

- Creates all four required classes
- Implements proper validation and error handling
- Adds comprehensive logging

### **4. System Integration**

- Registers in application kernel
- Updates JSON schema definitions
- Ensures proper type enumeration

### **5. Testing & Validation**

- Creates test configurations
- Runs code style and unit tests
- Validates schema compliance

### **6. Documentation**

- Generates usage documentation
- Creates configuration examples
- Provides troubleshooting guidance

## Best Practices Applied

The agent ensures all implementations follow:

- ✅ **CTX Architecture Patterns** - Four-class structure
- ✅ **Immutability Principles** - Readonly properties
- ✅ **Error Handling Standards** - Proper exception types
- ✅ **Code Quality Requirements** - PSR compliance
- ✅ **Testing Standards** - Comprehensive validation
- ✅ **Documentation Standards** - Clear examples and guides

## Getting Started

To use the agent effectively:

1. **Describe your data source** clearly and specifically
2. **Mention any special requirements** (authentication, formatting, etc.)
3. **Specify the expected output format** for the generated content
4. **Ask for complete implementation** including testing and documentation

The agent will handle all the complexity of CTX source type creation while ensuring your implementation follows best
practices and integrates seamlessly with the existing system.

## Examples of Agent Requests

### **Simple Request**

```
"Create a source type for fetching JSON data from REST APIs"
```

### **Detailed Request**

```
"I need a database source that connects to PostgreSQL, executes queries, 
and formats results as markdown tables with support for parameterized queries 
and connection pooling"
```

### **Complex Request**

```
"Create a cloud storage source that can fetch files from AWS S3, process 
different file types (text, JSON, CSV), apply content transformations, 
and include metadata like file size and modification date"
```

The `ctx-source-creator` agent is your comprehensive solution for extending the CTX context generator with custom source
types!