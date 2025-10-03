<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\Sanitizer;

use Butschster\ContextGenerator\Modifier\Sanitizer\Rule\CommentInsertionRule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommentInsertionRuleTest extends TestCase
{
    #[Test]
    public function it_should_return_correct_name(): void
    {
        $rule = new CommentInsertionRule(name: 'test-rule');
        $this->assertEquals('test-rule', $rule->getName());
    }

    #[Test]
    public function it_should_apply_file_header_comment(): void
    {
        $rule = new CommentInsertionRule(
            name: 'header-comment',
            fileHeaderComment: 'This is a header comment',
        );

        $content = "<?php\n\necho 'Hello World';";
        $expected = "// This is a header comment\n\n<?php\n\necho 'Hello World';";

        $this->assertEquals($expected, $rule->apply(content: $content));
    }

    #[Test]
    public function it_should_apply_multiline_file_header_comment(): void
    {
        $rule = new CommentInsertionRule(
            name: 'multiline-header',
            fileHeaderComment: "This is a header comment\nWith multiple lines",
        );

        $content = "<?php\n\necho 'Hello World';";
        $expected = "/**\n * This is a header comment\n * With multiple lines\n */\n\n<?php\n\necho 'Hello World';";

        $this->assertEquals($expected, $rule->apply(content: $content));
    }

    #[Test]
    public function it_should_apply_class_comment(): void
    {
        $rule = new CommentInsertionRule(
            name: 'class-comment',
            classComment: 'This is a class comment',
        );

        $content = "<?php\n\nclass TestClass {\n}";
        $expected = "<?php\n\n\n// This is a class comment\nclass TestClass {\n}";

        $this->assertEquals($expected, $rule->apply(content: $content));
    }

    #[Test]
    public function it_should_apply_method_comment(): void
    {
        $rule = new CommentInsertionRule(
            name: 'method-comment',
            methodComment: 'This is a method comment',
        );

        $content = "<?php\n\nclass TestClass {\n    public function test() {\n    }\n}";
        $expected = "<?php\n\nclass TestClass {\n    \n\n    // This is a method comment\n\n    \n    public function test() {\n    }\n}";

        $this->assertEquals($expected, $rule->apply(content: $content));
    }

    #[Test]
    public function it_should_apply_random_comments(): void
    {
        $rule = new CommentInsertionRule(
            name: 'random-comments',
            frequency: 2,
            randomComments: ['Random comment 1', 'Random comment 2'],
        );

        $content = "Line 1\nLine 2\nLine 3\nLine 4";
        $result = $rule->apply(content: $content);

        // Since the random comment selection is non-deterministic, we can only check
        // that the number of lines has increased as expected
        $this->assertGreaterThan(\strlen(string: $content), \strlen(string: $result));
        $this->assertStringContainsString('// Random comment', $result);
    }

    #[Test]
    public function it_should_apply_all_comment_options(): void
    {
        $rule = new CommentInsertionRule(
            name: 'all-options',
            fileHeaderComment: 'File header',
            classComment: 'Class comment',
            methodComment: 'Method comment',
            frequency: 0,
            randomComments: [],
        );

        $content = "<?php\n\nclass TestClass {\n    public function test() {\n    }\n}";
        $result = $rule->apply(content: $content);

        $this->assertStringContainsString('// File header', $result);
        $this->assertStringContainsString('// Class comment', $result);
        $this->assertStringContainsString('// Method comment', $result);
    }
}
