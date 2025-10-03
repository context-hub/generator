<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\Composer\Provider\ComposerProviderInterface;
use Butschster\ContextGenerator\Source\Fetcher\SourceFetcherInterface;
use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\Source\File\FileSourceFetcher;
use Butschster\ContextGenerator\Source\File\SymfonyFinder;
use Butschster\ContextGenerator\Source\SourceInterface;
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
        SymfonyFinder $finder,
        private string $basePath = '.',
        private ContentBuilderFactory $builderFactory = new ContentBuilderFactory(),
        private VariableResolver $variableResolver = new VariableResolver(),
        #[LoggerPrefix(prefix: 'composer-source-fetcher')]
        private ?LoggerInterface $logger = null,
    ) {
        // Create a FileSourceFetcher to handle the actual file fetching
        $this->fileSourceFetcher = new FileSourceFetcher(
            basePath: $this->basePath,
            finder: $finder,
            builderFactory: $this->builderFactory,
            logger: $this->logger instanceof LoggerInterface ? $this->logger : new NullLogger(),
        );
    }

    public function supports(SourceInterface $source): bool
    {
        $isSupported = $source instanceof ComposerSource;
        $this->logDebug(message: 'Checking if source is supported', context: [
            'sourceType' => $source::class,
            'isSupported' => $isSupported,
        ]);
        return $isSupported;
    }

    public function fetch(SourceInterface $source, ModifiersApplierInterface $modifiersApplier): string
    {
        if (!$source instanceof ComposerSource) {
            $errorMessage = 'Source must be an instance of ComposerSource';
            $this->logError(message: $errorMessage, context: [
                'sourceType' => $source::class,
            ]);
            throw new \InvalidArgumentException(message: $errorMessage);
        }

        $description = $this->variableResolver->resolve(strings: $source->getDescription());

        $this->logInfo(message: 'Fetching Composer source content', context: [
            'description' => $description,
            'composerPath' => $source->composerPath,
            'includeDevDependencies' => $source->includeDevDependencies,
        ]);

        // Create a content builder
        $builder = $this->builderFactory
            ->create()
            ->addTitle(title: $description);

        // Get packages from the provider
        $packages = $this->provider->getPackages(
            $source->composerPath,
            $source->includeDevDependencies,
        );

        // Filter packages if packages is set
        $packages = $packages->filter(pattern: $source->packages);

        if ($packages->count() === 0) {
            $this->logWarning(message: 'No matching packages found', context: [
                'composerPath' => $source->composerPath,
                'packages' => $source->packages,
            ]);

            $builder->addText(text: 'No matching packages found.');
            return $builder->build();
        }

        $this->logInfo(message: 'Found matching packages', context: [
            'count' => $packages->count(),
            'packages' => \array_keys(array: $packages->all()),
        ]);

        // Generate a tree view of selected packages if requested
        if ($source->treeView->enabled) {
            $this->logDebug(message: 'Generating package tree view');
            $builder->addTreeView(treeView: $packages->generateTree());
        }

        // For each package, fetch its source code
        foreach ($packages as $package) {
            $this->logInfo(message: 'Processing package', context: [
                'name' => $package->name,
                'version' => $package->version,
                'path' => $package->path,
            ]);

            $builder->addTitle(title: \sprintf('%s (%s)', $package->name, $package->version), level: 2);

            // Add package description and metadata if available
            if ($package->getDescription()) {
                $builder->addDescription(description: $package->getDescription());
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
                $builder->addText(text: \implode(separator: "\n", array: $metadata));
            }

            // Get source directories for this package
            $sourceDirs = $package->getSourceDirectories();
            $this->logDebug(message: 'Found source directories', context: [
                'package' => $package->name,
                'directories' => $sourceDirs,
            ]);

            // For each source directory, create a FileSource and fetch its content
            foreach ($sourceDirs as $dir) {
                $sourceDir = $package->path . '/' . $dir;
                if (!\is_dir(filename: $sourceDir)) {
                    $this->logWarning(message: 'Source directory not found', context: [
                        'package' => $package->name,
                        'directory' => $sourceDir,
                    ]);
                    continue;
                }

                $this->logDebug(message: 'Creating FileSource for directory', context: [
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
                    treeView: $source->treeView, // Show tree view for individual directories if requested
                    modifiers: $source->modifiers,
                );

                // Use the FileSourceFetcher to fetch the content
                try {
                    $content = $this->fileSourceFetcher->fetch(source: $fileSource, modifiersApplier: $modifiersApplier);
                    $builder->addText(text: $content);
                } catch (\Throwable $e) {
                    $this->logError(message: 'Error fetching package source', context: [
                        'package' => $package->name,
                        'directory' => $sourceDir,
                        'error' => $e->getMessage(),
                    ]);

                    $builder->addText(
                        text: \sprintf(
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
        $this->logInfo(message: 'Composer source content fetched successfully', context: [
            'packageCount' => $packages->count(),
            'contentLength' => \strlen(string: $content),
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
