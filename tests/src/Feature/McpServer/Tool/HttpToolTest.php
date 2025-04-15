<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Tool;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\Types\HttpToolHandler;
use Tests\Feature\FeatureTestCases;

final class HttpToolTest extends FeatureTestCases
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('McpServer/Tool/http_tools.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        // Get the tools registry
        $tools = $config->getTools();
        $this->assertNotNull($tools);

        // Verify both HTTP tools exist
        $this->assertTrue($tools->has('http-get-tool'));
        $this->assertTrue($tools->has('http-post-tool'));

        // Test GET tool configuration
        $getTool = $tools->get('http-get-tool');
        $this->assertInstanceOf(ToolDefinition::class, $getTool);
        $this->assertSame('http-get-tool', $getTool->id);
        $this->assertSame('A HTTP GET tool', $getTool->description);
        $this->assertSame('http', $getTool->type);

        // Verify request configuration is stored in extra
        $this->assertArrayHasKey('requests', $getTool->extra);
        $this->assertIsArray($getTool->extra['requests']);
        $this->assertCount(1, $getTool->extra['requests']);

        // Verify request details
        $getRequest = $getTool->extra['requests'][0];
        $this->assertSame('https://example.com/api/{{endpoint}}', $getRequest['url']);
        $this->assertSame('GET', $getRequest['method']);
        $this->assertArrayHasKey('headers', $getRequest);
        $this->assertSame('application/json', $getRequest['headers']['Content-Type']);
        $this->assertSame('Bearer {{token}}', $getRequest['headers']['Authorization']);

        // Verify schema properties
        $this->assertNotNull($getTool->schema);
        $getProperties = $getTool->schema->getProperties();
        $this->assertArrayHasKey('endpoint', $getProperties);
        $this->assertArrayHasKey('token', $getProperties);

        // Test POST tool configuration
        $postTool = $tools->get('http-post-tool');
        $this->assertInstanceOf(ToolDefinition::class, $postTool);
        $this->assertSame('http-post-tool', $postTool->id);
        $this->assertSame('A HTTP POST tool', $postTool->description);
        $this->assertSame('http', $postTool->type);

        // Verify request configuration
        $this->assertArrayHasKey('requests', $postTool->extra);
        $this->assertIsArray($postTool->extra['requests']);
        $this->assertCount(1, $postTool->extra['requests']);

        // Verify request details
        $postRequest = $postTool->extra['requests'][0];
        $this->assertSame('https://example.com/api/submit', $postRequest['url']);
        $this->assertSame('POST', $postRequest['method']);
        $this->assertArrayHasKey('headers', $postRequest);
        $this->assertSame('application/json', $postRequest['headers']['Content-Type']);
        $this->assertArrayHasKey('body', $postRequest);
        $this->assertSame('{{data}}', $postRequest['body']['data']);

        // Verify schema properties
        $this->assertNotNull($postTool->schema);
        $postProperties = $postTool->schema->getProperties();
        $this->assertArrayHasKey('data', $postProperties);

        // Verify that HttpToolHandler would be used for these tools
        $toolHandlerFactory = $this->getContainer()->get(\Butschster\ContextGenerator\McpServer\Tool\ToolHandlerFactory::class);
        $handler = $toolHandlerFactory->createHandlerForTool($getTool);
        $this->assertInstanceOf(HttpToolHandler::class, $handler);
    }
}
