<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Tool;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Tests\Feature\FeatureTestCases;

final class BasicToolConfigTest extends FeatureTestCases
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('McpServer/Tool/basic_tools.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        // Verify tools registry exists
        $tools = $config->getTools();
        $this->assertNotNull($tools);

        // Verify expected tools exist
        $this->assertTrue($tools->has('test-command-tool'));

        // Verify tool properties
        $tool = $tools->get('test-command-tool');
        $this->assertInstanceOf(ToolDefinition::class, $tool);
        $this->assertSame('test-command-tool', $tool->id);
        $this->assertSame('A test command tool', $tool->description);
        $this->assertSame('run', $tool->type);

        // Verify commands
        $this->assertCount(1, $tool->commands);
        $command = $tool->commands[0];
        $this->assertSame('echo', $command->cmd);
        $this->assertCount(1, $command->args);
        $this->assertSame('Hello, World!', (string) $command->args[0]);

        // Verify schema
        $this->assertNotNull($tool->schema);
        $properties = $tool->schema->getProperties();
        $this->assertArrayHasKey('message', $properties);
        $this->assertSame('string', $properties['message']['type']);
    }
}
