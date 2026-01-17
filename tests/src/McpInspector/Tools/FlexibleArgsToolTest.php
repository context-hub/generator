<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

/**
 * Tests for flexible arguments and blocked properties feature.
 */
#[Group('mcp-inspector')]
final class FlexibleArgsToolTest extends McpInspectorTestCase
{
    #[Test]
    public function it_unpacks_flexible_args_from_object(): void
    {
        // Act
        $result = $this->inspector->callTool('flexible-echo', [
            'args' => [
                'message' => 'Hello',
                'name' => 'World',
            ],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'message=Hello');
        $this->assertContentContains($result, 'name=World');
    }

    #[Test]
    public function it_unpacks_flexible_args_from_json_string(): void
    {
        // Act
        $result = $this->inspector->callTool('flexible-echo', [
            'args' => '{"message": "JsonTest", "name": "StringInput"}',
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'message=JsonTest');
        $this->assertContentContains($result, 'name=StringInput');
    }

    #[Test]
    public function it_blocks_forbidden_arguments(): void
    {
        // Act
        $result = $this->inspector->callTool('flexible-echo', [
            'args' => [
                'message' => 'Test',
                'password' => 'secret123',
            ],
        ]);

        // Assert
        $this->assertToolError($result);
        $this->assertContentContains($result, 'password');
        $this->assertContentContains($result, 'blocked');
    }

    #[Test]
    public function it_blocks_multiple_forbidden_arguments(): void
    {
        // Act - try with 'secret' which is also blocked
        $result = $this->inspector->callTool('flexible-echo', [
            'args' => [
                'message' => 'Test',
                'secret' => 'mysecret',
            ],
        ]);

        // Assert
        $this->assertToolError($result);
        $this->assertContentContains($result, 'secret');
        $this->assertContentContains($result, 'blocked');
    }

    #[Test]
    public function it_blocks_token_argument(): void
    {
        // Act
        $result = $this->inspector->callTool('flexible-echo', [
            'args' => [
                'message' => 'Test',
                'token' => 'my-api-token',
            ],
        ]);

        // Assert
        $this->assertToolError($result);
        $this->assertContentContains($result, 'token');
        $this->assertContentContains($result, 'blocked');
    }

    #[Test]
    public function it_allows_non_blocked_arbitrary_arguments(): void
    {
        // Act
        $result = $this->inspector->callTool('flexible-echo', [
            'args' => [
                'message' => 'Hello',
                'customArg' => 'CustomValue',
                'anotherArg' => '12345',
            ],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'message=Hello');
    }

    #[Test]
    public function it_executes_bash_command_with_flexible_args(): void
    {
        // Act
        $result = $this->inspector->callTool('test-bash', [
            'args' => [
                'cmd' => 'echo "Hello from bash"',
            ],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'Hello from bash');
    }

    #[Test]
    public function it_executes_complex_bash_command(): void
    {
        // Act
        $result = $this->inspector->callTool('test-bash', [
            'args' => [
                'cmd' => 'date +%Y',
            ],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, '202');
    }

    #[Test]
    public function it_blocks_password_in_bash_tool(): void
    {
        // Act
        $result = $this->inspector->callTool('test-bash', [
            'args' => [
                'cmd' => 'echo test',
                'password' => 'should-be-blocked',
            ],
        ]);

        // Assert
        $this->assertToolError($result);
        $this->assertContentContains($result, 'password');
        $this->assertContentContains($result, 'blocked');
    }

    #[Test]
    public function it_handles_empty_flexible_arg(): void
    {
        // Act
        $result = $this->inspector->callTool('flexible-echo', [
            'args' => [],
        ]);

        // Assert - should work but variables won't be replaced
        $this->assertInspectorSuccess($result);
    }

    #[Test]
    public function it_handles_nested_objects_in_flexible_arg(): void
    {
        // Act
        $result = $this->inspector->callTool('flexible-echo', [
            'args' => [
                'message' => 'Test',
                'config' => ['nested' => 'value'],
            ],
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'message=Test');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create context.yaml with test tools
        $this->createFile('context.yaml', $this->getTestConfig());
    }

    /**
     * Get test configuration with flexible args tools.
     */
    private function getTestConfig(): string
    {
        return <<<'YAML'
documents: []

tools:
  - id: flexible-echo
    description: 'Test tool with flexible arguments'
    type: run
    schema:
      flexibleArg: args
      blockedProperties:
        - password
        - secret
        - token
        - apiKey
      properties:
        args:
          type: object
          description: 'Object with any arguments'
    commands:
      - cmd: echo
        args:
          - "message={{message}}"
          - "name={{name}}"

  - id: test-bash
    description: 'Test bash tool with flexible arguments'
    type: run
    schema:
      flexibleArg: args
      blockedProperties:
        - password
        - secret
        - token
      properties:
        args:
          type: object
          description: 'Object with cmd property'
    commands:
      - cmd: bash
        args:
          - "-c"
          - "{{cmd}}"
YAML;
    }
}
