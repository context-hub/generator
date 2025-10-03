<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\Sanitizer\Rule;

/**
 * Factory for creating rule instances from configuration arrays
 */
final class RuleFactory
{
    /**
     * Create a rule instance from configuration
     */
    public function createFromConfig(array $config): RuleInterface
    {
        if (!isset($config['type'])) {
            throw new \InvalidArgumentException(message: 'Rule configuration must include a "type" field');
        }

        return match ($config['type']) {
            'keyword' => $this->createKeywordRemovalRule(config: $config),
            'regex' => $this->createRegexReplacementRule(config: $config),
            'comment' => $this->createCommentInsertionRule(config: $config),
            default => throw new \InvalidArgumentException(
                message: \sprintf('Unsupported rule type: %s', $config['type']),
            ),
        };
    }

    /**
     * Create keyword removal rule from configuration
     */
    private function createKeywordRemovalRule(array $config): KeywordRemovalRule
    {
        if (!isset($config['keywords']) || !\is_array(value: $config['keywords'])) {
            throw new \InvalidArgumentException(message: 'Keyword rule must include a "keywords" array');
        }

        return new KeywordRemovalRule(
            name: $config['name'] ?? 'keyword-removal-' . \uniqid(),
            keywords: $config['keywords'],
            replacement: $config['replacement'] ?? '[REMOVED]',
            caseSensitive: $config['caseSensitive'] ?? false,
            removeLines: $config['removeLines'] ?? true,
        );
    }

    /**
     * Create regex replacement rule from configuration
     */
    private function createRegexReplacementRule(array $config): RegexReplacementRule
    {
        $patterns = [];
        if (isset($config['patterns'])) {
            if (!\is_array(value: $config['patterns'])) {
                throw new \InvalidArgumentException(message: 'Regex rule "patterns" object must be an array');
            }

            $patterns = $config['patterns'];
        }

        // Handle predefined pattern aliases if specified
        if (isset($config['usePatterns']) && \is_array(value: $config['usePatterns'])) {
            $patterns = \array_merge($patterns, $this->getPatternsByAliases(aliases: $config['usePatterns']));
        }

        if (empty($patterns)) {
            throw new \InvalidArgumentException(message: 'Regex rule must include "patterns" or "usePatterns"');
        }

        return new RegexReplacementRule(
            name: $config['name'] ?? 'regex-replacement-' . \uniqid(),
            patterns: $patterns,
        );
    }

    /**
     * Create comment insertion rule from configuration
     */
    private function createCommentInsertionRule(array $config): CommentInsertionRule
    {
        return new CommentInsertionRule(
            name: $config['name'] ?? 'comment-insertion-' . \uniqid(),
            fileHeaderComment: $config['fileHeaderComment'] ?? '',
            classComment: $config['classComment'] ?? '',
            methodComment: $config['methodComment'] ?? '',
            frequency: $config['frequency'] ?? 0,
            randomComments: $config['randomComments'] ?? [],
        );
    }

    /**
     * Get predefined regex patterns by their aliases
     *
     * @param array<string> $aliases Pattern aliases
     * @return array<string, string> Regex patterns mapped to replacements
     */
    private function getPatternsByAliases(array $aliases): array
    {
        $predefinedPatterns = [
            'credit-card' => [
                '/\b(?:\d{4}[-\s]?){3}\d{4}\b|\b\d{16}\b/' => '[CREDIT_CARD_REMOVED]',
            ],
            'email' => [
                '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/' => '[EMAIL_REMOVED]',
            ],
            'api-key' => [
                '/\b[A-Za-z0-9_-]{32,}\b|\b[A-Za-z0-9]{8}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{12}\b/' => '[API_KEY_REMOVED]',
            ],
            'ip-address' => [
                '/\b(?:\d{1,3}\.){3}\d{1,3}\b/' => '[IP_ADDRESS_REMOVED]',
            ],
            'jwt' => [
                '/\beyJ[a-zA-Z0-9_-]{10,}\.[a-zA-Z0-9_-]{10,}\.[a-zA-Z0-9_-]{10,}\b/' => '[JWT_TOKEN_REMOVED]',
            ],
            'phone-number' => [
                '/\b(?:\+\d{1,3}[-\s]?)?\(?\d{3}\)?[-\s]?\d{3}[-\s]?\d{4}\b/' => '[PHONE_NUMBER_REMOVED]',
            ],
            'password-field' => [
                '/\b(?:password|passwd|pwd|secret)\s*=\s*["\'].*?["\']/i' => '[PASSWORD_REMOVED]',
            ],
            'url' => [
                '/https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*)/' => '[URL_REMOVED]',
            ],
            'social-security' => [
                '/\b\d{3}-\d{2}-\d{4}\b/' => '[SSN_REMOVED]',
            ],
            'aws-key' => [
                '/\bAKIA[0-9A-Z]{16}\b/' => '[AWS_KEY_REMOVED]',
            ],
            'private-key' => [
                '/-----BEGIN (?:RSA|DSA|EC|OPENSSH|PRIVATE) KEY-----/' => '[PRIVATE_KEY_REMOVED]',
            ],
            'database-conn' => [
                '/(?:jdbc:(?:mysql|postgresql|oracle):\/\/[^\s"\']+|mongodb(?:\+srv)?:\/\/[^\s"\']+)/' => '[DATABASE_CONNECTION_REMOVED]',
            ],
        ];

        $patterns = [];
        foreach ($aliases as $alias) {
            if (isset($predefinedPatterns[$alias])) {
                $patterns = \array_merge($patterns, $predefinedPatterns[$alias]);
            }
        }

        return $patterns;
    }
}
