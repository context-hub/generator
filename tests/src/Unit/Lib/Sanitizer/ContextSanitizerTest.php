<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\Sanitizer;

use Butschster\ContextGenerator\Modifier\Sanitizer\Rule\ContextSanitizer;
use Butschster\ContextGenerator\Modifier\Sanitizer\Rule\RuleInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContextSanitizerTest extends TestCase
{
    #[Test]
    public function it_should_initialize_with_empty_rules(): void
    {
        $sanitizer = new ContextSanitizer();
        $this->assertEmpty($sanitizer->getRules());
    }

    #[Test]
    public function it_should_initialize_with_provided_rules(): void
    {
        $rule1 = $this->createMock(RuleInterface::class);
        $rule1->method('getName')->willReturn('rule1');

        $rule2 = $this->createMock(RuleInterface::class);
        $rule2->method('getName')->willReturn('rule2');

        $sanitizer = new ContextSanitizer(rules: [
            'rule1' => $rule1,
            'rule2' => $rule2,
        ]);

        $this->assertCount(2, $sanitizer->getRules());
        $this->assertSame($rule1, $sanitizer->getRules()['rule1']);
        $this->assertSame($rule2, $sanitizer->getRules()['rule2']);
    }

    #[Test]
    public function it_should_add_rule_correctly(): void
    {
        $sanitizer = new ContextSanitizer();

        $rule = $this->createMock(RuleInterface::class);
        $rule->method('getName')->willReturn('test-rule');

        $result = $sanitizer->addRule(rule: $rule);

        $this->assertSame($sanitizer, $result);
        $this->assertCount(1, $sanitizer->getRules());
        $this->assertSame($rule, $sanitizer->getRules()['test-rule']);
    }

    #[Test]
    public function it_should_add_multiple_rules_correctly(): void
    {
        $sanitizer = new ContextSanitizer();

        $rule1 = $this->createMock(RuleInterface::class);
        $rule1->method('getName')->willReturn('rule1');

        $rule2 = $this->createMock(RuleInterface::class);
        $rule2->method('getName')->willReturn('rule2');

        $sanitizer->addRule(rule: $rule1);
        $sanitizer->addRule(rule: $rule2);

        $this->assertCount(2, $sanitizer->getRules());
        $this->assertSame($rule1, $sanitizer->getRules()['rule1']);
        $this->assertSame($rule2, $sanitizer->getRules()['rule2']);
    }

    #[Test]
    public function it_should_overwrite_existing_rule_with_same_name(): void
    {
        $sanitizer = new ContextSanitizer();

        $rule1 = $this->createMock(RuleInterface::class);
        $rule1->method('getName')->willReturn('same-name');

        $rule2 = $this->createMock(RuleInterface::class);
        $rule2->method('getName')->willReturn('same-name');

        $sanitizer->addRule(rule: $rule1);
        $sanitizer->addRule(rule: $rule2);

        $this->assertCount(1, $sanitizer->getRules());
        $this->assertSame($rule2, $sanitizer->getRules()['same-name']);
    }

    #[Test]
    public function it_should_return_all_rules(): void
    {
        $rule1 = $this->createMock(RuleInterface::class);
        $rule1->method('getName')->willReturn('rule1');

        $rule2 = $this->createMock(RuleInterface::class);
        $rule2->method('getName')->willReturn('rule2');

        $sanitizer = new ContextSanitizer(rules: [
            'rule1' => $rule1,
            'rule2' => $rule2,
        ]);

        $rules = $sanitizer->getRules();

        $this->assertCount(2, $rules);
        $this->assertSame($rule1, $rules['rule1']);
        $this->assertSame($rule2, $rules['rule2']);
    }

    #[Test]
    public function it_should_not_modify_content_with_no_rules(): void
    {
        $sanitizer = new ContextSanitizer();
        $content = "This content should remain unchanged";

        $this->assertEquals($content, $sanitizer->sanitize(content: $content));
    }

    #[Test]
    public function it_should_apply_single_rule_correctly(): void
    {
        $rule = $this->createMock(RuleInterface::class);
        $rule->method('getName')->willReturn('test-rule');
        $rule->method('apply')->willReturnCallback(static fn($content) => $content . ' (sanitized)');

        $sanitizer = new ContextSanitizer(rules: ['test-rule' => $rule]);
        $content = "Original content";

        $this->assertEquals("Original content (sanitized)", $sanitizer->sanitize(content: $content));
    }

    #[Test]
    public function it_should_apply_multiple_rules_correctly(): void
    {
        $rule1 = $this->createMock(RuleInterface::class);
        $rule1->method('getName')->willReturn('rule1');
        $rule1->method('apply')->willReturnCallback(static fn($content) => $content . ' (rule1)');

        $rule2 = $this->createMock(RuleInterface::class);
        $rule2->method('getName')->willReturn('rule2');
        $rule2->method('apply')->willReturnCallback(static fn($content) => $content . ' (rule2)');

        $sanitizer = new ContextSanitizer(rules: [
            'rule1' => $rule1,
            'rule2' => $rule2,
        ]);

        $content = "Original content";

        $this->assertEquals("Original content (rule1) (rule2)", $sanitizer->sanitize(content: $content));
    }

    #[Test]
    public function it_should_apply_rules_in_order_of_addition(): void
    {
        $rule1 = $this->createMock(RuleInterface::class);
        $rule1->method('getName')->willReturn('rule1');
        $rule1->method('apply')->willReturnCallback(static fn($content) => $content . ' (first)');

        $rule2 = $this->createMock(RuleInterface::class);
        $rule2->method('getName')->willReturn('rule2');
        $rule2->method('apply')->willReturnCallback(static fn($content) => $content . ' (second)');

        // Add rules in specific order
        $sanitizer = new ContextSanitizer();
        $sanitizer->addRule(rule: $rule1);
        $sanitizer->addRule(rule: $rule2);

        $content = "Original";

        $this->assertEquals("Original (first) (second)", $sanitizer->sanitize(content: $content));
    }
}
