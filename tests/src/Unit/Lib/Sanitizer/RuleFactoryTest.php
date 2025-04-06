<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\Sanitizer;

use Butschster\ContextGenerator\Modifier\Sanitizer\Rule\CommentInsertionRule;
use Butschster\ContextGenerator\Modifier\Sanitizer\Rule\KeywordRemovalRule;
use Butschster\ContextGenerator\Modifier\Sanitizer\Rule\RegexReplacementRule;
use Butschster\ContextGenerator\Modifier\Sanitizer\Rule\RuleFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RuleFactoryTest extends TestCase
{
    private RuleFactory $factory;

    #[Test]
    public function it_should_throw_exception_for_missing_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rule configuration must include a "type" field');

        $this->factory->createFromConfig([]);
    }

    #[Test]
    public function it_should_throw_exception_for_unsupported_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported rule type: unknown');

        $this->factory->createFromConfig(['type' => 'unknown']);
    }

    #[Test]
    public function it_should_throw_exception_for_keyword_rule_with_missing_keywords(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Keyword rule must include a "keywords" array');

        $this->factory->createFromConfig(['type' => 'keyword']);
    }

    #[Test]
    public function it_should_throw_exception_for_keyword_rule_with_invalid_keywords(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Keyword rule must include a "keywords" array');

        $this->factory->createFromConfig([
            'type' => 'keyword',
            'keywords' => 'not-an-array',
        ]);
    }

    #[Test]
    public function it_should_create_keyword_rule_with_minimal_config(): void
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

    #[Test]
    public function it_should_create_keyword_rule_with_full_config(): void
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

    #[Test]
    public function it_should_throw_exception_for_regex_rule_with_missing_patterns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Regex rule must include "patterns" or "usePatterns"');

        $this->factory->createFromConfig(['type' => 'regex']);
    }

    #[Test]
    public function it_should_throw_exception_for_regex_rule_with_invalid_patterns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Regex rule "patterns" object must be an array');

        $this->factory->createFromConfig([
            'type' => 'regex',
            'patterns' => 'not-an-array',
        ]);
    }

    #[Test]
    public function it_should_create_regex_rule_with_custom_patterns(): void
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

    #[Test]
    public function it_should_create_regex_rule_with_predefined_patterns(): void
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

    #[Test]
    public function it_should_create_regex_rule_with_mixed_patterns(): void
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

    #[Test]
    public function it_should_create_comment_rule_with_minimal_config(): void
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

    #[Test]
    public function it_should_create_comment_rule_with_full_config(): void
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
