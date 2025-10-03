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
        if (!isset($markdownData['markdownFiles']) || !\is_array(value: $markdownData['markdownFiles'])) {
            $this->logger?->warning('Invalid markdown data structure');
            return [];
        }

        $config = [
            'documents' => [],
            'prompts' => [],
            'resources' => [],
        ];

        foreach ($markdownData['markdownFiles'] as $fileData) {
            $resource = $this->transformSingleFile(fileData: $fileData);
            if ($resource !== null) {
                $resourceType = $resource['_type'];
                unset($resource['_type']);

                $config[$resourceType][] = $resource;
            }
        }

        // Remove empty sections
        $config = \array_filter(array: $config, callback: static fn(array $section) => !empty($section));

        $this->logger?->debug('Transformation completed', [
            'documentsCount' => \count(value: $config['documents'] ?? []),
            'promptsCount' => \count(value: $config['prompts'] ?? []),
            'resourcesCount' => \count(value: $config['resources'] ?? []),
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
        $resourceType = $this->determineResourceType(metadata: $metadata);

        // Generate ID from filename if not provided in metadata
        $id = $metadata['id'] ?? $this->generateIdFromFilename(filename: $name);

        // Get description with fallback hierarchy
        $description = $this->getDescription(metadata: $metadata, relativePath: $relativePath);

        return match ($resourceType) {
            'prompt' => $this->createPromptResource(id: $id, metadata: $metadata, content: $content, relativePath: $relativePath, description: $description),
            default => $this->createGenericResource(id: $id, metadata: $metadata, content: $content, relativePath: $relativePath, description: $description),
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
            $type = \strtolower(string: $metadata['type']);
            if (\in_array(needle: $type, haystack: ['prompt', 'resource'], strict: true)) {
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
            $prompt['tags'] = $this->normalizeTagsArray(tags: $metadata['tags']);
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
            'messagesCount' => \count(value: $prompt['messages']),
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
            $document['tags'] = $this->normalizeTagsArray(tags: $metadata['tags']);
        }

        // Create sources - either from metadata or convert content to text source
        if (!empty($metadata['sources']) && \is_array(value: $metadata['sources'])) {
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
            'sourcesCount' => \count(value: $document['sources']),
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
        return $this->createDocumentResource(id: $id, metadata: $metadata, content: $content, relativePath: $relativePath, description: $description);
    }

    /**
     * Generate a valid ID from filename
     */
    private function generateIdFromFilename(string $filename): string
    {
        // Convert filename to a valid identifier
        $id = \strtolower(string: $filename);
        $id = \preg_replace(pattern: '/[^a-z0-9]+/', replacement: '-', subject: $id);
        $id = \trim(string: (string) $id, characters: '-');

        return $id ?: 'unknown';
    }

    /**
     * Normalize tags to array format
     */
    private function normalizeTagsArray(mixed $tags): array
    {
        if (\is_string(value: $tags)) {
            // Handle comma-separated string
            return \array_map(callback: \trim(...), array: \explode(separator: ',', string: $tags));
        }

        if (\is_array(value: $tags)) {
            // Filter to ensure all elements are strings
            return \array_filter(array: \array_map(callback: \strval(...), array: $tags));
        }

        return [];
    }
}
