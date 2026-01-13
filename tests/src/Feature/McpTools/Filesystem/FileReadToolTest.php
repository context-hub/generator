<?php

declare(strict_types=1);

namespace Tests\Feature\McpTools\Filesystem;

use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\McpTools\McpToolTestCase;

/**
 * Tests for file-read MCP tool functionality.
 *
 * Note: These tests use custom tools defined in context.yaml.
 * For testing built-in MCP actions, use the action classes directly.
 */
final class FileReadToolTest extends McpToolTestCase
{
    #[Test]
    public function it_executes_simple_echo_tool(): void
    {
        // Create a context.yaml with a simple echo tool
        $this->createWorkConfig([
            'tools' => [
                [
                    'id' => 'ctx:echo',
                    'description' => 'Echo test tool',
                    'type' => 'run',
                    'commands' => [
                        [
                            'cmd' => 'echo',
                            'args' => ['Hello, ${message}'],
                        ],
                    ],
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => [
                                'type' => 'string',
                                'description' => 'Message to echo',
                            ],
                        ],
                        'required' => ['message'],
                    ],
                ],
            ],
        ]);

        // Execute the tool
        $result = $this->executeTool(
            'ctx:echo',
            ['message' => 'World'],
            $this->workDir . '/context.yaml',
        );

        // Assert success
        $this->assertToolSuccess($result);
        $this->assertToolOutputContains($result, 'Hello, World');
    }

    #[Test]
    public function it_executes_cat_tool_to_read_file(): void
    {
        // Create test file
        $this->createWorkFile('test.txt', 'This is test content');

        // Create a context.yaml with a cat tool
        $this->createWorkConfig([
            'tools' => [
                [
                    'id' => 'ctx:read-file',
                    'description' => 'Read file contents',
                    'type' => 'run',
                    'commands' => [
                        [
                            'cmd' => 'cat',
                            'args' => ['${path}'],
                        ],
                    ],
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Path to file',
                            ],
                        ],
                        'required' => ['path'],
                    ],
                ],
            ],
        ]);

        // Execute the tool
        $result = $this->executeTool(
            'ctx:read-file',
            ['path' => 'test.txt'],
            $this->workDir . '/context.yaml',
        );

        // Assert success and content
        $this->assertToolSuccess($result);
        $this->assertToolOutputContains($result, 'This is test content');
    }

    #[Test]
    public function it_returns_json_output_with_output_json_flag(): void
    {
        // Create a context.yaml with a simple tool
        $this->createWorkConfig([
            'tools' => [
                [
                    'id' => 'ctx:hello',
                    'description' => 'Hello tool',
                    'type' => 'run',
                    'commands' => [
                        [
                            'cmd' => 'echo',
                            'args' => ['Hello'],
                        ],
                    ],
                ],
            ],
        ]);

        // Execute the tool
        $result = $this->executeTool(
            'ctx:hello',
            [],
            $this->workDir . '/context.yaml',
        );

        // Assert JSON structure
        $this->assertToolSuccess($result);
        $this->assertToolOutputJson($result, [
            'success' => true,
            'toolId' => 'ctx:hello',
        ]);
    }

    #[Test]
    public function it_can_get_tool_schema(): void
    {
        // Create a context.yaml with a tool
        $this->createWorkConfig([
            'tools' => [
                [
                    'id' => 'ctx:schema-test',
                    'description' => 'Schema test tool',
                    'type' => 'run',
                    'commands' => [
                        [
                            'cmd' => 'echo',
                            'args' => ['test'],
                        ],
                    ],
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'input' => [
                                'type' => 'string',
                                'description' => 'Test input',
                            ],
                        ],
                        'required' => ['input'],
                    ],
                ],
            ],
        ]);

        // Get schema
        $schema = $this->getToolSchema(
            'ctx:schema-test',
            $this->workDir . '/context.yaml',
        );

        // Assert schema structure
        $this->assertArrayHasKey('name', $schema);
        $this->assertEquals('ctx:schema-test', $schema['name']);
        $this->assertArrayHasKey('description', $schema);
        $this->assertArrayHasKey('inputSchema', $schema);
        $this->assertArrayHasKey('properties', $schema['inputSchema']);
        $this->assertArrayHasKey('input', $schema['inputSchema']['properties']);
    }
}
