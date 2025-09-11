<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

#[Tool(
    name: 'directory-list',
    description: 'List directories and files with filtering options using Symfony Finder. Always ask for source path.',
    title: 'Directory List',
)]
#[InputSchema(
    name: 'path',
    type: 'string',
    description: 'Base directory path to list (relative to project root)',
    required: true,
)]
#[InputSchema(
    name: 'pattern',
    type: 'string',
    description: 'File name pattern(s) to match (e.g., "*.php", comma-separated for multiple patterns)',
    required: false,
)]
#[InputSchema(
    name: 'depth',
    type: 'integer',
    description: 'Maximum directory depth to search (0 means only files in the given directory) [default: 0]',
    required: false,
)]
#[InputSchema(
    name: 'size',
    type: 'string',
    description: 'Size filter expression (e.g., "> 1K", "< 10M", ">=1K <=10K")',
    required: false,
)]
#[InputSchema(
    name: 'date',
    type: 'string',
    description: 'Date filter expression (e.g., "since yesterday", "> 2023-01-01", "< now - 2 hours")',
    required: false,
)]
#[InputSchema(
    name: 'contains',
    type: 'string',
    description: 'Only files containing this text (uses grep-like behavior)',
    required: false,
)]
#[InputSchema(
    name: 'type',
    type: 'string',
    description: 'Filter by type: "file", "directory", or "any" (default)',
    required: false,
)]
#[InputSchema(
    name: 'sort',
    type: 'string',
    description: 'How to sort results: "name", "type", "date", "size"',
    required: false,
)]
#[InputSchema(
    name: 'showTree',
    type: 'boolean',
    description: 'Whether to include visual ASCII tree in the response',
    required: false,
)]
#[InputSchema(
    name: 'treeView',
    type: 'object',
    description: 'Configuration options for tree view visualization',
    required: false,
    properties: [
        'showSize' => ['type' => 'boolean', 'description' => 'Show file sizes in tree view'],
        'showLastModified' => ['type' => 'boolean', 'description' => 'Show last modified dates in tree view'],
        'showCharCount' => ['type' => 'boolean', 'description' => 'Show character counts in tree view'],
        'includeFiles' => [
            'type' => 'boolean',
            'description' => 'Include files in tree view (false to show only directories)',
        ],
    ],
)]
final readonly class DirectoryListAction
{
    public function __construct(
        private LoggerInterface $logger,
        private DirectoriesInterface $dirs,
        private FileTreeBuilder $treeBuilder,
    ) {}

    #[Post(path: '/tools/call/directory-list', name: 'tools.directory-list')]
    public function __invoke(ServerRequestInterface $request): CallToolResult
    {
        $this->logger->info('Processing directory-list tool');

        // Get params from the parsed body for POST requests
        $parsedBody = $request->getParsedBody();
        $relativePath = $parsedBody['path'] ?? '';
        $path = (string) $this->dirs->getRootPath()->join($relativePath);

        if (empty($path)) {
            return new CallToolResult([
                new TextContent(text: 'Error: Missing path parameter'),
            ], isError: true);
        }

        try {
            if (!\file_exists($path)) {
                return new CallToolResult([
                    new TextContent(text: \sprintf("Error: Path '%s' does not exist", $relativePath)),
                ], isError: true);
            }

            if (!\is_dir($path)) {
                return new CallToolResult([
                    new TextContent(text: \sprintf("Error: Path '%s' is not a directory", $relativePath)),
                ], isError: true);
            }

            // Create and configure Symfony Finder
            $finder = new Finder();
            $finder->in($path);

            // Apply pattern filter if provided
            if (!empty($parsedBody['pattern'])) {
                $patterns = \array_map('trim', \explode(',', (string) $parsedBody['pattern']));
                $finder->name($patterns);
            }

            // Apply depth filter if provided
            if (isset($parsedBody['depth'])) {
                $depth = (int) $parsedBody['depth'];
                $finder->depth('<= ' . $depth);
            } else {
                $finder->depth(0);
            }

            // Apply size filter if provided
            if (!empty($parsedBody['size'])) {
                $finder->size($parsedBody['size']);
            }

            // Apply date filter if provided
            if (!empty($parsedBody['date'])) {
                $finder->date($parsedBody['date']);
            }

            // Apply content filter if provided
            if (!empty($parsedBody['contains'])) {
                $finder->contains($parsedBody['contains']);
            }

            // Apply type filter if provided
            if (!empty($parsedBody['type'])) {
                $type = \strtolower((string) $parsedBody['type']);
                if ($type === 'file') {
                    $finder->files();
                } elseif ($type === 'directory') {
                    $finder->directories();
                }
            } else {
                // Default: include both files and directories
                $finder->ignoreDotFiles(false);
            }

            // Apply sorting if provided
            if (!empty($parsedBody['sort'])) {
                $sort = \strtolower((string) $parsedBody['sort']);
                switch ($sort) {
                    case 'name':
                        $finder->sortByName();
                        break;
                    case 'type':
                        $finder->sortByType();
                        break;
                    case 'date':
                        $finder->sortByModifiedTime();
                        break;
                    case 'size':
                        $finder->sortBySize();
                        break;
                }
            } else {
                // Default sort by name
                $finder->sortByName();
            }

            // Collect results
            $files = [];
            $filePaths = [];

            try {
                foreach ($finder as $file) {
                    $relativePath = \str_replace((string) $this->dirs->getRootPath() . '/', '', $file->getRealPath());

                    $files[] = [
                        'name' => $file->getFilename(),
                        'path' => $relativePath,
                        'fullPath' => $file->getRealPath(),
                        'isDirectory' => $file->isDir(),
                        'size' => $file->isFile() ? $file->getSize() : null,
                        'lastModified' => \date('Y-m-d H:i:s', $file->getMTime()),
                    ];

                    $filePaths[] = $file->getRealPath();
                }
            } catch (\Exception $e) {
                // Handle the case where Finder might not find anything
                $this->logger->warning('Finder search warning', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }

            // Generate tree view if requested
            $treeView = null;
            if (!empty($parsedBody['showTree']) && (bool) $parsedBody['showTree'] === true) {
                $treeViewConfig = new TreeViewConfig();

                // Apply tree view configuration if provided
                if (!empty($parsedBody['treeView']) && \is_array($parsedBody['treeView'])) {
                    $configData = $parsedBody['treeView'];
                    $treeViewConfig = new TreeViewConfig(
                        enabled: true,
                        showSize: $configData['showSize'] ?? false,
                        showLastModified: $configData['showLastModified'] ?? false,
                        showCharCount: $configData['showCharCount'] ?? false,
                        includeFiles: $configData['includeFiles'] ?? true,
                        maxDepth: isset($parsedBody['depth']) ? (int) $parsedBody['depth'] : 0,
                        dirContext: $configData['dirContext'] ?? [],
                    );
                }

                if (!empty($filePaths)) {
                    $treeView = $this->treeBuilder->buildTree(
                        $filePaths,
                        (string) $this->dirs->getRootPath(),
                        $treeViewConfig->getOptions(),
                    );
                } else {
                    $treeView = "No files match the specified criteria.";
                }
            }

            // Prepare response data
            $responseData = [
                'count' => \count($files),
                'basePath' => $relativePath,
            ];

            if ($treeView !== null) {
                $responseData['treeView'] = $treeView;
            } else {
                $responseData['files'] = $files;
            }

            return new CallToolResult([
                new TextContent(
                    text: \json_encode($responseData, JSON_PRETTY_PRINT),
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error listing directory', [
                'path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new CallToolResult([
                new TextContent(
                    text: 'Error: ' . $e->getMessage(),
                ),
            ], isError: true);
        }
    }
}
