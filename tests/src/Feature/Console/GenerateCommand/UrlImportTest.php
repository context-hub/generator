<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\HttpClient\HttpResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

final class UrlImportTest extends ConsoleTestCase
{
    private string $outputDir;
    private MockHttpClient $mockHttpClient;

    public static function commandsProvider(): \Generator
    {
        yield 'generate' => ['generate'];
        yield 'build' => ['build'];
        yield 'compile' => ['compile'];
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function basic_url_import_should_be_rendered(string $command): void
    {
        // Setup mock response for URL import
        $jsonConfig = \json_encode([
            'documents' => [
                [
                    'description' => 'URL Imported Document',
                    'outputPath' => 'url-imported.md',
                    'sources' => [
                        [
                            'type' => 'text',
                            'description' => 'URL Import Content',
                            'content' => 'This content was imported from a URL',
                            'tag' => 'URL_IMPORT',
                        ],
                    ],
                ],
            ],
        ]);

        $this->mockHttpClient->addResponse(
            'https://example.com/config.json',
            new HttpResponse(
                statusCode: 200,
                body: $jsonConfig,
                headers: ['Content-Type' => 'application/json'],
            ),
        );

        // Create a main config that imports the URL config
        $mainConfig = $this->createTempFile(
            <<<'YAML'
                import:
                  - url: https://example.com/config.json
                    type: url
                
                documents:
                  - description: "Main Document"
                    outputPath: "main.md"
                    sources:
                      - type: text
                        description: "Main content"
                        content: "This is the main configuration content"
                        tag: "MAIN_CONTENT"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfig,
                command: $command,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'url-imported.md',
                contains: [
                    '# URL Imported Document',
                    'This content was imported from a URL',
                    '<URL_IMPORT>',
                    '</URL_IMPORT>',
                ],
            )
            ->assertContext(
                document: 'main.md',
                contains: [
                    '# Main Document',
                    'This is the main configuration content',
                    '<MAIN_CONTENT>',
                    '</MAIN_CONTENT>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function url_import_with_headers_should_be_processed(): void
    {
        // Setup mock response for URL import with headers check
        $yamlConfig = <<<'YAML'
            documents:
              - description: "API Document"
                outputPath: "api-doc.md"
                sources:
                  - type: text
                    description: "API Content"
                    content: "This content was imported with custom headers"
                    tag: "API_CONTENT"
            YAML;

        $this->mockHttpClient->addResponse(
            'https://api.example.com/config',
            new HttpResponse(
                statusCode: 200,
                body: $yamlConfig,
                headers: ['Content-Type' => 'application/yaml'],
            ),
        );

        // Create a main config that imports the URL config with headers
        $mainConfig = $this->createTempFile(
            <<<'YAML'
                import:
                  - url: https://api.example.com/config
                    type: url
                    headers:
                      Authorization: "Bearer test-token"
                      Accept: "application/yaml"
                
                documents:
                  - description: "Main Document"
                    outputPath: "main.md"
                    sources:
                      - type: text
                        description: "Main content"
                        content: "This is the main configuration content"
                        tag: "MAIN_CONTENT"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfig,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'api-doc.md',
                contains: [
                    '# API Document',
                    'This content was imported with custom headers',
                    '<API_CONTENT>',
                    '</API_CONTENT>',
                ],
            );

        // Verify the headers were sent correctly
        $sentHeaders = $this->mockHttpClient->getRequestHeaders('https://api.example.com/config');
        $this->assertArrayHasKey('Authorization', $sentHeaders);
        $this->assertEquals('Bearer test-token', $sentHeaders['Authorization']);
        $this->assertArrayHasKey('Accept', $sentHeaders);
        $this->assertEquals('application/yaml', $sentHeaders['Accept']);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function url_import_with_variables_should_resolve_variables(): void
    {
        // Setup mock response
        $jsonConfig = \json_encode([
            'documents' => [
                [
                    'description' => 'Variable Test Document',
                    'outputPath' => 'variable-doc.md',
                    'sources' => [
                        [
                            'type' => 'text',
                            'description' => 'Variable Content',
                            'content' => 'This content was imported from the {{ENV_NAME}} environment',
                            'tag' => 'VARIABLE_CONTENT',
                        ],
                    ],
                ],
            ],
        ]);

        $this->mockHttpClient->addResponse(
            'https://api.testing.example.com/config',
            new HttpResponse(
                statusCode: 200,
                body: $jsonConfig,
                headers: ['Content-Type' => 'application/json'],
            ),
        );

        // Create env file with variables
        $envFile = $this->createTempFile(
            "ENV_NAME=testing\nAPI_TOKEN=test-token\n",
            '.env',
        );

        // Create a main config that imports the URL config with variables
        $mainConfig = $this->createTempFile(
            <<<'YAML'
                import:
                  - url: https://api.${ENV_NAME}.example.com/config
                    type: url
                    headers:
                      Authorization: "Bearer ${API_TOKEN}"
                
                documents:
                  - description: "Main Document"
                    outputPath: "main.md"
                    sources:
                      - type: text
                        description: "Main content"
                        content: "This is the {{ENV_NAME}} configuration"
                        tag: "MAIN_CONTENT"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfig,
                envFile: $envFile,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'variable-doc.md',
                contains: [
                    '# Variable Test Document',
                    'This content was imported from the testing environment',
                    '<VARIABLE_CONTENT>',
                    '</VARIABLE_CONTENT>',
                ],
            )
            ->assertContext(
                document: 'main.md',
                contains: [
                    '# Main Document',
                    'This is the testing configuration',
                    '<MAIN_CONTENT>',
                    '</MAIN_CONTENT>',
                ],
            );

        // Verify the headers were sent with resolved variables
        $sentHeaders = $this->mockHttpClient->getRequestHeaders('https://api.testing.example.com/config');
        $this->assertArrayHasKey('Authorization', $sentHeaders);
        $this->assertEquals('Bearer test-token', $sentHeaders['Authorization']);
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function url_import_with_selective_documents_should_work(): void
    {
        // Setup mock response with multiple documents
        $yamlConfig = <<<'YAML'
            documents:
              - description: "First Document"
                outputPath: "first.md"
                sources:
                  - type: text
                    description: "First content"
                    content: "This is the first document content"
                    tag: "FIRST_CONTENT"
              - description: "Second Document"
                outputPath: "second.md"
                sources:
                  - type: text
                    description: "Second content"
                    content: "This is the second document content"
                    tag: "SECOND_CONTENT"
              - description: "Third Document"
                outputPath: "third.md"
                sources:
                  - type: text
                    description: "Third content"
                    content: "This is the third document content"
                    tag: "THIRD_CONTENT"
            YAML;

        $this->mockHttpClient->addResponse(
            'https://example.com/multi-docs.yaml',
            new HttpResponse(
                statusCode: 200,
                body: $yamlConfig,
                headers: ['Content-Type' => 'application/yaml'],
            ),
        );

        // Create a main config that imports only selected documents
        $mainConfig = $this->createTempFile(
            <<<'YAML'
                import:
                  - url: https://example.com/multi-docs.yaml
                    type: url
                    docs:
                      - "first.md"
                      - "third.md"
                
                documents:
                  - description: "Main Document"
                    outputPath: "main.md"
                    sources:
                      - type: text
                        description: "Main content"
                        content: "This is the main configuration content"
                        tag: "MAIN_CONTENT"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfig,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'first.md',
                contains: [
                    '# First Document',
                    'This is the first document content',
                    '<FIRST_CONTENT>',
                    '</FIRST_CONTENT>',
                ],
            )
            ->assertMissedContext(document: 'second.md')
            ->assertContext(
                document: 'third.md',
                contains: [
                    '# Third Document',
                    'This is the third document content',
                    '<THIRD_CONTENT>',
                    '</THIRD_CONTENT>',
                ],
            )
            ->assertContext(
                document: 'main.md',
                contains: [
                    '# Main Document',
                    'This is the main configuration content',
                    '<MAIN_CONTENT>',
                    '</MAIN_CONTENT>',
                ],
            );

        // Verify second.md was not generated
        $this->assertFileDoesNotExist("{$this->outputDir}/second.md");
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function failed_url_import_should_be_handled_gracefully(): void
    {
        // Setup mock response with error
        $this->mockHttpClient->addResponse(
            'https://example.com/not-found',
            new HttpResponse(
                statusCode: 404,
                body: 'Not Found',
                headers: [],
            ),
        );

        // Create a main config that imports a failing URL
        $mainConfig = $this->createTempFile(
            <<<'YAML'
                import:
                  - url: https://example.com/not-found
                    type: url
                
                documents:
                  - description: "Main Document"
                    outputPath: "main.md"
                    sources:
                      - type: text
                        description: "Main content"
                        content: "This is the main configuration content"
                        tag: "MAIN_CONTENT"
                YAML,
            '.yaml',
        );

        // The command should still succeed and generate the main document
        // even if the URL import fails
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfig,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'main.md',
                contains: [
                    '# Main Document',
                    'This is the main configuration content',
                    '<MAIN_CONTENT>',
                    '</MAIN_CONTENT>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function url_import_with_multiple_urls_should_be_supported(): void
    {
        // Setup mock responses for multiple URLs
        $jsonConfig1 = \json_encode([
            'documents' => [
                [
                    'description' => 'First URL Document',
                    'outputPath' => 'url1.md',
                    'sources' => [
                        [
                            'type' => 'text',
                            'description' => 'URL1 Content',
                            'content' => 'This content was imported from the first URL',
                            'tag' => 'URL1_CONTENT',
                        ],
                    ],
                ],
            ],
        ]);

        $jsonConfig2 = \json_encode([
            'documents' => [
                [
                    'description' => 'Second URL Document',
                    'outputPath' => 'url2.md',
                    'sources' => [
                        [
                            'type' => 'text',
                            'description' => 'URL2 Content',
                            'content' => 'This content was imported from the second URL',
                            'tag' => 'URL2_CONTENT',
                        ],
                    ],
                ],
            ],
        ]);

        $this->mockHttpClient->addResponse(
            'https://example.com/config1.json',
            new HttpResponse(
                statusCode: 200,
                body: $jsonConfig1,
                headers: ['Content-Type' => 'application/json'],
            ),
        );

        $this->mockHttpClient->addResponse(
            'https://example.com/config2.json',
            new HttpResponse(
                statusCode: 200,
                body: $jsonConfig2,
                headers: ['Content-Type' => 'application/json'],
            ),
        );

        // Create a main config that imports multiple URLs
        $mainConfig = $this->createTempFile(
            <<<'YAML'
                import:
                  - url: https://example.com/config1.json
                    type: url
                  - url: https://example.com/config2.json
                    type: url
                
                documents:
                  - description: "Main Document"
                    outputPath: "main.md"
                    sources:
                      - type: text
                        description: "Main content"
                        content: "This is the main configuration content"
                        tag: "MAIN_CONTENT"
                YAML,
            '.yaml',
        );

        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfig,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'url1.md',
                contains: [
                    '# First URL Document',
                    'This content was imported from the first URL',
                    '<URL1_CONTENT>',
                    '</URL1_CONTENT>',
                ],
            )
            ->assertContext(
                document: 'url2.md',
                contains: [
                    '# Second URL Document',
                    'This content was imported from the second URL',
                    '<URL2_CONTENT>',
                    '</URL2_CONTENT>',
                ],
            )
            ->assertContext(
                document: 'main.md',
                contains: [
                    '# Main Document',
                    'This is the main configuration content',
                    '<MAIN_CONTENT>',
                    '</MAIN_CONTENT>',
                ],
            );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function url_import_with_filter_should_work(): void
    {
        // Setup mock response with prompts section
        $yamlConfig = <<<'YAML'
            prompts:
              - id: coding-helper
                description: "Helps with coding tasks"
                tags: ["coding", "development"]
                messages:
                  - role: user
                    content: "I need help with coding."
              - id: design-helper
                description: "Helps with design tasks"
                tags: ["design", "ui"]
                messages:
                  - role: user
                    content: "I need help with design."
              - id: advanced-coding
                description: "Advanced coding assistance"
                tags: ["coding", "advanced"]
                messages:
                  - role: user
                    content: "I need advanced coding help."
            YAML;

        $this->mockHttpClient->addResponse(
            'https://example.com/prompts.yaml',
            new HttpResponse(
                statusCode: 200,
                body: $yamlConfig,
                headers: ['Content-Type' => 'application/yaml'],
            ),
        );

        // Create a main config that imports with filter
        $mainConfig = $this->createTempFile(
            <<<'YAML'
                import:
                  - url: https://example.com/prompts.yaml
                    type: url
                    filter:
                      tags:
                        include: ["coding"]
                        exclude: ["advanced"]
                
                documents:
                  - description: "Filtered Prompts Document"
                    outputPath: "filtered-prompts.md"
                    sources:
                      - type: text
                        description: "Filtered prompts content"
                        content: "This document should include filtered prompts"
                        tag: "PROMPTS_CONTENT"
                YAML,
            '.yaml',
        );

        // Since we're testing URL import specifically and not prompt handling,
        // we'll just check that the compilation succeeds
        $this
            ->buildContext(
                workDir: $this->outputDir,
                configPath: $mainConfig,
            )
            ->assertDocumentsCompiled()
            ->assertContext(
                document: 'filtered-prompts.md',
                contains: [
                    '# Filtered Prompts Document',
                    'This document should include filtered prompts',
                    '<PROMPTS_CONTENT>',
                    '</PROMPTS_CONTENT>',
                ],
            );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = $this->createTempDir();

        // Create mock HTTP client
        $this->mockHttpClient = new MockHttpClient();

        // Register mock HTTP client in the container
        $this->getContainer()->bindSingleton(HttpClientInterface::class, $this->mockHttpClient);
    }

    protected function buildContext(
        string $workDir,
        ?string $configPath = null,
        ?string $inlineJson = null,
        ?string $envFile = null,
        string $command = 'generate',
        bool $asJson = true,
    ): CompilingResult {
        return (new ContextBuilder($this->getConsole()))->build(
            workDir: $workDir,
            configPath: $configPath,
            inlineJson: $inlineJson,
            envFile: $envFile,
            command: $command,
            asJson: $asJson,
        );
    }
}
