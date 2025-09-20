<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;

/**
 * Represents a project template with its configuration and metadata
 */
final readonly class Template implements \JsonSerializable
{
    /**
     * @param string $name Unique identifier for the template
     * @param string $description Human-readable description
     * @param array<string> $tags Tags for categorization
     * @param int $priority Priority for template selection (higher = more preferred)
     * @param array<string, mixed> $detectionCriteria Criteria for automatic detection
     * @param ConfigRegistry $config The configuration to apply when using this template
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $tags = [],
        public int $priority = 0,
        public array $detectionCriteria = [],
        public ?ConfigRegistry $config = null,
    ) {}

    /**
     * Check if this template matches the given detection criteria
     */
    public function matches(array $projectMetadata): bool
    {
        if (empty($this->detectionCriteria)) {
            return false;
        }

        // Check if required files exist
        if (isset($this->detectionCriteria['files'])) {
            foreach ($this->detectionCriteria['files'] as $file) {
                if (!isset($projectMetadata['files']) || !\in_array($file, $projectMetadata['files'], true)) {
                    return false;
                }
            }
        }

        // Check if required directories exist
        if (isset($this->detectionCriteria['directories'])) {
            foreach ($this->detectionCriteria['directories'] as $dir) {
                if (!isset($projectMetadata['directories']) || !\in_array($dir, $projectMetadata['directories'], true)) {
                    return false;
                }
            }
        }

        // Check if required patterns exist in composer.json
        if (isset($this->detectionCriteria['patterns']) && isset($projectMetadata['composer'])) {
            foreach ($this->detectionCriteria['patterns'] as $pattern) {
                $found = false;
                $composer = $projectMetadata['composer'];

                // Check in require section
                if (isset($composer['require']) && \array_key_exists($pattern, $composer['require'])) {
                    $found = true;
                }

                // Check in require-dev section
                if (isset($composer['require-dev']) && \array_key_exists($pattern, $composer['require-dev'])) {
                    $found = true;
                }

                if (!$found) {
                    return false;
                }
            }
        }

        return true;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'tags' => $this->tags,
            'priority' => $this->priority,
            'detectionCriteria' => $this->detectionCriteria,
            'config' => $this->config,
        ];
    }
}
