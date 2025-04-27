<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Prompt;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
use Tests\Feature\FeatureTestCases;

/**
 * Tests JSON serialization of prompts.
 */
final class JsonSerializationTest extends FeatureTestCases
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('McpServer/Prompts/json_serialization.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        // Test serialization of complete prompt
        $completePrompt = $config->getPrompts()->get('complete-prompt');
        $this->assertInstanceOf(PromptDefinition::class, $completePrompt);

        $serialized = \json_encode($completePrompt);
        $this->assertNotFalse($serialized, 'JSON serialization failed');

        $decoded = \json_decode($serialized, true);
        $this->assertIsArray($decoded);

        // Check that all important fields are present in the serialized output
        $this->assertArrayHasKey('id', $decoded);
        $this->assertArrayHasKey('type', $decoded);
        $this->assertArrayHasKey('description', $decoded);
        $this->assertArrayHasKey('schema', $decoded);
        $this->assertArrayHasKey('messages', $decoded);

        // Check schema structure
        $this->assertArrayHasKey('properties', $decoded['schema']);
        $this->assertArrayHasKey('required', $decoded['schema']);

        // Check that required field contains 'language'
        $this->assertContains('language', $decoded['schema']['required']);

        // Test serialization of minimal template
        $minimalTemplate = $config->getPrompts()->get('minimal-template');
        $serializedTemplate = \json_encode($minimalTemplate);
        $this->assertNotFalse($serializedTemplate, 'Template JSON serialization failed');

        $decodedTemplate = \json_decode($serializedTemplate, true);
        $this->assertIsArray($decodedTemplate);

        // Check that type is set to 'template'
        $this->assertSame('template', $decodedTemplate['type']);

        // Test serialization of extension-only prompt
        $extensionPrompt = $config->getPrompts()->get('extension-only-prompt');
        $serializedExtension = \json_encode($extensionPrompt);
        $this->assertNotFalse($serializedExtension, 'Extension prompt JSON serialization failed');

        $decodedExtension = \json_decode($serializedExtension, true);
        $this->assertIsArray($decodedExtension);

        // Check that extend field is present and properly formatted
        $this->assertArrayHasKey('extend', $decodedExtension);
        $this->assertIsArray($decodedExtension['extend']);
        $this->assertCount(1, $decodedExtension['extend']);
        $this->assertSame('minimal-template', $decodedExtension['extend'][0]['id']);

        // Test registry serialization
        $registry = $config->getPrompts();
        $serializedRegistry = \json_encode($registry);
        $this->assertNotFalse($serializedRegistry, 'Registry JSON serialization failed');

        $decodedRegistry = \json_decode($serializedRegistry, true);
        $this->assertIsArray($decodedRegistry);

        // The registry should only serialize regular prompts, not templates
        foreach ($decodedRegistry as $prompt) {
            $this->assertNotEquals('template', $prompt['type'] ?? 'prompt');
        }
    }
}
