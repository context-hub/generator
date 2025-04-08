<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ProjectService;

use Butschster\ContextGenerator\Application\AppScope;
use Mcp\Types\CallToolRequestParams;
use Mcp\Types\GetPromptRequestParams;
use Mcp\Types\ReadResourceRequestParams;
use Spiral\Core\Attribute\Scope;

#[Scope(name: AppScope::Mcp)]
interface ProjectServiceInterface
{
    /**
     * @template TParams of mixed
     * @param TParams $params
     * @return TParams
     */
    public function processRequestParams(
        CallToolRequestParams|GetPromptRequestParams|ReadResourceRequestParams $params,
    ): mixed;

    public function processResponse(mixed $payload): mixed;
}
