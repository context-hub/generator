<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools;

use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Mcp\Types\ListToolsResult;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class ListToolsAction
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    #[Get(path: '/tools/list', name: 'tools.list')]
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
                                'description' => 'Path to the file, relative to project root. Only files within project directory can be accessed.',
                            ],
                            'encoding' => [
                                'type' => 'string',
                                'description' => 'File encoding (default: utf-8)',
                                'default' => 'utf-8',
                            ],
                        ],
                        'required' => ['path'],
                    ],
                    'description' => 'Read content from a file within the project directory structure',
                ],
                [
                    'name' => 'file-write',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Relative path to the file (e.g., "src/file.txt"). Path is resolved against project root.',
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
                    'description' => 'Write content to a file. Can create parent directories automatically.',
                ],
                [
                    'name' => 'file-rename',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Current relative path of the file/directory. Must exist within project directory.',
                            ],
                            'newPath' => [
                                'type' => 'string',
                                'description' => 'New relative path for the file/directory. Must remain within project directory.',
                            ],
                        ],
                        'required' => ['path', 'newPath'],
                    ],
                    'description' => 'Rename a file or directory. Verifies source exists and destination doesn\'t.',
                ],
                [
                    'name' => 'file-move',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'source' => [
                                'type' => 'string',
                                'description' => 'Source relative path of the file to move. Must exist within project directory.',
                            ],
                            'destination' => [
                                'type' => 'string',
                                'description' => 'Destination relative path where file will be moved. Must remain within project directory.',
                            ],
                            'createDirectory' => [
                                'type' => 'boolean',
                                'description' => 'Create destination directory if it does not exist',
                                'default' => true,
                            ],
                        ],
                        'required' => ['source', 'destination'],
                    ],
                    'description' => 'Move a file to another location. Implemented as copy+delete for safety.',
                ],
                [
                    'name' => 'file-info',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Path to the file or directory, relative to project root. Returns detailed metadata.',
                            ],
                        ],
                        'required' => ['path'],
                    ],
                    'description' => 'Get information about a file or directory including type, size, permissions, and modification time.',
                ],
            ],
        ];

        return ListToolsResult::fromResponseData($tools);
    }
}
