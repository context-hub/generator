<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Source\File\FileSource;

/**
 * Flask project template definition
 */
final class FlaskTemplateDefinition extends AbstractTemplateDefinition
{
    public function getName(): string
    {
        return 'flask';
    }

    public function getDescription(): string
    {
        return 'Flask Python web framework project template';
    }

    public function getTags(): array
    {
        return ['python', 'flask', 'web', 'framework', 'api'];
    }

    public function getPriority(): int
    {
        return 85;
    }

    protected function getSourceDirectories(): array
    {
        return ['app', 'src', 'static', 'templates', 'tests'];
    }

    protected function getFrameworkSpecificCriteria(): array
    {
        return [
            'files' => ['app.py', 'requirements.txt'],
            'patterns' => ['Flask', 'flask'],
        ];
    }

    /**
     * Add Flask-specific documents
     */
    #[\Override]
    protected function createAdditionalDocuments(array $projectMetadata): array
    {
        $documents = [];
        $existingDirs = $projectMetadata['existingDirectories'] ?? $projectMetadata['directories'] ?? [];

        // Add Flask App and Routes document
        $sourcePaths = [];
        if (\in_array('app', $existingDirs, true)) {
            $sourcePaths[] = 'app';
        }
        if (\in_array('src', $existingDirs, true)) {
            $sourcePaths[] = 'src';
        }

        // If no app/src directory, check for app.py in root
        if (empty($sourcePaths)) {
            $sourcePaths = ['.'];
        }

        $documents[] = new Document(
            description: 'Flask Application and Routes',
            outputPath: 'docs/flask-app-routes.md',
            tags: ['flask', 'routes', 'application'],
            fileSource: new FileSource(
                sourcePaths: $sourcePaths,
                description: 'Flask Application and Routes',
                filePattern: ['*.py'],
                contains: ['@app.route', 'Flask', 'blueprint'],
            ),
        );

        return $documents;
    }
}
