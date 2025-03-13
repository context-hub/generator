<?php

declare(strict_types=1);

namespace Tests\Loader;

use Butschster\ContextGenerator\Document;
use Butschster\ContextGenerator\DocumentRegistry;
use Butschster\ContextGenerator\Loader\JsonConfigParser;
use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\Source\Github\GithubSource;
use Butschster\ContextGenerator\Source\Text\TextSource;
use Butschster\ContextGenerator\Source\Url\UrlSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class JsonConfigParserTest extends TestCase
{
    private string $rootPath;

    #[Test]
    public function it_should_parse_config_with_all_source_types(): void
    {
        $parser = new JsonConfigParser(rootPath: $this->rootPath);

        $config = [
            'documents' => [
                [
                    'description' => 'Test Document',
                    'outputPath' => '/output/path.md',
                    'overwrite' => true,
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => ['/path/to/source'],
                            'filePattern' => '*.php',
                        ],
                        [
                            'type' => 'url',
                            'urls' => ['https://example.com'],
                        ],
                        [
                            'type' => 'text',
                            'content' => 'Sample text content',
                        ],
                        [
                            'type' => 'github',
                            'repository' => 'testuser/testrepo',
                            'sourcePaths' => ['path/to/file.md'],
                            'branch' => 'main',
                        ],
                    ],
                ],
            ],
        ];

        $registry = $parser->parse($config);

        $this->assertInstanceOf(DocumentRegistry::class, $registry);
        $documents = $registry->getDocuments();
        $this->assertCount(1, $documents);

        $document = $documents[0];
        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals('Test Document', $document->description);
        $this->assertEquals('/output/path.md', $document->outputPath);
        $this->assertTrue($document->overwrite);

        $sources = $document->getSources();
        $this->assertCount(4, $sources);
        $this->assertInstanceOf(FileSource::class, $sources[0]);
        $this->assertInstanceOf(UrlSource::class, $sources[1]);
        $this->assertInstanceOf(TextSource::class, $sources[2]);
        $this->assertInstanceOf(GithubSource::class, $sources[3]);
    }

    #[Test]
    public function it_should_throw_exception_for_document_without_required_fields(): void
    {
        $parser = new JsonConfigParser(rootPath: $this->rootPath);

        $config = [
            'documents' => [
                [
                    // Missing description and outputPath
                    'sources' => [],
                ],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Document at index 0 must have "description" and "outputPath"');

        $parser->parse($config);
    }

    #[Test]
    public function it_should_throw_exception_for_source_without_type(): void
    {
        $parser = new JsonConfigParser(rootPath: $this->rootPath);

        $config = [
            'documents' => [
                [
                    'description' => 'Test Document',
                    'outputPath' => '/output/path.md',
                    'sources' => [
                        [
                            // Missing type
                            'sourcePaths' => ['/path/to/source'],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source at path 0.0 must have a "type" property');

        $parser->parse($config);
    }

    #[Test]
    public function it_should_throw_exception_for_unknown_source_type(): void
    {
        $parser = new JsonConfigParser(rootPath: $this->rootPath);

        $config = [
            'documents' => [
                [
                    'description' => 'Test Document',
                    'outputPath' => '/output/path.md',
                    'sources' => [
                        [
                            'type' => 'unknown',
                            'content' => 'Sample content',
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown source type "unknown" at path 0.0');

        $parser->parse($config);
    }

    #[Test]
    public function it_should_parse_modifiers_configuration(): void
    {
        $parser = new JsonConfigParser(rootPath: $this->rootPath);

        $config = [
            'documents' => [
                [
                    'description' => 'Test Document',
                    'outputPath' => '/output/path.md',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => ['/path/to/source'],
                            'filePattern' => '*.php',
                            'modifiers' => [
                                'simple-modifier',
                                [
                                    'name' => 'complex-modifier',
                                    'options' => [
                                        'option1' => 'value1',
                                        'option2' => 'value2',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $registry = $parser->parse($config);
        $document = $registry->getDocuments()[0];
        $source = $document->getSources()[0];

        $this->assertInstanceOf(FileSource::class, $source);
        $this->assertCount(2, $source->modifiers);
        $this->assertEquals('simple-modifier', $source->modifiers[0]);
        $this->assertIsArray($source->modifiers[1]);
        $this->assertEquals('complex-modifier', $source->modifiers[1]['name']);
        $this->assertIsArray($source->modifiers[1]['options']);
        $this->assertEquals('value1', $source->modifiers[1]['options']['option1']);
        $this->assertEquals('value2', $source->modifiers[1]['options']['option2']);
    }

    #[Test]
    public function it_should_throw_exception_for_invalid_modifier_format(): void
    {
        $parser = new JsonConfigParser(rootPath: $this->rootPath);

        $config = [
            'documents' => [
                [
                    'description' => 'Test Document',
                    'outputPath' => '/output/path.md',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => ['/path/to/source'],
                            'filePattern' => '*.php',
                            'modifiers' => [
                                123, // Invalid modifier format (not a string or array)
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid modifier format:');

        $parser->parse($config);
    }

    #[Test]
    public function it_should_parse_environment_variables_in_config_values(): void
    {
        // Set a test environment variable
        \putenv('TEST_ENV_VAR=test_value');

        $parser = new JsonConfigParser(rootPath: $this->rootPath);

        $config = [
            'documents' => [
                [
                    'description' => 'Test Document',
                    'outputPath' => '/output/path.md',
                    'sources' => [
                        [
                            'type' => 'github',
                            'repository' => 'testuser/testrepo',
                            'sourcePaths' => ['path/to/file.md'],
                            'branch' => 'main',
                            'githubToken' => '${TEST_ENV_VAR}',
                        ],
                    ],
                ],
            ],
        ];

        $registry = $parser->parse($config);
        $document = $registry->getDocuments()[0];
        $source = $document->getSources()[0];

        $this->assertInstanceOf(GithubSource::class, $source);

        // Use reflection to access the private githubToken property
        $reflection = new \ReflectionClass($source);
        $property = $reflection->getProperty('githubToken');
        $property->setAccessible(true);
        $githubToken = $property->getValue($source);

        $this->assertEquals('test_value', $githubToken);

        // Clean up environment variable
        \putenv('TEST_ENV_VAR');
    }

    protected function setUp(): void
    {
        $this->rootPath = \dirname(__DIR__, 3);
    }
}
