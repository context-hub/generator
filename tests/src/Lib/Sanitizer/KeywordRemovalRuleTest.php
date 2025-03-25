<?php

declare(strict_types=1);

namespace Tests\Lib\Sanitizer;

use Butschster\ContextGenerator\Lib\Sanitizer\KeywordRemovalRule;
use Tests\TestCase;

class KeywordRemovalRuleTest extends TestCase
{
    public function testGetName(): void
    {
        $rule = new KeywordRemovalRule('test-rule', ['keyword']);
        $this->assertEquals('test-rule', $rule->getName());
    }

    public function testApplyWithEmptyKeywords(): void
    {
        $rule = new KeywordRemovalRule('empty-keywords', []);
        $content = "This content should remain unchanged";

        $this->assertEquals($content, $rule->apply($content));
    }

    public function testApplyWithSingleKeyword(): void
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

    public function testApplyWithMultipleKeywords(): void
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

    public function testApplyWithCustomReplacement(): void
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

    public function testApplyWithoutRemovingLines(): void
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

    public function testApplyWithCaseSensitiveMatching(): void
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

    public function testApplyWithCaseInsensitiveMatching(): void
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

    public function testApplyWithMultipleKeywordsWithoutRemovingLines(): void
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
