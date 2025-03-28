<?php

declare(strict_types=1);

namespace Tests\Lib\Sanitizer;

use Butschster\ContextGenerator\Lib\Sanitizer\CommentInsertionRule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommentInsertionRuleTest extends TestCase
{
    #[Test]
    public function it_should_return_correct_name(): void
    {
        $rule = new CommentInsertionRule('test-rule');
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

        $this->assertEquals($expected, $rule->apply($content));
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

        $this->assertEquals($expected, $rule->apply($content));
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

        $this->assertEquals($expected, $rule->apply($content));
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

        $this->assertEquals($expected, $rule->apply($content));
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
        $result = $rule->apply($content);

        // Since the random comment selection is non-deterministic, we can only check
        // that the number of lines has increased as expected
        $this->assertGreaterThan(\strlen($content), \strlen($result));
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
        $result = $rule->apply($content);

        $this->assertStringContainsString('// File header', $result);
        $this->assertStringContainsString('// Class comment', $result);
        $this->assertStringContainsString('// Method comment', $result);
    }

    #[Test]
    public function it_should_apply_comments_with_indentation(): void
    {
        $rule = new CommentInsertionRule(
            name: 'indented-comments',
            fileHeaderComment: 'Indented header',
            classComment: 'Indented class comment',
            methodComment: 'Indented method comment',
            frequency: 2,
            randomComments: ['Indented random comment'],
        );

        $content = "<?php\n\n    class IndentedClass {\n        public function indentedMethod() {\n            echo 'Indented';\n        }\n    }";
        $result = $rule->apply($content);

        $this->assertStringContainsString('// Indented header', $result);
        $this->assertStringContainsString('// Indented class comment', $result);
        $this->assertStringContainsString('// Indented method comment', $result);
        $this->assertStringContainsString('// Indented random comment', $result);
    }
}
