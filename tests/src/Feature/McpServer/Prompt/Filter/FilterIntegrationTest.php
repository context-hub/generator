<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Prompt\Filter;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
use Butschster\ContextGenerator\McpServer\Prompt\PromptProviderInterface;
use Tests\Feature\FeatureTestCases;

/**
 * Integration tests for prompt import filtering with fixture config files.
 */
final class FilterIntegrationTest extends FeatureTestCases
{
    protected function getConfigPath(): string
    {
        return $this->getFixturesDir('McpServer/Prompts/prompt_filters.yaml');
    }

    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        $prompts = $config->getPrompts();
        $this->assertInstanceOf(PromptProviderInterface::class, $prompts);

        // Verify original prompts exist
        $this->assertTrue($prompts->has('basic-prompt'));
        $this->assertTrue($prompts->has('advanced-prompt'));
        $this->assertTrue($prompts->has('template-prompt'));

        // Verify ID filter worked
        $this->assertTrue($prompts->has('imported-prompt1'), 'Prompt included by ID filter should exist');
        $this->assertTrue($prompts->has('imported-prompt3'), 'Prompt included by ID filter should exist');

        // Verify tag include filter worked for 'include-tag'
        $this->assertTrue($prompts->has('imported-prompt1'), 'Prompt with include tag should exist');

        // Verify tag exclude filter worked for 'exclude-tag'
        // Note: imported-prompt3 would be excluded by tag filter but included by ID filter,
        // so we need to check a prompt that only tag filter would affect
        $this->assertFalse(
            $prompts->has('imported-prompt4'),
            'Prompt with both include and exclude tags should be excluded',
        );

        // Verify composite filter (ID=imported-prompt1 AND tag=special-tag) worked
        $importedPrompt1 = $prompts->get('imported-prompt1');
        $this->assertInstanceOf(PromptDefinition::class, $importedPrompt1);

        // Special tag should be in the tags of imported-prompt1
        $hasTags = \in_array('special-tag', $importedPrompt1->tags, true);
        $this->assertTrue($hasTags, 'Prompt should have special-tag for composite filter');

        // imported-prompt5 has special-tag but wasn't included in ID list, so should be excluded
        $this->assertFalse(
            $prompts->has('imported-prompt5'),
            'Prompt with special tag but wrong ID should be excluded',
        );
    }
}
