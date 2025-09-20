<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Source\File\FileSource;

/**
 * FastAPI project template definition
 */
final class FastApiTemplateDefinition extends AbstractTemplateDefinition
{
    public function getName(): string
    {
        return 'fastapi';
    }

    public function getDescription(): string
    {
        return 'FastAPI Python web framework project template';
    }

    public function getTags(): array
    {
        return ['python', 'fastapi', 'api', 'web', 'framework', 'async'];
    }

    public function getPriority(): int
    {
        return 88;
    }

    protected function getSourceDirectories(): array
    {
        return ['app', 'src', 'api', 'routers', 'models', 'schemas', 'tests'];
    }

    protected function getFrameworkSpecificCriteria(): array
    {
        return [
            'files' => ['main.py', 'requirements.txt'],
            'patterns' => ['fastapi', 'FastAPI'],
        ];
    }

    /**
     * Add FastAPI-specific documents
     */
    #[\Override]
    protected function createAdditionalDocuments(array $projectMetadata): array
    {
        $documents = [];
        $existingDirs = $projectMetadata['existingDirectories'] ?? $projectMetadata['directories'] ?? [];

        // Add API Routes and Models document
        $sourcePaths = [];
        if (\in_array('app', $existingDirs, true)) {
            $sourcePaths[] = 'app';
        }
        if (\in_array('src', $existingDirs, true)) {
            $sourcePaths[] = 'src';
        }
        if (\in_array('api', $existingDirs, true)) {
            $sourcePaths[] = 'api';
        }

        // If no structured directories, check for main.py in root
        if (empty($sourcePaths)) {
            $sourcePaths = ['.'];
        }

        $documents[] = new Document(
            description: 'FastAPI Routes and Endpoints',
            outputPath: 'docs/fastapi-routes.md',
            tags: ['fastapi', 'routes', 'api'],
            fileSource: new FileSource(
                sourcePaths: $sourcePaths,
                description: 'FastAPI Routes and Endpoints',
                filePattern: ['*.py'],
                contains: ['@app.', 'APIRouter', 'FastAPI'],
            ),
        );

        // Add Models and Schemas document if those directories exist
        if (\in_array('models', $existingDirs, true) || \in_array('schemas', $existingDirs, true)) {
            $modelPaths = [];
            if (\in_array('models', $existingDirs, true)) {
                $modelPaths[] = 'models';
            }
            if (\in_array('schemas', $existingDirs, true)) {
                $modelPaths[] = 'schemas';
            }

            $documents[] = new Document(
                description: 'FastAPI Models and Schemas',
                outputPath: 'docs/fastapi-models-schemas.md',
                tags: ['fastapi', 'models', 'schemas'],
                fileSource: new FileSource(
                    sourcePaths: $modelPaths,
                    description: 'FastAPI Models and Schemas',
                    filePattern: ['*.py'],
                    contains: ['BaseModel', 'SQLModel', 'pydantic'],
                ),
            );
        }

        return $documents;
    }
}
