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

    /**
     * Extract only the markdown content from file content
     */
    public function extractContent(string $content): string
    {
        return $this->parse($content)['content'];
    }

    /**
     * Update frontmatter while preserving content
     */
    public function updateFrontmatter(string $originalContent, array $newFrontmatter): string
    {
        $parsed = $this->parse($originalContent);
        return $this->combine($newFrontmatter, $parsed['content']);
    }

    /**
     * Update content while preserving frontmatter
     */
    public function updateContent(string $originalContent, string $newContent): string
    {
        $parsed = $this->parse($originalContent);
        return $this->combine($parsed['frontmatter'], $newContent);
    }

    /**
     * Merge frontmatter updates with existing frontmatter
     */
    public function mergeFrontmatter(string $originalContent, array $updates): string
    {
        $parsed = $this->parse($originalContent);
        $mergedFrontmatter = \array_merge($parsed['frontmatter'], $updates);
        return $this->combine($mergedFrontmatter, $parsed['content']);
    }

    /**
     * Validate YAML frontmatter structure
     */
    public function validateFrontmatter(array $frontmatter): array
    {
        $errors = [];

        // Validate required fields
        $requiredFields = ['entry_id', 'title', 'entry_type', 'category', 'status'];
        foreach ($requiredFields as $field) {
            if (!isset($frontmatter[$field]) || empty($frontmatter[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate date fields
        $dateFields = ['created_at', 'updated_at'];
        foreach ($dateFields as $field) {
            if (isset($frontmatter[$field]) && !\is_string($frontmatter[$field])) {
                $errors[] = "Field '{$field}' must be a valid ISO 8601 date string";
            }
        }

        // Validate array fields
        $arrayFields = ['tags'];
        foreach ($arrayFields as $field) {
            if (isset($frontmatter[$field]) && !\is_array($frontmatter[$field])) {
                $errors[] = "Field '{$field}' must be an array";
            }
        }

        return $errors;
    }

    /**
     * Normalize frontmatter data types
     */
    public function normalizeFrontmatter(array $frontmatter): array
    {
        // Ensure tags is an array
        if (isset($frontmatter['tags']) && !\is_array($frontmatter['tags'])) {
            $frontmatter['tags'] = [];
        }

        // Ensure dates are properly formatted
        $dateFields = ['created_at', 'updated_at'];
        foreach ($dateFields as $field) {
            if (isset($frontmatter[$field]) && \is_string($frontmatter[$field])) {
                try {
                    $date = new \DateTime($frontmatter[$field]);
                    $frontmatter[$field] = $date->format('c'); // ISO 8601 format
                } catch (\Exception) {
                    // Invalid date - leave as is, validation will catch it
                }
            }
        }

        return $frontmatter;
    }
}
