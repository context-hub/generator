<?php

declare(strict_types=1);

namespace Tests\Lib\Sanitizer;

use Butschster\ContextGenerator\Lib\Sanitizer\CommentInsertionRule;
use Butschster\ContextGenerator\Lib\Sanitizer\KeywordRemovalRule;
use Butschster\ContextGenerator\Lib\Sanitizer\RegexReplacementRule;
use Butschster\ContextGenerator\Lib\Sanitizer\RuleFactory;
use Tests\TestCase;

class RuleFactoryTest extends TestCase
{
    private RuleFactory $factory;

    public function testCreateFromConfigWithMissingType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rule configuration must include a "type" field');

        $this->factory->createFromConfig([]);
    }

    public function testCreateFromConfigWithUnsupportedType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported rule type: unknown');

        $this->factory->createFromConfig(['type' => 'unknown']);
    }

    public function testCreateKeywordRemovalRuleWithMissingKeywords(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Keyword rule must include a "keywords" array');

        $this->factory->createFromConfig(['type' => 'keyword']);
    }

    public function testCreateKeywordRemovalRuleWithInvalidKeywords(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Keyword rule must include a "keywords" array');

        $this->factory->createFromConfig([
            'type' => 'keyword',
            'keywords' => 'not-an-array',
        ]);
    }

    public function testCreateKeywordRemovalRuleWithMinimalConfig(): void
    {
        $rule = $this->factory->createFromConfig([
            'type' => 'keyword',
            'keywords' => ['secret', 'password'],
        ]);

        $this->assertInstanceOf(KeywordRemovalRule::class, $rule);
        $this->assertStringStartsWith('keyword-removal-', $rule->getName());

        // Test the rule works as expected
        $content = "Line with secret\nNormal line";
        $result = $rule->apply($content);
        $this->assertEquals("[REMOVED]\nNormal line", $result);
    }

    public function testCreateKeywordRemovalRuleWithFullConfig(): void
    {
        $rule = $this->factory->createFromConfig([
            'type' => 'keyword',
            'name' => 'custom-keyword-rule',
            'keywords' => ['secret', 'password'],
            'replacement' => '[REDACTED]',
            'caseSensitive' => true,
            'removeLines' => false,
        ]);

        $this->assertInstanceOf(KeywordRemovalRule::class, $rule);
        $this->assertEquals('custom-keyword-rule', $rule->getName());

        // Test the rule works as expected
        $content = "Line with secret\nLine with SECRET\nNormal line";
        $result = $rule->apply($content);
        $this->assertEquals("Line with [REDACTED]\nLine with SECRET\nNormal line", $result);
    }

    public function testCreateRegexReplacementRuleWithMissingPatterns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Regex rule must include "patterns" or "usePatterns"');

        $this->factory->createFromConfig(['type' => 'regex']);
    }

    public function testCreateRegexReplacementRuleWithInvalidPatterns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Regex rule "patterns" object must be an array');

        $this->factory->createFromConfig([
            'type' => 'regex',
            'patterns' => 'not-an-array',
        ]);
    }

    public function testCreateRegexReplacementRuleWithPatterns(): void
    {
        $rule = $this->factory->createFromConfig([
            'type' => 'regex',
            'name' => 'custom-regex-rule',
            'patterns' => [
                '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/' => '[EMAIL_REMOVED]',
            ],
        ]);

        $this->assertInstanceOf(RegexReplacementRule::class, $rule);
        $this->assertEquals('custom-regex-rule', $rule->getName());

        // Test the rule works as expected
        $content = "Contact us at test@example.com";
        $result = $rule->apply($content);
        $this->assertEquals("Contact us at [EMAIL_REMOVED]", $result);
    }

    public function testCreateRegexReplacementRuleWithPredefinedPatterns(): void
    {
        $rule = $this->factory->createFromConfig([
            'type' => 'regex',
            'usePatterns' => ['email', 'ip-address'],
        ]);

        $this->assertInstanceOf(RegexReplacementRule::class, $rule);

        // Test the rule works as expected with email pattern
        $content = "Contact us at test@example.com or visit 192.168.1.1";
        $result = $rule->apply($content);
        $this->assertEquals("Contact us at [EMAIL_REMOVED] or visit [IP_ADDRESS_REMOVED]", $result);
    }

    public function testCreateRegexReplacementRuleWithMixedPatterns(): void
    {
        $rule = $this->factory->createFromConfig([
            'type' => 'regex',
            'patterns' => [
                '/custom-pattern/' => '[CUSTOM_REMOVED]',
            ],
            'usePatterns' => ['email'],
        ]);

        $this->assertInstanceOf(RegexReplacementRule::class, $rule);

        // Test the rule works with both custom and predefined patterns
        $content = "Contact us at test@example.com or use custom-pattern";
        $result = $rule->apply($content);
        $this->assertEquals("Contact us at [EMAIL_REMOVED] or use [CUSTOM_REMOVED]", $result);
    }

    public function testCreateCommentInsertionRuleWithMinimalConfig(): void
    {
        $rule = $this->factory->createFromConfig([
            'type' => 'comment',
        ]);

        $this->assertInstanceOf(CommentInsertionRule::class, $rule);
        $this->assertStringStartsWith('comment-insertion-', $rule->getName());

        // Test the rule doesn't modify content with default empty settings
        $content = "<?php\n\necho 'Hello';";
        $result = $rule->apply($content);
        $this->assertEquals($content, $result);
    }

    public function testCreateCommentInsertionRuleWithFullConfig(): void
    {
        $rule = $this->factory->createFromConfig([
            'type' => 'comment',
            'name' => 'custom-comment-rule',
            'fileHeaderComment' => 'File header',
            'classComment' => 'Class comment',
            'methodComment' => 'Method comment',
            'frequency' => 0,
            'randomComments' => ['Random 1', 'Random 2'],
        ]);

        $this->assertInstanceOf(CommentInsertionRule::class, $rule);
        $this->assertEquals('custom-comment-rule', $rule->getName());

        // Test the rule applies comments correctly
        $content = "<?php\n\nclass Test {\n    public function method() {}\n}";
        $result = $rule->apply($content);

        $this->assertStringContainsString('// File header', $result);
        $this->assertStringContainsString('// Class comment', $result);
        $this->assertStringContainsString('// Method comment', $result);
    }

    protected function setUp(): void
    {
        $this->factory = new RuleFactory();
    }
}
