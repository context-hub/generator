<?php

declare(strict_types=1);

namespace Tests\Feature\McpServer\Prompt\Filter;

use Butschster\ContextGenerator\Config\Import\Source\Config\FilterConfig;
use Butschster\ContextGenerator\Config\Import\Source\ImportedConfig;
use Butschster\ContextGenerator\Config\Import\Source\Local\LocalSourceConfig;
use Butschster\ContextGenerator\McpServer\Prompt\Filter\PromptFilterFactory;
use Butschster\ContextGenerator\McpServer\Prompt\PromptConfigMerger;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * Tests for actual prompt import filtering through the PromptConfigMerger.
 */
final class FilterImportTest extends TestCase
{
    private PromptConfigMerger $merger;
    private PromptFilterFactory $factory;

    #[Test]
    public function mergerShouldApplyIdFilter(): void
    {
        $mainConfig = [
            'prompts' => [
                ['id' => 'existing-prompt', 'description' => 'Existing'],
            ],
        ];

        $importedConfig = [
            'prompts' => [
                ['id' => 'prompt1', 'description' => 'Should be included'],
                ['id' => 'prompt2', 'description' => 'Should be included'],
                ['id' => 'prompt3', 'description' => 'Should be filtered out'],
            ],
        ];

        $filterConfig = [
            'ids' => ['prompt1', 'prompt2'],
        ];

        $sourceConfig = $this->createSourceConfig($filterConfig);
        $wrappedImport = new ImportedConfig($sourceConfig, $importedConfig, 'test-path', true);

        $mergedConfig = $this->merger->merge($mainConfig, $wrappedImport);

        // Verify only the filtered prompts were included
        $this->assertCount(3, $mergedConfig['prompts']);

        $promptIds = \array_map(
            static fn($prompt) => $prompt['id'],
            $mergedConfig['prompts'],
        );

        $this->assertContains('existing-prompt', $promptIds);
        $this->assertContains('prompt1', $promptIds);
        $this->assertContains('prompt2', $promptIds);
        $this->assertNotContains('prompt3', $promptIds);
    }

    #[Test]
    public function mergerShouldApplyTagFilter(): void
    {
        $mainConfig = [
            'prompts' => [
                ['id' => 'existing-prompt', 'description' => 'Existing'],
            ],
        ];

        $importedConfig = [
            'prompts' => [
                ['id' => 'prompt1', 'description' => 'Has include tag', 'tags' => ['include-tag']],
                ['id' => 'prompt2', 'description' => 'Has exclude tag', 'tags' => ['exclude-tag']],
                ['id' => 'prompt3', 'description' => 'Has both tags', 'tags' => ['include-tag', 'exclude-tag']],
                ['id' => 'prompt4', 'description' => 'Has no relevant tags', 'tags' => ['other-tag']],
            ],
        ];

        $filterConfig = [
            'tags' => [
                'include' => ['include-tag'],
                'exclude' => ['exclude-tag'],
            ],
        ];

        $sourceConfig = $this->createSourceConfig($filterConfig);
        $wrappedImport = new ImportedConfig($sourceConfig, $importedConfig, 'test-path', true);

        $mergedConfig = $this->merger->merge($mainConfig, $wrappedImport);

        // Verify filtering worked as expected
        $promptIds = \array_map(
            static fn($prompt) => $prompt['id'],
            $mergedConfig['prompts'],
        );

        $this->assertContains('existing-prompt', $promptIds); // Original prompt
        $this->assertContains('prompt1', $promptIds); // Has include tag
        $this->assertNotContains('prompt2', $promptIds); // Has exclude tag
        $this->assertNotContains('prompt3', $promptIds); // Has both tags (exclude wins)
        $this->assertNotContains('prompt4', $promptIds); // No relevant tags (include required)
    }

    #[Test]
    public function mergerShouldFilterPromptsWithoutIds(): void
    {
        $mainConfig = [
            'prompts' => [
                ['id' => 'existing-prompt', 'description' => 'Existing'],
            ],
        ];

        $importedConfig = [
            'prompts' => [
                ['id' => 'prompt1', 'description' => 'Has ID'],
                ['description' => 'Missing ID'],
            ],
        ];

        $sourceConfig = $this->createSourceConfig(null);
        $wrappedImport = new ImportedConfig($sourceConfig, $importedConfig, 'test-path', true);

        $mergedConfig = $this->merger->merge($mainConfig, $wrappedImport);

        // Verify only prompts with IDs were included
        $this->assertCount(2, $mergedConfig['prompts']);

        $hasDescriptionWithoutId = false;
        foreach ($mergedConfig['prompts'] as $prompt) {
            if ($prompt['description'] === 'Missing ID') {
                $hasDescriptionWithoutId = true;
                break;
            }
        }

        $this->assertFalse($hasDescriptionWithoutId, 'Prompt without ID should be filtered out');
    }

    #[Test]
    public function mergerShouldMergeOverlappingPrompts(): void
    {
        $mainConfig = [
            'prompts' => [
                ['id' => 'prompt1', 'description' => 'Original description'],
            ],
        ];

        $importedConfig = [
            'prompts' => [
                ['id' => 'prompt1', 'description' => 'Updated description'],
            ],
        ];

        $sourceConfig = $this->createSourceConfig(null);
        $wrappedImport = new ImportedConfig($sourceConfig, $importedConfig, 'test-path', true);

        $mergedConfig = $this->merger->merge($mainConfig, $wrappedImport);

        // Verify there's only one prompt and it has the updated description
        $this->assertCount(1, $mergedConfig['prompts']);
        $this->assertEquals('Updated description', $mergedConfig['prompts'][0]['description']);
    }

    #[Test]
    public function mergerShouldIgnoreInvalidSections(): void
    {
        $mainConfig = [
            'prompts' => [
                ['id' => 'existing-prompt', 'description' => 'Existing'],
            ],
        ];

        $importedConfig = [
            'not-prompts' => [
                ['id' => 'invalid-section'],
            ],
        ];

        $sourceConfig = $this->createSourceConfig(null);
        $wrappedImport = new ImportedConfig($sourceConfig, $importedConfig, 'test-path', true);

        $mergedConfig = $this->merger->merge($mainConfig, $wrappedImport);

        // Config should be unchanged
        $this->assertCount(1, $mergedConfig['prompts']);
        $this->assertEquals('existing-prompt', $mergedConfig['prompts'][0]['id']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PromptFilterFactory();
        $this->merger = new PromptConfigMerger(new NullLogger(), $this->factory);
    }

    /**
     * Creates a source config with the specified filter
     */
    private function createSourceConfig(?array $filterConfig): LocalSourceConfig
    {
        $filter = $filterConfig ? new FilterConfig($filterConfig) : null;

        return new LocalSourceConfig(
            path: 'test-path',
            absolutePath: '/absolute/test-path',
            hasWildcard: false,
            filter: $filter,
        );
    }
}
