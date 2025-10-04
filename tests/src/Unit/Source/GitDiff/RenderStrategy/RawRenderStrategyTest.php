<?php

declare(strict_types=1);

namespace Tests\Unit\Source\GitDiff\RenderStrategy;

use Butschster\ContextGenerator\Lib\Content\ContentBuilder;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Config\RenderConfig;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\Enum\RenderStrategyEnum;
use Butschster\ContextGenerator\Source\GitDiff\RenderStrategy\RawRenderStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(RawRenderStrategy::class)]
final class RawRenderStrategyTest extends TestCase
{
    private RawRenderStrategy $strategy;

    #[Test]
    public function it_should_render_file_diffs_in_raw_format(): void
    {
        $diffs = [
            'src/example.php' => [
                'diff' => "diff --git a/src/example.php b/src/example.php\nindex 1234567..890abcd 100644\n--- a/src/example.php\n+++ b/src/example.php\n@@ -1,5 +1,6 @@\n <?php\n \n-function oldFunction() {\n-    return false;\n+function newFunction() {\n+    // This is a new comment\n+    return true;\n }\n",
                'file' => 'src/example.php',
                'stats' => '+2 -2',
            ],
        ];

        $result = $this->strategy->render($diffs, new RenderConfig(strategy: RenderStrategyEnum::Raw));
        $content = $this->getContentFromBuilder($result);

        $this->assertInstanceOf(ContentBuilder::class, $result);
        $this->assertStringContainsString("## Diff for src/example.php", $content);
        $this->assertStringContainsString("```diff", $content);
        // The raw content should remain unchanged
        $this->assertStringContainsString("diff --git a/src/example.php b/src/example.php", $content);
        $this->assertStringContainsString("-function oldFunction() {", $content);
        $this->assertStringContainsString("-    return false;", $content);
        $this->assertStringContainsString("+function newFunction() {", $content);
        $this->assertStringContainsString("+    // This is a new comment", $content);
        $this->assertStringContainsString("+    return true;", $content);
        $this->assertStringContainsString("```", $content);
    }

    #[Test]
    public function it_should_properly_handle_multiple_file_diffs(): void
    {
        $diffs = [
            'src/file1.php' => [
                'diff' => "diff --git a/src/file1.php b/src/file1.php\nindex 1111..2222 100644\n--- a/src/file1.php\n+++ b/src/file1.php\n@@ -1,2 +1,3 @@\n <?php\n+// New line\n",
                'file' => 'src/file1.php',
                'stats' => '+1 -0',
            ],
            'src/file2.php' => [
                'diff' => "diff --git a/src/file2.php b/src/file2.php\nindex 3333..4444 100644\n--- a/src/file2.php\n+++ b/src/file2.php\n@@ -1,3 +1,2 @@\n <?php\n-// Removed line\n",
                'file' => 'src/file2.php',
                'stats' => '+0 -1',
            ],
        ];

        $result = $this->strategy->render($diffs, new RenderConfig(strategy: RenderStrategyEnum::Raw));
        $content = $this->getContentFromBuilder($result);

        $this->assertInstanceOf(ContentBuilder::class, $result);
        $this->assertStringContainsString("## Diff for src/file1.php", $content);
        $this->assertStringContainsString("## Diff for src/file2.php", $content);
        $this->assertStringContainsString("+// New line", $content);
        $this->assertStringContainsString("-// Removed line", $content);
    }

    #[Test]
    public function it_should_handle_empty_diff_array(): void
    {
        $result = $this->strategy->render([], new RenderConfig(strategy: RenderStrategyEnum::Raw));

        $this->assertInstanceOf(ContentBuilder::class, $result);
        // Empty diff array should result in empty content
        $this->assertEquals("", $this->getContentFromBuilder($result));
    }

    protected function setUp(): void
    {
        $this->strategy = new RawRenderStrategy();
    }

    /**
     * Helper method to extract content from ContentBuilder
     *
     */
    private function getContentFromBuilder(ContentBuilder $builder): string
    {
        // Since we don't have access to the internal state of ContentBuilder,
        // we're assuming it has a __toString() method or similar to get content
        // If that's not available, this method would need to be adjusted based on the actual API
        return (string) $builder;
    }
}
