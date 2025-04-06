<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\Sanitizer;

use Butschster\ContextGenerator\Modifier\Sanitizer\Rule\KeywordRemovalRule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KeywordRemovalRuleTest extends TestCase
{
    #[Test]
    public function it_should_return_correct_name(): void
    {
        $rule = new KeywordRemovalRule('test-rule', ['keyword']);
        $this->assertEquals('test-rule', $rule->getName());
    }

    #[Test]
    public function it_should_not_modify_content_with_empty_keywords(): void
    {
        $rule = new KeywordRemovalRule('empty-keywords', []);
        $content = "This content should remain unchanged";

        $this->assertEquals($content, $rule->apply($content));
    }

    #[Test]
    public function it_should_remove_lines_with_single_keyword(): void
    {
        $rule = new KeywordRemovalRule(
            name: 'single-keyword',
            keywords: ['secret'],
            removeLines: true,
        );

        $content = "Line with no match\nLine with secret keyword\nAnother normal line";
        $expected = "Line with no match\n[REMOVED]\nAnother normal line";

        $this->assertEquals($expected, $rule->apply($content));
    }

    #[Test]
    public function it_should_remove_lines_with_multiple_keywords(): void
    {
        $rule = new KeywordRemovalRule(
            name: 'multiple-keywords',
            keywords: ['secret', 'password', 'key'],
            removeLines: true,
        );

        $content = "Line with secret\nLine with password\nLine with key\nNormal line";
        $expected = "[REMOVED]\n[REMOVED]\n[REMOVED]\nNormal line";

        $this->assertEquals($expected, $rule->apply($content));
    }

    #[Test]
    public function it_should_use_custom_replacement_text(): void
    {
        $rule = new KeywordRemovalRule(
            name: 'custom-replacement',
            keywords: ['secret'],
            replacement: '[REDACTED]',
            removeLines: true,
        );

        $content = "Line with no match\nLine with secret keyword\nAnother normal line";
        $expected = "Line with no match\n[REDACTED]\nAnother normal line";

        $this->assertEquals($expected, $rule->apply($content));
    }

    #[Test]
    public function it_should_replace_keywords_without_removing_lines(): void
    {
        $rule = new KeywordRemovalRule(
            name: 'keep-lines',
            keywords: ['secret'],
            replacement: '[REDACTED]',
            removeLines: false,
        );

        $content = "Line with no match\nLine with secret keyword\nAnother normal line";
        $expected = "Line with no match\nLine with [REDACTED] keyword\nAnother normal line";

        $this->assertEquals($expected, $rule->apply($content));
    }

    #[Test]
    public function it_should_respect_case_sensitive_matching(): void
    {
        $rule = new KeywordRemovalRule(
            name: 'case-sensitive',
            keywords: ['Secret'],
            caseSensitive: true,
            removeLines: true,
        );

        $content = "Line with Secret\nLine with secret\nNormal line";
        $expected = "[REMOVED]\nLine with secret\nNormal line";

        $this->assertEquals($expected, $rule->apply($content));
    }

    #[Test]
    public function it_should_respect_case_insensitive_matching(): void
    {
        $rule = new KeywordRemovalRule(
            name: 'case-insensitive',
            keywords: ['Secret'],
            caseSensitive: false,
            removeLines: true,
        );

        $content = "Line with Secret\nLine with secret\nNormal line";
        $expected = "[REMOVED]\n[REMOVED]\nNormal line";

        $this->assertEquals($expected, $rule->apply($content));
    }

    #[Test]
    public function it_should_replace_multiple_keywords_in_same_line(): void
    {
        $rule = new KeywordRemovalRule(
            name: 'multiple-keywords-keep-lines',
            keywords: ['secret', 'password'],
            replacement: '[REDACTED]',
            removeLines: false,
        );

        $content = "Line with secret and password\nNormal line";
        $expected = "Line with [REDACTED] and [REDACTED]\nNormal line";

        $this->assertEquals($expected, $rule->apply($content));
    }
}
