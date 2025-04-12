<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\ProjectService;

use Mcp\Types\CallToolRequestParams;
use Mcp\Types\GetPromptRequestParams;
use Mcp\Types\ListPromptsResult;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\ListToolsResult;
use Mcp\Types\Prompt;
use Mcp\Types\ReadResourceRequestParams;
use Mcp\Types\Resource;
use Mcp\Types\Tool;

final readonly class ProjectService implements ProjectServiceInterface
{
    private const string URI_CTX_PREFIX = 'ctx://';

    public function __construct(
        private ?string $projectName,
        private ?string $projectSlug,
    ) {}

    public function processResponse(mixed $payload): mixed
    {
        if (!$this->isEnable()) {
            return $payload;
        }

        if ($payload instanceof ListToolsResult) {
            return new ListToolsResult(
                tools: \array_map(fn($item) => $this->processTool($item), $payload->tools),
            );
        }

        if ($payload instanceof ListPromptsResult) {
            return new ListPromptsResult(
                prompts: \array_map(fn($item) => $this->processPrompt($item), $payload->prompts),
            );
        }

        if ($payload instanceof ListResourcesResult) {
            return new ListResourcesResult(
                resources: \array_map(fn(Resource $item) => $this->processResource($item), $payload->resources),
            );
        }

        return $payload;
    }

    public function processToolRequestParams(CallToolRequestParams $params): CallToolRequestParams
    {
        if (!$this->isEnable()) {
            return $params;
        }

        return new CallToolRequestParams(
            name: $this->removeToolPostfix($params->name),
            arguments: $params->arguments,
            _meta: $params->_meta,
        );
    }

    public function processPromptRequestParams(GetPromptRequestParams $params): GetPromptRequestParams
    {
        return $params;
    }

    public function processResourceRequestParams(ReadResourceRequestParams $params): ReadResourceRequestParams
    {
        if (!$this->isEnable()) {
            return $params;
        }

        return new ReadResourceRequestParams(
            uri: $this->removeResourceUriPrefix($params->uri),
            _meta: $params->_meta,
        );
    }

    private function addProjectSignature(string $description): string
    {
        return 'Important ONLY for project "' . $this->projectName . '". ' . $description;
    }

    private function processTool(Tool $tool): Tool
    {
        return new Tool(
            name: $this->addToolPostfix($tool->name),
            inputSchema: $tool->inputSchema,
            description: $this->addProjectSignature($tool->description),
        );
    }

    private function processPrompt(Prompt $item): Prompt
    {
        return new Prompt(
            name: $item->name,
            description: $this->addProjectSignature($item->description),
            arguments: $item->arguments,
        );
    }

    private function processResource(Resource $item): Resource
    {
        return new Resource(
            name: $this->addResourceNamePrefix($item->name),
            uri: $this->addResourceUriPrefix($item->uri),
            description: $this->addProjectSignature($item->description),
            mimeType: $item->mimeType,
            annotations: $item->annotations,
        );
    }

    private function isEnable(): bool
    {
        return $this->projectName !== null;
    }

    private function postfix(): string
    {
        return '_from_' . $this->projectSlug;
    }

    private function addToolPostfix(string $name = ''): string
    {
        return $name . $this->postfix();
    }

    private function removeToolPostfix(string $name): string
    {
        return \str_replace($this->postfix(), '', $name);
    }

    private function addResourceNamePrefix(string $name): string
    {
        return "[{$this->projectName}] " . $name;
    }

    private function uriPrefix(): string
    {
        return $this->projectSlug . '://';
    }

    private function addResourceUriPrefix(string $uri): string
    {
        return \str_replace(self::URI_CTX_PREFIX, $this->uriPrefix(), $uri);
    }

    private function removeResourceUriPrefix(string $uri): string
    {
        return \str_replace($this->uriPrefix(), self::URI_CTX_PREFIX, $uri);
    }
}
