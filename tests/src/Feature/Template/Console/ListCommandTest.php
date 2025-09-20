<?php

declare(strict_types=1);

namespace Tests\Feature\Template\Console;

use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

final class ListCommandTest extends ConsoleTestCase
{
    #[Test]
    public function lists_available_templates_in_basic_format(): void
    {
        $result = $this->runCommand('template:list');

        $this->assertStringContainsString('Available Templates', $result);
        $this->assertStringContainsString('Name', $result);
        $this->assertStringContainsString('Description', $result);
        $this->assertStringContainsString('Tags', $result);
        $this->assertStringContainsString('Priority', $result);

        // Should contain at least some built-in templates
        $this->assertStringContainsString('laravel', $result);
        $this->assertStringContainsString('symfony', $result);
        $this->assertStringContainsString('generic-php', $result);

        // Should show usage note
        $this->assertStringContainsString('ctx init <template-name>', $result);
    }

    #[Test]
    public function lists_templates_in_detailed_format(): void
    {
        $result = $this->runCommand('template:list', ['--detailed' => true]);

        $this->assertStringContainsString('Available Templates', $result);

        // Should show detailed information
        $this->assertStringContainsString('Priority', $result);
        $this->assertStringContainsString('Tags', $result);
        $this->assertStringContainsString('Detection Criteria', $result);
        $this->assertStringContainsString('Generated Documents', $result);

        // Should contain template names as sections
        $this->assertStringContainsString('laravel', $result);
        $this->assertStringContainsString('symfony', $result);

        // Should show usage notes
        $this->assertStringContainsString('ctx init <template-name>', $result);
        $this->assertStringContainsString('ctx init --show-all', $result);
    }

    #[Test]
    public function filters_templates_by_single_tag(): void
    {
        $result = $this->runCommand('template:list', ['--tag' => 'php']);

        $this->assertStringContainsString('Available Templates', $result);

        // Should contain PHP-related templates
        $this->assertStringContainsString('laravel', $result);
        $this->assertStringContainsString('symfony', $result);
        $this->assertStringContainsString('generic-php', $result);

        // Should not contain JavaScript-only templates if they exist
        // (This depends on the actual templates available)
    }

    #[Test]
    public function filters_templates_by_multiple_tags(): void
    {
        $result = $this->runCommand('template:list', ['--tag' => ['php', 'framework']]);

        $this->assertStringContainsString('Available Templates', $result);

        // Should contain framework templates that have both php and framework tags
        $this->assertStringContainsString('laravel', $result);
        $this->assertStringContainsString('symfony', $result);
    }

    #[Test]
    public function shows_warning_when_no_templates_match_filter(): void
    {
        $result = $this->runCommand('template:list', ['--tag' => 'nonexistent-tag']);

        $this->assertStringContainsString('No templates found with tag(s): nonexistent-tag', $result);
    }

    #[Test]
    public function shows_warning_when_no_templates_available(): void
    {
        // This test would require mocking the template registry to return empty results
        // For now, we'll skip this as it requires dependency injection setup
        $this->markTestSkipped('Requires mocking TemplateRegistry which has complex dependencies');
    }

    #[Test]
    public function detailed_view_shows_detection_criteria(): void
    {
        $result = $this->runCommand('template:list', ['--detailed' => true]);

        // Should show detection criteria details
        $this->assertStringContainsString('Detection Criteria', $result);
        $this->assertStringContainsString('Required Files', $result);
        $this->assertStringContainsString('Expected Directories', $result);
        $this->assertStringContainsString('Required Packages', $result);
    }

    #[Test]
    public function detailed_view_shows_generated_documents(): void
    {
        $result = $this->runCommand('template:list', ['--detailed' => true]);

        // Should show information about generated documents
        $this->assertStringContainsString('Generated Documents', $result);

        // Should show document mappings (description → output path)
        $this->assertStringContainsString('→', $result);
    }

    #[Test]
    public function basic_and_detailed_views_both_show_usage_notes(): void
    {
        $basicResult = $this->runCommand('template:list');
        $detailedResult = $this->runCommand('template:list', ['--detailed' => true]);

        // Both should show usage information
        $this->assertStringContainsString('ctx init <template-name>', $basicResult);
        $this->assertStringContainsString('ctx init <template-name>', $detailedResult);

        // Detailed view should show additional options
        $this->assertStringContainsString('ctx init --show-all', $detailedResult);
    }

    #[Test]
    public function can_use_shortcut_options(): void
    {
        // Test shortcut for detailed
        $detailedResult = $this->runCommand('template:list', ['-d' => true]);
        $this->assertStringContainsString('Detection Criteria', $detailedResult);

        // Test shortcut for tag filter
        $tagResult = $this->runCommand('template:list', ['-t' => 'php']);
        $this->assertStringContainsString('Available Templates', $tagResult);
    }

    #[Test]
    public function combines_tag_filter_with_detailed_view(): void
    {
        $result = $this->runCommand('template:list', [
            '--tag' => 'php',
            '--detailed' => true,
        ]);

        $this->assertStringContainsString('Available Templates', $result);
        $this->assertStringContainsString('Detection Criteria', $result);
        $this->assertStringContainsString('Generated Documents', $result);

        // Should contain PHP templates
        $this->assertStringContainsString('laravel', $result);
        $this->assertStringContainsString('symfony', $result);
    }
}
