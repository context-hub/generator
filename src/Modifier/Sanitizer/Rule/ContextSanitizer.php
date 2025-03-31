<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\Sanitizer\Rule;

final class ContextSanitizer
{
    /**
     * @param array<string, RuleInterface> $rules Collection of sanitization rules
     */
    public function __construct(
        private array $rules = [],
    ) {}

    /**
     * Register a sanitization rule
     */
    public function addRule(RuleInterface $rule): self
    {
        $this->rules[$rule->getName()] = $rule;
        return $this;
    }

    /**
     * Get all registered rules
     *
     * @return array<string, RuleInterface>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Sanitize a context file and save the result
     */
    public function sanitize(string $content): string
    {
        foreach ($this->rules as $rule) {
            $content = $rule->apply($content);
        }

        return $content;
    }
}
