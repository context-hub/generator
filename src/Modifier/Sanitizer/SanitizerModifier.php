<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\Sanitizer;

use Butschster\ContextGenerator\Modifier\Sanitizer\Rule\ContextSanitizer;
use Butschster\ContextGenerator\Modifier\Sanitizer\Rule\RuleFactory;
use Butschster\ContextGenerator\Modifier\SourceModifierInterface;

/**
 * Modifier that applies sanitization rules to source content
 */
final readonly class SanitizerModifier implements SourceModifierInterface
{
    /**
     * @param array<array> $defaultRules Default sanitization rules configuration
     */
    public function __construct(
        private RuleFactory $ruleFactory = new RuleFactory(),
        private array $defaultRules = [],
    ) {}

    /**
     * Get the identifier for this modifier
     */
    public function getIdentifier(): string
    {
        return 'sanitizer';
    }

    /**
     * This modifier supports any content type
     */
    public function supports(string $contentType): bool
    {
        return true;
    }

    /**
     * Modify the source content by applying sanitization rules
     *
     * @param string $content The content to sanitize
     * @param array $context Additional context, may contain 'rules' array
     * @return string The sanitized content
     */
    public function modify(string $content, array $context = []): string
    {
        // Create a fresh sanitizer instance for each content modification
        $sanitizer = new ContextSanitizer();

        // Determine which rules to apply
        $rules = $context['rules'] ?? $this->defaultRules;

        // Apply each rule to the sanitizer
        foreach ($rules as $ruleConfig) {
            $rule = $this->ruleFactory->createFromConfig($ruleConfig);
            $sanitizer->addRule($rule);
        }

        // Apply sanitization rules to the content
        return $sanitizer->sanitize($content);
    }
}
