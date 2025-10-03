<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\Sanitizer\Rule;

/**
 * Rule for inserting comments into the code
 *
 * This rule inserts comments at various positions in the code to mark it as sanitized,
 * indicate restrictions, or provide warnings about its use.
 */
final readonly class CommentInsertionRule implements RuleInterface
{
    /**
     * @param string $name Unique rule name
     * @param string $fileHeaderComment Comment to insert at the top of file
     * @param string $classComment Comment to insert before class definitions
     * @param string $methodComment Comment to insert before method definitions
     * @param int $frequency How often to insert random comments (1 = every line, 5 = every 5th line)
     * @param array<string> $randomComments Array of random comments to insert
     */
    public function __construct(
        private string $name,
        private string $fileHeaderComment = '',
        private string $classComment = '',
        private string $methodComment = '',
        private int $frequency = 0,
        private array $randomComments = [],
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function apply(string $content): string
    {
        // Add file header comment
        if (!empty($this->fileHeaderComment)) {
            $comment = $this->formatPhpComment(comment: $this->fileHeaderComment);
            $content = $comment . PHP_EOL . PHP_EOL . $content;
        }

        // Add class comments
        if (!empty($this->classComment)) {
            $comment = $this->formatPhpComment(comment: $this->classComment);
            $content = (string) \preg_replace(
                pattern: '/(class|interface|trait|enum)\s+([a-zA-Z0-9_]+)/',
                replacement: PHP_EOL . $comment . PHP_EOL . '$1 $2',
                subject: $content,
            );
        }

        // Add method comments
        if (!empty($this->methodComment)) {
            $comment = $this->formatPhpComment(comment: $this->methodComment);
            $content = (string) \preg_replace(
                pattern: '/(\s+)public|private|protected\s+function/',
                replacement: '$1' . PHP_EOL . '$1' . $comment . PHP_EOL . '$1$0',
                subject: (string) $content,
            );
        }

        // Add random comments
        if ($this->frequency > 0 && !empty($this->randomComments)) {
            $lines = \explode(separator: PHP_EOL, string: (string) $content);
            $result = [];

            foreach ($lines as $i => $line) {
                $result[] = $line;

                if ($i % $this->frequency === 0 && \trim(string: $line) !== '') {
                    $randomComment = $this->randomComments[\array_rand($this->randomComments)];
                    $indentation = '';

                    // Try to detect indentation level
                    if (\preg_match(pattern: '/^(\s+)/', subject: $line, matches: $matches)) {
                        $indentation = $matches[1];
                    }

                    $result[] = $indentation . '// ' . $randomComment;
                }
            }

            $content = \implode(separator: PHP_EOL, array: $result);
        }

        return $content;
    }

    /**
     * Format a comment string as a PHP doc-block or inline comment
     */
    private function formatPhpComment(string $comment): string
    {
        if (\str_contains(haystack: $comment, needle: PHP_EOL)) {
            // Multi-line comment
            $lines = \explode(separator: PHP_EOL, string: $comment);
            $result = "/**" . PHP_EOL;

            foreach ($lines as $line) {
                $result .= " * " . $line . PHP_EOL;
            }

            $result .= " */";
            return $result;
        }

        // Single line comment
        return "// " . $comment;
    }
}
