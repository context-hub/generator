<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Definition;

use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Source\File\FileSource;

/**
 * Gin web framework template definition
 */
final class GinTemplateDefinition extends AbstractTemplateDefinition
{
    public function getName(): string
    {
        return 'gin';
    }

    public function getDescription(): string
    {
        return 'Gin Go web framework project template';
    }

    public function getTags(): array
    {
        return ['go', 'golang', 'gin', 'web', 'api'];
    }

    public function getPriority(): int
    {
        return 85;
    }

    protected function getSourceDirectories(): array
    {
        return ['cmd', 'pkg', 'internal', 'api', 'handlers', 'middleware', 'models'];
    }

    protected function getFrameworkSpecificCriteria(): array
    {
        return [
            'files' => ['go.mod'],
            'patterns' => ['github.com/gin-gonic/gin'],
        ];
    }

    /**
     * Add Gin-specific documents
     */
    #[\Override]
    protected function createAdditionalDocuments(array $projectMetadata): array
    {
        $documents = [];
        $existingDirs = $projectMetadata['existingDirectories'] ?? $projectMetadata['directories'] ?? [];

        // Add handlers and routes document
        $handlerPaths = [];
        if (\in_array('handlers', $existingDirs, true)) {
            $handlerPaths[] = 'handlers';
        }
        if (\in_array('api', $existingDirs, true)) {
            $handlerPaths[] = 'api';
        }
        if (\in_array('internal', $existingDirs, true)) {
            $handlerPaths[] = 'internal';
        }

        if (!empty($handlerPaths)) {
            $documents[] = new Document(
                description: 'Gin Handlers and Routes',
                outputPath: 'docs/gin-handlers-routes.md',
                tags: ['gin', 'handlers', 'routes'],
                fileSource: new FileSource(
                    sourcePaths: $handlerPaths,
                    description: 'Gin Handlers and Routes',
                    filePattern: ['*.go'],
                    contains: ['gin.', 'c.JSON', 'router.', 'engine.'],
                ),
            );
        }

        // Add middleware document if middleware directory exists
        if (\in_array('middleware', $existingDirs, true)) {
            $documents[] = new Document(
                description: 'Gin Middleware',
                outputPath: 'docs/gin-middleware.md',
                tags: ['gin', 'middleware'],
                fileSource: new FileSource(
                    sourcePaths: ['middleware'],
                    description: 'Gin Middleware',
                    filePattern: ['*.go'],
                ),
            );
        }

        return $documents;
    }
}
