<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer;

use Butschster\ContextGenerator\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\TreeBuilder\FileTreeBuilder;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\Composer\Provider\ComposerProviderInterface;
use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\Source\File\FileSourceFetcher;
use Butschster\ContextGenerator\Source\File\SymfonyFinder;
use Butschster\ContextGenerator\SourceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Fetcher for Composer package sources
 * @implements SourceFetcherInterface<ComposerSource>
 */
final readonly class ComposerSourceFetcher implements SourceFetcherInterface
{
    private FileSourceFetcher $fileSourceFetcher;

    public function __construct(
        private ComposerProviderInterface $provider,
        private string $basePath = '.',
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private FileTreeBuilder $treeBuilder = new FileTreeBuilder(),
        private ?LoggerInterface $logger = null,
    ) {
        // Create a FileSourceFetcher to handle the actual file fetching
        $this->fileSourceFetcher = new FileSourceFetcher(
            basePath: $this->basePath,
            finder: new SymfonyFinder($this->treeBuilder),
            builderFactory: $this->builderFactory,
            logger: $this->logger instanceof LoggerInterface ? $this->logger : new NullLogger(),
        );
    }

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof ComposerSource;
        $this->logDebug('Checking if source is supported', [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        if (!$source instanceof ComposerSource) {
            $errorMessage = 'Source must be an instance of ComposerSource';
            $this->logError($errorMessage, [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException($errorMessage);
        }

        $this->logInfo('Fetching Composer source content', [
            'description' => $source->getDescription(),
            'composerPath' => $source->composerPath,
            'includeDevDependencies' => $source->includeDevDependencies,
        ]);

        // Create a content builder
        $builder = $this->builderFactory
            ->create()
            ->addTitle($source->getDescription());

        // Get packages from the provider
        $packages = $this->provider->getPackages(
            $source->composerPath,
            $source->includeDevDependencies,
        );

        // Filter packages if packages is set
        $packages = $packages->filter($source->packages);

        if ($packages->count() === 0) {
            $this->logWarning('No matching packages found', [
                'composerPath' => $source->composerPath,
                'packages' => $source->packages,
            ]);

            $builder->addText('No matching packages found.');
            return $builder->build();
        }

        $this->logInfo('Found matching packages', [
            'count' => $packages->count(),
            'packages' => \array_keys($packages->all()),
        ]);

        // Generate a tree view of selected packages if requested
        if ($source->showTreeView) {
            $this->logDebug('Generating package tree view');
            $builder->addTreeView($packages->generateTree());
        }

        // For each package, fetch its source code
        foreach ($packages as $packageName => $package) {
            $this->logInfo('Processing package', [
                'name' => $package->name,
                'version' => $package->version,
                'path' => $package->path,
            ]);

            $builder->addTitle(\sprintf('%s (%s)', $package->name, $package->version), 2);

            // Add package description and metadata if available
            if ($package->getDescription()) {
                $builder->addDescription($package->getDescription());
            }

            // Create a metadata section with authors, license, homepage, etc.
            $metadata = [];

            if ($authors = $package->getFormattedAuthors()) {
                $metadata[] = "**Authors:** {$authors}";
            }

            if ($license = $package->getFormattedLicense()) {
                $metadata[] = "**License:** {$license}";
            }

            if ($homepage = $package->getHomepage()) {
                $metadata[] = "**Homepage:** {$homepage}";
            }

            if (!empty($metadata)) {
                $builder->addText(\implode("\n", $metadata));
            }

            // Get source directories for this package
            $sourceDirs = $package->getSourceDirectories();
            $this->logDebug('Found source directories', [
                'package' => $package->name,
                'directories' => $sourceDirs,
            ]);

            // For each source directory, create a FileSource and fetch its content
            foreach ($sourceDirs as $dir) {
                $sourceDir = $package->path . '/' . $dir;
                if (!\is_dir($sourceDir)) {
                    $this->logWarning('Source directory not found', [
                        'package' => $package->name,
                        'directory' => $sourceDir,
                    ]);
                    continue;
                }

                $this->logDebug('Creating FileSource for directory', [
                    'package' => $package->name,
                    'directory' => $sourceDir,
                ]);

                // Create a FileSource for this directory
                $fileSource = new FileSource(
                    sourcePaths: $sourceDir,
                    filePattern: $source->filePattern,
                    notPath: $source->notPath,
                    path: $source->path,
                    contains: $source->contains,
                    notContains: $source->notContains,
                    showTreeView: $source->showTreeView, // Show tree view for individual directories if requested
                    modifiers: $source->modifiers,
                );

                // Use the FileSourceFetcher to fetch the content
                try {
                    $content = $this->fileSourceFetcher->fetch($fileSource, $modifiersApplier);
                    $builder->addText($content);
                } catch (\Throwable $e) {
                    $this->logError('Error fetching package source', [
                        'package' => $package->name,
                        'directory' => $sourceDir,
                        'error' => $e->getMessage(),
                    ]);

                    $builder->addText(
                        \sprintf(
                            "Error fetching source for %s in directory %s: %s",
                            $package->name,
                            $sourceDir,
                            $e->getMessage(),
                        ),
                    );
                }
            }

            $builder->addSeparator();
        }

        $content = $builder->build();
        $this->logInfo('Composer source content fetched successfully', [
            'packageCount' => $packages->count(),
            'contentLength' => \strlen($content),
        ]);

        return $content;
    }

    /**
     * Log a debug message if a logger is available
     */
    private function logDebug(string $message, array $context = []): void
    {
        $this->logger?->debug($message, $context);
    }

    /**
     * Log an info message if a logger is available
     */
    private function logInfo(string $message, array $context = []): void
    {
        $this->logger?->info($message, $context);
    }

    /**
     * Log a warning message if a logger is available
     */
    private function logWarning(string $message, array $context = []): void
    {
        $this->logger?->warning($message, $context);
    }

    /**
     * Log an error message if a logger is available
     */
    private function logError(string $message, array $context = []): void
    {
        $this->logger?->error($message, $context);
    }
}
