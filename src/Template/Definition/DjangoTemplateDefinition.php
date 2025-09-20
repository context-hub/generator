<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Source\File\FileSource;

/**
 * Django project template definition
 */
final class DjangoTemplateDefinition extends AbstractTemplateDefinition
{
    public function getName(): string
    {
        return 'django';
    }

    public function getDescription(): string
    {
        return 'Django Python web framework project template';
    }

    public function getTags(): array
    {
        return ['python', 'django', 'web', 'framework'];
    }

    public function getPriority(): int
    {
        return 90;
    }

    protected function getSourceDirectories(): array
    {
        return ['app', 'apps', 'project', 'static', 'templates', 'media'];
    }

    protected function getFrameworkSpecificCriteria(): array
    {
        return [
            'files' => ['manage.py', 'requirements.txt'],
            'patterns' => ['Django', 'django'],
        ];
    }

    /**
     * Add Django-specific documents
     */
    #[\Override]
    protected function createAdditionalDocuments(array $projectMetadata): array
    {
        $documents = [];
        $existingDirs = $projectMetadata['existingDirectories'] ?? $projectMetadata['directories'] ?? [];

        // Add Models and Views document if apps or project directory exists
        $sourcePaths = [];
        if (\in_array('app', $existingDirs, true)) {
            $sourcePaths[] = 'app';
        }
        if (\in_array('apps', $existingDirs, true)) {
            $sourcePaths[] = 'apps';
        }
        if (\in_array('project', $existingDirs, true)) {
            $sourcePaths[] = 'project';
        }

        if (!empty($sourcePaths)) {
            $documents[] = new Document(
                description: 'Django Models and Views',
                outputPath: 'docs/django-models-views.md',
                tags: ['django', 'models', 'views'],
                fileSource: new FileSource(
                    sourcePaths: $sourcePaths,
                    description: 'Django Models and Views',
                    filePattern: ['models.py', 'views.py', '*.py'],
                    path: ['models', 'views'],
                ),
            );
        }

        // Add Settings and URLs document
        if (!empty($sourcePaths)) {
            $documents[] = new Document(
                description: 'Django Configuration',
                outputPath: 'docs/django-config.md',
                tags: ['django', 'configuration'],
                fileSource: new FileSource(
                    sourcePaths: $sourcePaths,
                    description: 'Django Configuration',
                    filePattern: ['settings.py', 'urls.py', 'wsgi.py', 'asgi.py'],
                ),
            );
        }

        return $documents;
    }
}
