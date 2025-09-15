<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing\Handler\Prompts;

use Butschster\ContextGenerator\McpServer\Routing\Handler\BaseHandler;
use Laminas\Diactoros\Response\JsonResponse;
use Mcp\Types\GetPromptRequestParams;
use Mcp\Types\GetPromptResult;
use Mcp\Types\ListPromptsResult;

/**
 * Handler for prompt-related MCP requests
 */
final readonly class PromptsHandler extends BaseHandler implements PromptsHandlerInterface
{
    #[\Override]
    public function handlePromptsList(): ListPromptsResult
    {
        try {
            return $this->handleRoute('prompts/list');
        } catch (\Throwable $e) {
            $this->logError('Prompts list', 'prompts/list', $e);
            return new ListPromptsResult([]);
        }
    }

    #[\Override]
    public function handlePromptsGet(GetPromptRequestParams $params): GetPromptResult
    {
        $params = $this->projectService->processPromptRequestParams($params);

        $name = $params->name;
        $arguments = $params->arguments;
        $method = 'prompt/' . $name;

        $this->logger->debug('Handling prompt get', [
            'prompt' => $name,
            'method' => $method,
            'arguments' => (array) $arguments->jsonSerialize(),
        ]);

        try {
            // Create PSR request with the prompt name in the path and arguments as POST body
            $request = $this->requestAdapter->createPsrRequest($method, (array) $arguments->jsonSerialize());

            $response = $this->router->dispatch($request);
            \assert($response instanceof JsonResponse);

            return $response->getPayload();
        } catch (\Throwable $e) {
            $this->logError('Prompt get', $method, $e);
            return new GetPromptResult([]);
        }
    }
}
