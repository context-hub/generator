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
                        'required' => ['path'],
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
                // New filesystem tools
                [
                    'name' => 'file-read',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Path to the file',
                            ],
                            'encoding' => [
                                'type' => 'string',
                                'description' => 'File encoding (default: utf-8)',
                                'default' => 'utf-8',
                            ],
                        ],
                        'required' => ['path'],
                    ],
                    'description' => 'Read content from a file',
                ],
                [
                    'name' => 'file-write',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Path to the file',
                            ],
                            'content' => [
                                'type' => 'string',
                                'description' => 'Content to write',
                            ],
                            'createDirectory' => [
                                'type' => 'boolean',
                                'description' => 'Create directory if it does not exist',
                                'default' => true,
                            ],
                        ],
                        'required' => ['path', 'content'],
                    ],
                    'description' => 'Write content to a file',
                ],
                [
                    'name' => 'file-rename',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Current path',
                            ],
                            'newPath' => [
                                'type' => 'string',
                                'description' => 'New path',
                            ],
                        ],
                        'required' => ['path', 'newPath'],
                    ],
                    'description' => 'Rename a file or directory',
                ],
                [
                    'name' => 'file-move',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'source' => [
                                'type' => 'string',
                                'description' => 'Source path',
                            ],
                            'destination' => [
                                'type' => 'string',
                                'description' => 'Destination path',
                            ],
                            'createDirectory' => [
                                'type' => 'boolean',
                                'description' => 'Create destination directory if it does not exist',
                                'default' => true,
                            ],
                        ],
                        'required' => ['source', 'destination'],
                    ],
                    'description' => 'Move a file to another location',
                ],
                [
                    'name' => 'file-info',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Path to the file or directory',
                            ],
                        ],
                        'required' => ['path'],
                    ],
                    'description' => 'Get information about a file or directory',
                ],
            ],
        ];

        return ListToolsResult::fromResponseData($tools);
    }
}
