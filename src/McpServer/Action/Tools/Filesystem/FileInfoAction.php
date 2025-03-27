<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\FilesInterface;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class FileInfoAction
{
    public function __construct(
        private LoggerInterface $logger,
        private FilesInterface $files,
        private Directories $dirs,
    ) {}

    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing file-info tool');

        // Get params from the parsed body for POST requests
        $parsedBody = $request->getParsedBody();
        $path = $this->dirs->getFilePath($parsedBody['path'] ?? '');

        if (empty($path)) {
            return new CallToolResult([
                new TextContent(
                    text: 'Error: Missing path parameter',
                ),
            ], isError: true);
        }

        try {
            if (!$this->files->exists($path)) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: File or directory '%s' does not exist", $path),
                    ),
                ], isError: true);
            }

            if (\is_dir($path)) {
                return new CallToolResult([
                    new TextContent(
                        text: \sprintf("Error: '%s' is a directory", $path),
                    ),
                ], isError: true);
            }

            $info = $this->getFileInfo($path);

            return new CallToolResult([
                new TextContent(
                    text: \json_encode($info, JSON_PRETTY_PRINT),
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error getting file info', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        }
    }

    /**
     * Get information about a file or directory
     */
    private function getFileInfo(string $path): array
    {
        $isFile = \is_file($path);
        $isDir = \is_dir($path);
        $info = [
            'path' => $path,
            'exists' => true,
            'type' => $isFile ? 'file' : ($isDir ? 'directory' : 'unknown'),
            'size' => $isFile ? \filesize($path) : null,
            'permissions' => \decoct(\fileperms($path) & 0777),
            'lastModified' => \date('Y-m-d H:i:s', \filemtime($path)),
        ];

        // Add directory contents if it's a directory
        if ($isDir) {
            $contents = \scandir($path);
            $files = [];
            $directories = [];

            foreach ($contents as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $fullPath = \rtrim($path, '/') . '/' . $item;
                if (\is_dir($fullPath)) {
                    $directories[] = $item;
                } else {
                    $files[] = $item;
                }
            }

            $info['contents'] = [
                'files' => $files,
                'directories' => $directories,
                'count' => [
                    'files' => \count($files),
                    'directories' => \count($directories),
                    'total' => \count($files) + \count($directories),
                ],
            ];
        }

        return $info;
    }
}