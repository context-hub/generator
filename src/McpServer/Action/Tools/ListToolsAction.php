<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools;

use Mcp\Types\ListToolsResult;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListToolsAction
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ServerRequestInterface $request): ListToolsResult
    {
        $this->logger->info('Listing available tools');

        $tools = [
            'tools' => [
                [
                    'name' => 'context-request',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'json' => [
                                'type' => 'string',
                                'description' => 'JSON string to process',
                            ],
                        ],
                        'required' => ['json'],
                    ],
                    'description' => 'Requests the context from server using JSON schema',
                ],
                [
                    'name' => 'context-get',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Path to the document',
                            ],
                        ],
                        'required' => ['document'],
                    ],
                    'description' => 'Requests a document by path',
                ],
                [
                    'name' => 'context',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [],
                        'required' => [],
                    ],
                    'description' => 'Provide list of available contexts',
                ],
            ],
        ];

        return ListToolsResult::fromResponseData($tools);
    }
}
