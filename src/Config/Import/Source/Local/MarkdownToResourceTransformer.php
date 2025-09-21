<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source\Local;

use Psr\Log\LoggerInterface;

/**
 * Transforms parsed markdown files into CTX configuration resources
 */
final readonly class MarkdownToResourceTransformer
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Transform an array of parsed markdown files into CTX configuration
     */
    public function transform(array $markdownData): array
    {
        if (!isset($markdownData['markdownFiles']) || !\is_array($markdownData['markdownFiles'])) {
            $this->logger?->warning('Invalid markdown data structure');
            return [];
        }

        $config = [
            'documents' => [],
            'prompts' => [],
            'resources' => [],
        ];

        foreach ($markdownData['markdownFiles'] as $fileData) {
            $resource = $this->transformSingleFile($fileData);
            if ($resource !== null) {
                $resourceType = $resource['_type'];
                unset($resource['_type']);

                $config[$resourceType][] = $resource;
            }
        }

        // Remove empty sections
        $config = \array_filter($config, static fn(array $section) => !empty($section));

        $this->logger?->debug('Transformation completed', [
            'documentsCount' => \count($config['documents'] ?? []),
            'promptsCount' => \count($config['prompts'] ?? []),
            'resourcesCount' => \count($config['resources'] ?? []),
        ]);

        return $config;
    }

    /**
     * Transform a single parsed markdown file into a CTX resource
     */
    private function transformSingleFile(array $fileData): ?array
    {
        $metadata = $fileData['metadata'] ?? [];
        $content = $fileData['content'] ?? '';
        $name = $fileData['name'] ?? '';
        $relativePath = $fileData['relativePath'] ?? '';

        // Determine resource type from metadata or default to 'resource'
        $resourceType = $this->determineResourceType($metadata);

        // Generate ID from filename if not provided in metadata
        $id = $metadata['id'] ?? $this->generateIdFromFilename($name);

        // Get description with fallback hierarchy
        $description = $this->getDescription($metadata, $relativePath);

        return match ($resourceType) {
            'prompt' => $this->createPromptResource($id, $metadata, $content, $relativePath, $description),
            default => $this->createGenericResource($id, $metadata, $content, $relativePath, $description),
        };
    }

    /**
     * Get description with fallback hierarchy: description -> title -> generated from path
     */
    private function getDescription(array $metadata, string $relativePath): string
    {
        // Priority: explicit description > title from metadata/header > generated from path
        if (!empty($metadata['description'])) {
            return $metadata['description'];
        }

        if (!empty($metadata['title'])) {
            return $metadata['title'];
        }

        return "Resource from {$relativePath}";
    }

    /**
     * Determine the resource type from metadata
     */
    private function determineResourceType(array $metadata): string
    {
        // Check explicit type declaration
        if (isset($metadata['type'])) {
            $type = \strtolower($metadata['type']);
            if (\in_array($type, ['prompt', 'resource'], true)) {
                return $type;
            }
        }

        // for claude we can detect if it is a prompt by model key
        if (isset($metadata['model'])) {
            return 'prompt';
        }

        // Infer type from other metadata properties
        if (isset($metadata['role']) || isset($metadata['messages']) || isset($metadata['schema'])) {
            return 'prompt';
        }

        // Default to resource
        return 'resource';
    }

    /**
     * Create a prompt resource
     */
    private function createPromptResource(string $id, array $metadata, string $content, string $relativePath, string $description): array
    {
        $prompt = [
            '_type' => 'prompts',
            'id' => $id,
            'description' => $description,
            'type' => $metadata['promptType'] ?? 'prompt',
        ];

        // Add tags if present
        if (!empty($metadata['tags'])) {
            $prompt['tags'] = $this->normalizeTagsArray($metadata['tags']);
        }

        // Add schema if present
        if (!empty($metadata['schema'])) {
            $prompt['schema'] = $metadata['schema'];
        }

        // Convert content to a simple user message
        $prompt['messages'] = [
            [
                'role' => $metadata['role'] ?? 'user',
                'content' => $content,
            ],
        ];

        $this->logger?->debug('Created prompt resource', [
            'id' => $id,
            'description' => $description,
            'type' => $prompt['type'],
            'hasSchema' => isset($prompt['schema']),
            'messagesCount' => \count($prompt['messages']),
            'hasTitle' => !empty($metadata['title']),
        ]);

        return $prompt;
    }

    /**
     * Create a document resource
     */
    private function createDocumentResource(string $id, array $metadata, string $content, string $relativePath, string $description): array
    {
        $document = [
            '_type' => 'documents',
            'description' => $description,
            'outputPath' => $metadata['outputPath'] ?? "docs/{$id}.md",
        ];

        // Add overwrite flag if specified
        if (isset($metadata['overwrite'])) {
            $document['overwrite'] = (bool) $metadata['overwrite'];
        }

        // Add tags if present
        if (!empty($metadata['tags'])) {
            $document['tags'] = $this->normalizeTagsArray($metadata['tags']);
        }

        // Create sources - either from metadata or convert content to text source
        if (!empty($metadata['sources']) && \is_array($metadata['sources'])) {
            $document['sources'] = $metadata['sources'];
        } else {
            // Convert content to a text source
            $sourceDescription = !empty($metadata['title'])
                ? $metadata['title']
                : "Content from {$relativePath}";

            $document['sources'] = [
                [
                    'type' => 'text',
                    'description' => $sourceDescription,
                    'content' => $content,
                    'tags' => ['markdown', 'imported'],
                ],
            ];
        }

        $this->logger?->debug('Created document resource', [
            'description' => $description,
            'outputPath' => $document['outputPath'],
            'sourcesCount' => \count($document['sources']),
            'hasTitle' => !empty($metadata['title']),
        ]);

        return $document;
    }

    /**
     * Create a generic resource (for unknown or unspecified types)
     */
    private function createGenericResource(string $id, array $metadata, string $content, string $relativePath, string $description): array
    {
        // For now, convert generic resources to documents
        // This provides a sensible default behavior
        return $this->createDocumentResource($id, $metadata, $content, $relativePath, $description);
    }

    /**
     * Generate a valid ID from filename
     */
    private function generateIdFromFilename(string $filename): string
    {
        // Convert filename to a valid identifier
        $id = \strtolower($filename);
        $id = \preg_replace('/[^a-z0-9]+/', '-', $id);
        $id = \trim($id, '-');

        return $id ?: 'unknown';
    }

    /**
     * Normalize tags to array format
     */
    private function normalizeTagsArray(mixed $tags): array
    {
        if (\is_string($tags)) {
            // Handle comma-separated string
            return \array_map(\trim(...), \explode(',', $tags));
        }

        if (\is_array($tags)) {
            // Filter to ensure all elements are strings
            return \array_filter(\array_map(\strval(...), $tags));
        }

        return [];
    }
}
