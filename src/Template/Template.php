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
    public function matches(array $projectMetadata): array
    {
        $matching = [
            'confidence' => 0.0,
        ];

        // Check files
        if (isset($this->detectionCriteria['files'])) {
            foreach ($this->detectionCriteria['files'] as $file) {
                if ($this->hasFile($projectMetadata, $file)) {
                    $matching['files'][] = $file;
                }
            }
        }

        // Check directories
        if (isset($this->detectionCriteria['directories'])) {
            foreach ($this->detectionCriteria['directories'] as $directory) {
                if ($this->hasDirectory($projectMetadata, $directory)) {
                    $matching['directories'][] = $directory;
                }
            }
        }

        // Check patterns
        if (isset($this->detectionCriteria['patterns'])) {
            foreach ($this->detectionCriteria['patterns'] as $pattern) {
                if ($this->hasPackagePattern($projectMetadata, $pattern)) {
                    $matching['patterns'][] = $pattern;
                }
            }
        }


        if (empty($this->detectionCriteria)) {
            return $matching;
        }

        $totalCriteria = 0;
        $matchedCriteria = 0;
        $confidence = 0.0;

        // Check file criteria
        if (isset($this->detectionCriteria['files'])) {
            $files = $this->detectionCriteria['files'];
            $totalCriteria += \count($files);

            foreach ($files as $file) {
                if ($this->hasFile($projectMetadata, $file)) {
                    $matchedCriteria++;
                    $confidence += 0.3; // Files are important indicators
                }
            }
        }

        // Check directory criteria
        if (isset($this->detectionCriteria['directories'])) {
            $directories = $this->detectionCriteria['directories'];
            $totalCriteria += \count($directories);

            foreach ($directories as $directory) {
                if ($this->hasDirectory($projectMetadata, $directory)) {
                    $matchedCriteria++;
                    $confidence += 0.2; // Directories are moderate indicators
                }
            }
        }

        // Check package pattern criteria (composer.json or package.json)
        if (isset($this->detectionCriteria['patterns'])) {
            $patterns = $this->detectionCriteria['patterns'];
            $totalCriteria += \count($patterns);

            foreach ($patterns as $pattern) {
                if ($this->hasPackagePattern($projectMetadata, $pattern)) {
                    $matchedCriteria++;
                    $confidence += 0.4; // Package patterns are strong indicators
                }
            }
        }

        // Normalize confidence based on how many criteria were met
        if ($totalCriteria > 0) {
            $matchRatio = $matchedCriteria / $totalCriteria;
            $confidence = $confidence * (float) $matchRatio;
        }

        $matching['confidence'] = \min($confidence, 1.0);

        return $matching;
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

    /**
     * Check if project has a specific file
     */
    private function hasFile(array $projectMetadata, string $file): bool
    {
        return isset($projectMetadata['files']) && \in_array($file, $projectMetadata['files'], true);
    }

    /**
     * Check if project has a specific directory
     */
    private function hasDirectory(array $projectMetadata, string $directory): bool
    {
        return isset($projectMetadata['directories']) && \in_array($directory, $projectMetadata['directories'], true);
    }

    /**
     * Check if project has a specific package pattern in composer.json or package.json
     */
    private function hasPackagePattern(array $projectMetadata, string $pattern): bool
    {
        // Check composer.json packages
        if (isset($projectMetadata['composer']['packages'])) {
            if (\array_key_exists($pattern, $projectMetadata['composer']['packages'])) {
                return true;
            }
        }

        // Check package.json dependencies
        if (isset($projectMetadata['packageJson']['dependencies'])) {
            if (\array_key_exists($pattern, $projectMetadata['packageJson']['dependencies'])) {
                return true;
            }
        }

        return false;
    }
}
