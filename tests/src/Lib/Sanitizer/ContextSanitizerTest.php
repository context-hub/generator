<?php

declare(strict_types=1);

namespace Tests\Lib\Sanitizer;

use Butschster\ContextGenerator\Lib\Sanitizer\ContextSanitizer;
use Butschster\ContextGenerator\Lib\Sanitizer\RuleInterface;
use Tests\TestCase;

class ContextSanitizerTest extends TestCase
{
    public function testConstructorWithEmptyRules(): void
    {
        $sanitizer = new ContextSanitizer();
        $this->assertEmpty($sanitizer->getRules());
    }

    public function testConstructorWithRules(): void
    {
        $rule1 = $this->createMock(RuleInterface::class);
        $rule1->method('getName')->willReturn('rule1');

        $rule2 = $this->createMock(RuleInterface::class);
        $rule2->method('getName')->willReturn('rule2');

        $sanitizer = new ContextSanitizer([
            'rule1' => $rule1,
            'rule2' => $rule2,
        ]);

        $this->assertCount(2, $sanitizer->getRules());
        $this->assertSame($rule1, $sanitizer->getRules()['rule1']);
        $this->assertSame($rule2, $sanitizer->getRules()['rule2']);
    }

    public function testAddRule(): void
    {
        $sanitizer = new ContextSanitizer();

        $rule = $this->createMock(RuleInterface::class);
        $rule->method('getName')->willReturn('test-rule');

        $result = $sanitizer->addRule($rule);

        $this->assertSame($sanitizer, $result);
        $this->assertCount(1, $sanitizer->getRules());
        $this->assertSame($rule, $sanitizer->getRules()['test-rule']);
    }

    public function testAddMultipleRules(): void
    {
        $sanitizer = new ContextSanitizer();

        $rule1 = $this->createMock(RuleInterface::class);
        $rule1->method('getName')->willReturn('rule1');

        $rule2 = $this->createMock(RuleInterface::class);
        $rule2->method('getName')->willReturn('rule2');

        $sanitizer->addRule($rule1);
        $sanitizer->addRule($rule2);

        $this->assertCount(2, $sanitizer->getRules());
        $this->assertSame($rule1, $sanitizer->getRules()['rule1']);
        $this->assertSame($rule2, $sanitizer->getRules()['rule2']);
    }

    public function testAddRuleOverwritesExistingRule(): void
    {
        $sanitizer = new ContextSanitizer();

        $rule1 = $this->createMock(RuleInterface::class);
        $rule1->method('getName')->willReturn('same-name');

        $rule2 = $this->createMock(RuleInterface::class);
        $rule2->method('getName')->willReturn('same-name');

        $sanitizer->addRule($rule1);
        $sanitizer->addRule($rule2);

        $this->assertCount(1, $sanitizer->getRules());
        $this->assertSame($rule2, $sanitizer->getRules()['same-name']);
    }

    public function testGetRules(): void
    {
        $rule1 = $this->createMock(RuleInterface::class);
        $rule1->method('getName')->willReturn('rule1');

        $rule2 = $this->createMock(RuleInterface::class);
        $rule2->method('getName')->willReturn('rule2');

        $sanitizer = new ContextSanitizer([
            'rule1' => $rule1,
            'rule2' => $rule2,
        ]);

        $rules = $sanitizer->getRules();

        $this->assertCount(2, $rules);
        $this->assertSame($rule1, $rules['rule1']);
        $this->assertSame($rule2, $rules['rule2']);
    }

    public function testSanitizeWithNoRules(): void
    {
        $sanitizer = new ContextSanitizer();
        $content = "This content should remain unchanged";

        $this->assertEquals($content, $sanitizer->sanitize($content));
    }

    public function testSanitizeWithSingleRule(): void
    {
        $rule = $this->createMock(RuleInterface::class);
        $rule->method('getName')->willReturn('test-rule');
        $rule->method('apply')->willReturnCallback(static fn($content) => $content . ' (sanitized)');

        $sanitizer = new ContextSanitizer(['test-rule' => $rule]);
        $content = "Original content";

        $this->assertEquals("Original content (sanitized)", $sanitizer->sanitize($content));
    }

    public function testSanitizeWithMultipleRules(): void
    {
        $rule1 = $this->createMock(RuleInterface::class);
        $rule1->method('getName')->willReturn('rule1');
        $rule1->method('apply')->willReturnCallback(static fn($content) => $content . ' (rule1)');

        $rule2 = $this->createMock(RuleInterface::class);
        $rule2->method('getName')->willReturn('rule2');
        $rule2->method('apply')->willReturnCallback(static fn($content) => $content . ' (rule2)');

        $sanitizer = new ContextSanitizer([
            'rule1' => $rule1,
            'rule2' => $rule2,
        ]);

        $content = "Original content";

        $this->assertEquals("Original content (rule1) (rule2)", $sanitizer->sanitize($content));
    }

    public function testSanitizeAppliesRulesInOrder(): void
    {
        $rule1 = $this->createMock(RuleInterface::class);
        $rule1->method('getName')->willReturn('rule1');
        $rule1->method('apply')->willReturnCallback(static fn($content) => $content . ' (first)');

        $rule2 = $this->createMock(RuleInterface::class);
        $rule2->method('getName')->willReturn('rule2');
        $rule2->method('apply')->willReturnCallback(static fn($content) => $content . ' (second)');

        // Add rules in specific order
        $sanitizer = new ContextSanitizer();
        $sanitizer->addRule($rule1);
        $sanitizer->addRule($rule2);

        $content = "Original";

        $this->assertEquals("Original (first) (second)", $sanitizer->sanitize($content));
    }
}
