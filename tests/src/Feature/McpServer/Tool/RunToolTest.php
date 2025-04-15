<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Tool;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolArg;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\ToolHandlerFactory;
use Butschster\ContextGenerator\McpServer\Tool\Types\RunToolHandler;
use Tests\Feature\FeatureTestCases;

final class RunToolTest extends FeatureTestCases
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('McpServer/Tool/run_tools.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        // Get the tools registry
        $tools = $config->getTools();
        $this->assertNotNull($tools);

        // Verify the variable command tool exists
        $this->assertTrue($tools->has('variable-command-tool'));

        // Get the tool and verify its properties
        $tool = $tools->get('variable-command-tool');
        $this->assertInstanceOf(ToolDefinition::class, $tool);
        $this->assertSame('variable-command-tool', $tool->id);
        $this->assertSame('A tool with variable arguments', $tool->description);
        $this->assertSame('run', $tool->type);

        // Verify commands and arguments
        $this->assertCount(1, $tool->commands);
        $command = $tool->commands[0];
        $this->assertSame('echo', $command->cmd);

        // Verify variable argument
        $this->assertCount(2, $command->args);

        // First argument is a simple variable placeholder
        $this->assertSame('{{message}}', (string) $command->args[0]);

        // Second argument is conditional with a "when" clause
        $this->assertInstanceOf(ToolArg::class, $command->args[1]);
        $this->assertSame('--optional-arg', $command->args[1]->name);
        $this->assertSame('{{use_optional}}', $command->args[1]->when);

        // Verify schema properties
        $this->assertNotNull($tool->schema);
        $properties = $tool->schema->getProperties();

        // Check message property
        $this->assertArrayHasKey('message', $properties);
        $this->assertSame('string', $properties['message']['type']);

        // Check use_optional property with its default value
        $this->assertArrayHasKey('use_optional', $properties);
        $this->assertSame('boolean', $properties['use_optional']['type']);
        $this->assertFalse($tool->schema->getDefaultValue('use_optional'));

        // Verify that RunToolHandler would be used for this tool
        $toolHandlerFactory = $this->getContainer()->get(ToolHandlerFactory::class);
        $handler = $toolHandlerFactory->createHandlerForTool($tool);
        $this->assertInstanceOf(RunToolHandler::class, $handler);
    }
}
