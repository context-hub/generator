<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Tool;

use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\HttpClient\HttpResponse;
use Butschster\ContextGenerator\Lib\Variable\Provider\ConfigVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutorInterface;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolArg;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolCommand;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolSchema;
use Butschster\ContextGenerator\McpServer\Tool\Types\HttpToolHandler;
use Butschster\ContextGenerator\McpServer\Tool\Types\RunToolHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\AppTestCase;

final class ToolHandlerTest extends AppTestCase
{
    /**
     * Test the execution of a run tool with variable arguments
     */
    #[Test]
    public function testRunToolExecution(): void
    {
        // Create mock for CommandExecutorInterface
        /** @var CommandExecutorInterface&MockObject $commandExecutor */
        $commandExecutor = $this->createMock(CommandExecutorInterface::class);

        // Configure mock to return a successful execution result
        $commandExecutor
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (ToolCommand $command) {
                // Verify the command has been processed correctly with arguments
                $this->assertSame('echo', $command->cmd);
                $this->assertCount(1, $command->args);
                $this->assertSame('test message', (string) $command->args[0]);

                return [
                    'output' => "test message\n",
                    'exitCode' => 0,
                ];
            });

        // Create the RunToolHandler with our mock
        $handler = new RunToolHandler($commandExecutor);

        // Create a ToolDefinition with a variable argument
        $tool = new ToolDefinition(
            id: 'test-run-tool',
            description: 'Test run tool',
            type: 'run',
            commands: [
                new ToolCommand(
                    cmd: 'echo',
                    args: [
                        new ToolArg('{{message}}'),
                    ],
                ),
            ],
            schema: ToolSchema::fromArray([
                'properties' => [
                    'message' => [
                        'type' => 'string',
                        'description' => 'The message to echo',
                    ],
                ],
            ]),
        );

        // Execute the tool with arguments
        $result = $handler->execute($tool, ['message' => 'test message']);

        // Verify the result
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('test message', $result['output']);
        $this->assertCount(1, $result['commands']);
        $this->assertSame(0, $result['commands'][0]['exitCode']);
    }

    /**
     * Test HTTP tool execution with variable replacement
     */
    #[Test]
    public function testHttpToolExecution(): void
    {
        // Create mock for HttpClientInterface
        /** @var HttpClientInterface&MockObject $httpClient */
        $httpClient = $this->createMock(HttpClientInterface::class);

        // Configure mock to return a successful response
        $httpClient
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $url, array $headers) {
                // Verify URL and headers have been processed correctly
                $this->assertSame('https://example.com/api/test?param=value', $url);
                $this->assertArrayHasKey('Authorization', $headers);
                $this->assertSame('Bearer test-token', $headers['Authorization']);

                // Create mock response
                $response = $this->createMock(HttpResponse::class);
                $response->method('isSuccess')->willReturn(true);
                $response->method('getJson')->willReturn(['success' => true, 'data' => 'test']);
                $response->method('getBody')->willReturn('{"success":true,"data":"test"}');

                return $response;
            });

        $variableResolver = $this->get(VariableResolver::class)->with(
            new VariableReplacementProcessor(new ConfigVariableProvider([
                'endpoint' => 'test',
                'token' => 'test-token',
            ])),
        );

        // Create the HttpToolHandler with our mocks
        $handler = new HttpToolHandler($httpClient, $variableResolver);

        // Create a ToolDefinition for an HTTP GET request
        $tool = new ToolDefinition(
            id: 'test-http-tool',
            description: 'Test HTTP tool',
            type: 'http',
            schema: ToolSchema::fromArray([
                'properties' => [
                    'endpoint' => [
                        'type' => 'string',
                        'description' => 'API endpoint',
                    ],
                    'token' => [
                        'type' => 'string',
                        'description' => 'Auth token',
                    ],
                    'param' => [
                        'type' => 'string',
                        'description' => 'Query parameter',
                    ],
                ],
            ]),
            extra: [
                'requests' => [
                    [
                        'url' => 'https://example.com/api/{{endpoint}}',
                        'method' => 'GET',
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer {{token}}',
                        ],
                        'query' => [
                            'param' => 'value',
                        ],
                    ],
                ],
            ],
        );

        // Execute the tool with arguments
        $result = $handler->execute($tool, [
            'endpoint' => 'test',
            'token' => 'test-token',
            'param' => 'value',
        ]);

        // Verify the result
        $this->assertStringContainsString('success', $result['output']);
    }
}
