<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Source\File\FileSource;

/**
 * Generic Go project template definition
 */
final class GoTemplateDefinition extends AbstractTemplateDefinition
{
    public function getName(): string
    {
        return 'go';
    }

    public function getDescription(): string
    {
        return 'Generic Go project template';
    }

    public function getTags(): array
    {
        return ['go', 'golang', 'generic'];
    }

    public function getPriority(): int
    {
        return 20; // Lower priority - let specific frameworks go first
    }

    protected function getSourceDirectories(): array
    {
        return ['cmd', 'pkg', 'internal', 'api'];
    }

    protected function getFrameworkSpecificCriteria(): array
    {
        return [
            'files' => ['go.mod'],
        ];
    }

    /**
     * Add Go-specific documents
     */
    #[\Override]
    protected function createAdditionalDocuments(array $projectMetadata): array
    {
        $documents = [];
        $existingDirs = $projectMetadata['existingDirectories'] ?? $projectMetadata['directories'] ?? [];

        // Add Go packages document if source directories exist
        $sourcePaths = \array_intersect($existingDirs, $this->getSourceDirectories());

        if (!empty($sourcePaths)) {
            $documents[] = new Document(
                description: 'Go Packages and Modules',
                outputPath: 'docs/go-packages.md',
                tags: ['go', 'packages'],
                fileSource: new FileSource(
                    sourcePaths: \array_values($sourcePaths),
                    description: 'Go Packages and Modules',
                    filePattern: ['*.go'],
                    notPath: ['vendor', 'bin'],
                ),
            );
        }

        // Add configuration files document
        $configFiles = [];
        $potentialConfigFiles = [
            'go.mod',
            'go.sum',
            'go.work',
            'Makefile',
            'Dockerfile',
            '.golangci.yml',
            '.golangci.yaml',
        ];

        foreach ($potentialConfigFiles as $configFile) {
            if (\in_array($configFile, $projectMetadata['files'] ?? [], true)) {
                $configFiles[] = $configFile;
            }
        }

        if (!empty($configFiles)) {
            $documents[] = new Document(
                description: 'Go Configuration Files',
                outputPath: 'docs/go-config.md',
                tags: ['go', 'configuration'],
                fileSource: new FileSource(
                    sourcePaths: ['.'],
                    description: 'Go Configuration Files',
                    filePattern: $configFiles,
                ),
            );
        }

        return $documents;
    }
}
