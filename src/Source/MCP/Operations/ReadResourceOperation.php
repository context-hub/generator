<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP\Operations;

use Butschster\ContextGenerator\Lib\Content\ContentBuilder;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Mcp\Client\ClientSession;
use Mcp\Types\TextResourceContents;

/**
 * Operation for reading resources from an MCP server
 */
final readonly class ReadResourceOperation extends AbstractOperation
{
    /**
     * @param array<string> $resources List of resource URIs to read
     */
    public function __construct(
        public array $resources = [],
    ) {
        parent::__construct('resource.read');
    }

    /**
     * Execute this operation on the given client session
     *
     * @param ClientSession $session MCP client session
     * @param VariableResolver $resolver Variable resolver for resolving variables in configuration
     * @return string Raw content of all resources
     */
    public function execute(ClientSession $session, VariableResolver $resolver): string
    {
        $results = [];

        // Resolve variables in all resource URIs
        $resolvedResources = \array_map($resolver->resolve(...), $this->resources);

        // Read each resource
        foreach ($resolvedResources as $uri) {
            $result = $session->readResource($uri);


            foreach ($result->contents as $content) {
                if ($content instanceof TextResourceContents) {
                    $results[] = $content->text;
                }
            }
        }

        // Return JSON-encoded results for later processing
        return \implode("\n", $results);
    }

    /**
     * Build content for this operation
     *
     * @param ContentBuilder $builder Content builder to add content to
     * @param string $content The processed content to build with
     */
    #[\Override]
    public function buildContent(ContentBuilder $builder, string $content): void
    {
        // Add code block for this resource
        $builder->addCodeBlock($content, 'json');
    }

    /**
     * Serialize to JSON
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'resources' => $this->resources,
        ];
    }

    /**
     * Determine language from content type and URI
     */
    private function determineLanguageFromContentTypeAndUri(?string $contentType, string $uri): ?string
    {
        // If content type is set, try to determine language from it
        if ($contentType !== null) {
            return match (true) {
                \str_contains($contentType, 'php') => 'php',
                \str_contains($contentType, 'javascript') => 'javascript',
                \str_contains($contentType, 'typescript') => 'typescript',
                \str_contains($contentType, 'json') => 'json',
                \str_contains($contentType, 'yaml') || \str_contains($contentType, 'yml') => 'yaml',
                \str_contains($contentType, 'html') => 'html',
                \str_contains($contentType, 'css') => 'css',
                \str_contains($contentType, 'markdown') || \str_contains($contentType, 'md') => 'markdown',
                default => null,
            };
        }

        // Otherwise, try to determine from file extension
        $extension = \pathinfo($uri, PATHINFO_EXTENSION);

        return match ($extension) {
            'php' => 'php',
            'js' => 'javascript',
            'ts' => 'typescript',
            'json' => 'json',
            'yaml', 'yml' => 'yaml',
            'html', 'htm' => 'html',
            'css' => 'css',
            'md', 'markdown' => 'markdown',
            default => null,
        };
    }

    /**
     * Extract filename from URI
     */
    private function extractFilenameFromUri(string $uri): string
    {
        // Remove scheme and authority
        $path = \preg_replace('#^[^:]+://[^/]+#', '', $uri);

        // If the URI is just a path, return it
        if ($path === $uri) {
            return $uri;
        }

        return $path ?: $uri;
    }
}
