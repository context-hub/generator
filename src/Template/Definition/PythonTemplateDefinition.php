<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Source\File\FileSource;

/**
 * Generic Python project template definition
 */
final class PythonTemplateDefinition extends AbstractTemplateDefinition
{
    public function getName(): string
    {
        return 'python';
    }

    public function getDescription(): string
    {
        return 'Generic Python project template';
    }

    public function getTags(): array
    {
        return ['python', 'generic'];
    }

    public function getPriority(): int
    {
        return 15; // Lower priority - let specific frameworks go first
    }

    protected function getSourceDirectories(): array
    {
        return ['src', 'lib', 'app', 'tests', 'test'];
    }

    protected function getFrameworkSpecificCriteria(): array
    {
        return [
            'files' => ['requirements.txt', 'pyproject.toml', 'setup.py'],
        ];
    }

    /**
     * Add Python-specific documents
     */
    #[\Override]
    protected function createAdditionalDocuments(array $projectMetadata): array
    {
        $documents = [];
        $existingDirs = $projectMetadata['existingDirectories'] ?? $projectMetadata['directories'] ?? [];

        // Add Python modules document if source directories exist
        $sourcePaths = \array_intersect($existingDirs, $this->getSourceDirectories());

        if (!empty($sourcePaths)) {
            $documents[] = new Document(
                description: 'Python Modules and Packages',
                outputPath: 'docs/python-modules.md',
                tags: ['python', 'modules'],
                fileSource: new FileSource(
                    sourcePaths: \array_values($sourcePaths),
                    description: 'Python Modules and Packages',
                    filePattern: ['*.py'],
                    notPath: ['__pycache__', '*.pyc', 'venv', 'env', '.venv'],
                ),
            );
        }

        // Add configuration files document
        $configFiles = [];
        $potentialConfigFiles = [
            'setup.py',
            'setup.cfg',
            'pyproject.toml',
            'requirements.txt',
            'Pipfile',
            'tox.ini',
            'pytest.ini',
            '.coveragerc',
        ];

        foreach ($potentialConfigFiles as $configFile) {
            if (\in_array($configFile, $projectMetadata['files'] ?? [], true)) {
                $configFiles[] = $configFile;
            }
        }

        if (!empty($configFiles)) {
            $documents[] = new Document(
                description: 'Python Configuration Files',
                outputPath: 'docs/python-config.md',
                tags: ['python', 'configuration'],
                fileSource: new FileSource(
                    sourcePaths: ['.'],
                    description: 'Python Configuration Files',
                    filePattern: $configFiles,
                ),
            );
        }

        return $documents;
    }
}
