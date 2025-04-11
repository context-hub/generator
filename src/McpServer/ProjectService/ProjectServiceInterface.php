<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\ProjectService;

use Butschster\ContextGenerator\Application\AppScope;
use Mcp\Types\CallToolRequestParams;
use Mcp\Types\GetPromptRequestParams;
use Mcp\Types\ReadResourceRequestParams;
use Spiral\Core\Attribute\Scope;

#[Scope(name: AppScope::Mcp)]
interface ProjectServiceInterface
{
    public function processToolRequestParams(CallToolRequestParams $params): CallToolRequestParams;

    public function processPromptRequestParams(GetPromptRequestParams $params): GetPromptRequestParams;

    public function processResourceRequestParams(ReadResourceRequestParams $params): ReadResourceRequestParams;

    public function processResponse(mixed $payload): mixed;
}
