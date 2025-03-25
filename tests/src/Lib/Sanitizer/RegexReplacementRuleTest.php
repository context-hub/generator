<?php

declare(strict_types=1);

namespace Tests\Lib\Sanitizer;

use Butschster\ContextGenerator\Lib\Sanitizer\RegexReplacementRule;
use Tests\TestCase;

class RegexReplacementRuleTest extends TestCase
{
    public function testGetName(): void
    {
        $rule = new RegexReplacementRule('test-rule', []);
        $this->assertEquals('test-rule', $rule->getName());
    }

    public function testApplyWithEmptyPatterns(): void
    {
        $rule = new RegexReplacementRule('empty-patterns', []);
        $content = "This content should remain unchanged";

        $this->assertEquals($content, $rule->apply($content));
    }

    public function testApplyWithSinglePattern(): void
    {
        $rule = new RegexReplacementRule(
            name: 'single-pattern',
            patterns: ['/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/' => '[EMAIL_REMOVED]'],
        );

        $content = "Contact us at test@example.com for more information";
        $expected = "Contact us at [EMAIL_REMOVED] for more information";

        $this->assertEquals($expected, $rule->apply($content));
    }

    public function testApplyWithMultiplePatterns(): void
    {
        $rule = new RegexReplacementRule(
            name: 'multiple-patterns',
            patterns: [
                '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/' => '[EMAIL_REMOVED]',
                '/\b(?:\d{1,3}\.){3}\d{1,3}\b/' => '[IP_ADDRESS_REMOVED]',
            ],
        );

        $content = "Contact us at test@example.com or visit our server at 192.168.1.1";
        $expected = "Contact us at [EMAIL_REMOVED] or visit our server at [IP_ADDRESS_REMOVED]";

        $this->assertEquals($expected, $rule->apply($content));
    }

    public function testApplyWithCaptureGroups(): void
    {
        $rule = new RegexReplacementRule(
            name: 'capture-groups',
            patterns: [
                '/password\s*=\s*["\'](.+?)["\']/' => 'password="[REDACTED]"',
            ],
        );

        $content = "config = { password = \"supersecret123\" }";
        $expected = "config = { password=\"[REDACTED]\" }";

        $this->assertEquals($expected, $rule->apply($content));
    }

    public function testApplyWithMultilineContent(): void
    {
        $rule = new RegexReplacementRule(
            name: 'multiline',
            patterns: [
                '/\/\*.*?\*\//s' => '[COMMENT_REMOVED]',
            ],
        );

        $content = "function test() {\n  /* This is a\n  multiline comment */\n  return true;\n}";
        $expected = "function test() {\n  [COMMENT_REMOVED]\n  return true;\n}";

        $this->assertEquals($expected, $rule->apply($content));
    }

    public function testApplyWithComplexPatterns(): void
    {
        $rule = new RegexReplacementRule(
            name: 'complex-patterns',
            patterns: [
                '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/' => '[CREDIT_CARD_REMOVED]',
                '/\b(?:\+\d{1,3}[-\s]?)?\(?\d{3}\)?[-\s]?\d{3}[-\s]?\d{4}\b/' => '[PHONE_NUMBER_REMOVED]',
            ],
        );

        $content = "Card: 1234 5678 9012 3456, Phone: (123) 456-7890";
        $expected = "Card: [CREDIT_CARD_REMOVED], Phone: ([PHONE_NUMBER_REMOVED]";

        $this->assertEquals($expected, $rule->apply($content));
    }

    public function testApplyWithNoMatches(): void
    {
        $rule = new RegexReplacementRule(
            name: 'no-matches',
            patterns: [
                '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/' => '[EMAIL_REMOVED]',
            ],
        );

        $content = "This content has no email addresses";

        $this->assertEquals($content, $rule->apply($content));
    }

    public function testApplyWithOverlappingPatterns(): void
    {
        $rule = new RegexReplacementRule(
            name: 'overlapping-patterns',
            patterns: [
                '/secret\d+key/' => '[SECRET_KEY_REMOVED]',
                '/secret\d+code/' => '[SECRET_CODE_REMOVED]',
            ],
        );

        $content = "The code is secret123key and secret456code";
        $expected = "The code is [SECRET_KEY_REMOVED] and [SECRET_CODE_REMOVED]";

        $this->assertEquals($expected, $rule->apply($content));
    }
}
