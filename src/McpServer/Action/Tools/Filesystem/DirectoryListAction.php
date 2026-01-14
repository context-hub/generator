<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem;

use Butschster\ContextGenerator\Config\Exclude\ExcludeRegistryInterface;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\DirectoryListRequest;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Post;
use PhpMcp\Schema\Result\CallToolResult;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Symfony\Component\Finder\Finder;

#[Tool(
    name: 'directory-list',
    description: 'List directories and files with filtering options using Symfony Finder. Always ask for source path.',
    title: 'Directory List',
)]
#[InputSchema(class: DirectoryListRequest::class)]
final readonly class DirectoryListAction
{
    public function __construct(
        private LoggerInterface $logger,
        #[Proxy] private DirectoriesInterface $dirs,
        private FileTreeBuilder $treeBuilder,
        private ExcludeRegistryInterface $excludeRegistry,
    ) {}

    #[Post(path: '/tools/call/directory-list', name: 'tools.directory-list')]
    public function __invoke(DirectoryListRequest $request): CallToolResult
    {
        $this->logger->info('Processing directory-list tool');

        // Get params from the parsed body for POST requests
        $relativePath = $request->path;
        $path = (string) $this->dirs->getRootPath()->join($relativePath);

        if (empty($path)) {
            return ToolResult::error('Missing path parameter');
        }

        try {
            if (!\file_exists($path)) {
                return ToolResult::error(\sprintf("Path '%s' does not exist", $relativePath));
            }

            if (!\is_dir($path)) {
                return ToolResult::error(\sprintf("Path '%s' is not a directory", $relativePath));
            }

            // Create and configure Symfony Finder
            $finder = new Finder();
            $finder->in($path);

            // Apply pattern filter if provided
            if (!empty($request->pattern)) {
                $patterns = \array_map(\trim(...), \explode(',', $request->pattern));
                $finder->name($patterns);
            }

            // Apply depth filter if provided
            $depth = $request->depth;
            $finder->depth('<= ' . $depth);

            // Apply size filter if provided
            if (!empty($request->size)) {
                $finder->size($request->size);
            }

            // Apply date filter if provided
            if (!empty($request->date)) {
                $finder->date($request->date);
            }

            // Apply content filter if provided
            if (!empty($request->contains)) {
                $finder->contains($request->contains);
            }

            // Apply type filter if provided
            $type = \strtolower($request->type);
            if ($type === 'file') {
                $finder->files();
            } elseif ($type === 'directory') {
                $finder->directories();
            } else {
                // Default: include both files and directories
                $finder->ignoreDotFiles(false);
            }

            // Apply sorting if provided
            $sort = \strtolower($request->sort);
            match ($sort) {
                'name' => $finder->sortByName(),
                'type' => $finder->sortByType(),
                'date' => $finder->sortByModifiedTime(),
                'size' => $finder->sortBySize(),
                // Default sort by name
                default => $finder->sortByName(),
            };

            // Collect results
            $files = [];
            $filePaths = [];

            try {
                foreach ($finder as $file) {
                    $fileRelativePath = \str_replace((string) $this->dirs->getRootPath() . '/', '', $file->getRealPath());

                    // Skip excluded files and directories
                    if ($this->excludeRegistry->shouldExclude($fileRelativePath)) {
                        continue;
                    }

                    $files[] = [
                        'name' => $file->getFilename(),
                        'path' => $fileRelativePath,
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
            if ($request->showTree === true) {
                $treeViewConfig = new TreeViewConfig();

                // Apply tree view configuration if provided
                if ($request->treeView !== null) {
                    $treeViewConfig = new TreeViewConfig(
                        enabled: true,
                        showSize: $request->treeView->showSize,
                        showLastModified: $request->treeView->showLastModified,
                        showCharCount: $request->treeView->showCharCount,
                        includeFiles: $request->treeView->includeFiles,
                        maxDepth: $request->depth,
                        dirContext: [],
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

            return ToolResult::success($responseData);
        } catch (\Throwable $e) {
            $this->logger->error('Error listing directory', [
                'path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ToolResult::error($e->getMessage());
        }
    }
}
