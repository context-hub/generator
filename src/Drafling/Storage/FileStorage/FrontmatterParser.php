<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Storage\FileStorage;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Handles parsing and manipulation of YAML frontmatter in Markdown files
 */
final readonly class FrontmatterParser
{
    private const string FRONTMATTER_DELIMITER = '---';

    /**
     * Parse frontmatter and content from markdown file content
     *
     * @return array{frontmatter: array, content: string}
     */
    public function parse(string $content): array
    {
        $content = \trim($content);

        // Check if file starts with frontmatter delimiter
        if (!\str_starts_with($content, self::FRONTMATTER_DELIMITER)) {
            return [
                'frontmatter' => [],
                'content' => $content,
            ];
        }

        // Find the closing delimiter
        $lines = \explode("\n", $content);
        $frontmatterLines = [];
        $contentLines = [];
        $inFrontmatter = false;
        $frontmatterClosed = false;

        foreach ($lines as $index => $line) {
            if ($index === 0 && $line === self::FRONTMATTER_DELIMITER) {
                $inFrontmatter = true;
                continue;
            }

            if ($inFrontmatter && $line === self::FRONTMATTER_DELIMITER) {
                $inFrontmatter = false;
                $frontmatterClosed = true;
                continue;
            }

            if ($inFrontmatter) {
                $frontmatterLines[] = $line;
            } elseif ($frontmatterClosed) {
                $contentLines[] = $line;
            }
        }

        // Parse YAML frontmatter
        $frontmatter = [];
        if (!empty($frontmatterLines)) {
            $yamlContent = \implode("\n", $frontmatterLines);
            try {
                $frontmatter = Yaml::parse($yamlContent) ?? [];
            } catch (ParseException $e) {
                throw new \RuntimeException("Failed to parse YAML frontmatter: {$e->getMessage()}", 0, $e);
            }
        }

        $content = \implode("\n", $contentLines);

        return [
            'frontmatter' => $frontmatter,
            'content' => \trim($content),
        ];
    }

    /**
     * Combine frontmatter and content into markdown file format
     */
    public function combine(array $frontmatter, string $content): string
    {
        $output = '';

        if (!empty($frontmatter)) {
            $output .= self::FRONTMATTER_DELIMITER . "\n";
            $output .= Yaml::dump($frontmatter, 2, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            $output .= self::FRONTMATTER_DELIMITER . "\n";
        }

        $output .= $content;

        return $output;
    }

    /**
     * Extract only the frontmatter from file content
     */
    public function extractFrontmatter(string $content): array
    {
        return $this->parse($content)['frontmatter'];
    }
}
