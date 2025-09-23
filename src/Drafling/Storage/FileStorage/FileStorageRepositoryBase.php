<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Storage\FileStorage;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Drafling\Config\DraflingConfigInterface;
use Spiral\Files\FilesInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Abstract base class for file-based repositories
 */
abstract class FileStorageRepositoryBase
{
    public function __construct(
        protected readonly FilesInterface $files,
        protected readonly DraflingConfigInterface $config,
        private readonly DirectoriesInterface $dirs,
        protected readonly FrontmatterParser $frontmatterParser = new FrontmatterParser(),
        protected readonly ?DirectoryScanner $directoryScanner = null,
        protected readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Get base path for storage operations
     */
    protected function getBasePath(): string
    {
        return (string) $this->dirs->getRootPath()->join($this->config->getProjectsPath());
    }

    /**
     * Get templates base path
     */
    protected function getTemplatesPath(): string
    {
        return (string) $this->dirs->getRootPath()->join($this->config->getTemplatesPath());
    }

    /**
     * Ensure directory exists
     */
    protected function ensureDirectory(string $path): void
    {
        if (!$this->files->exists($path)) {
            $this->files->ensureDirectory($path);
            $this->logger?->debug('Created directory', ['path' => $path]);
        }
    }

    /**
     * Read and parse YAML file
     */
    protected function readYamlFile(string $filePath): array
    {
        if (!$this->files->exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $content = $this->files->read($filePath);

        try {
            return Yaml::parse($content) ?? [];
        } catch (ParseException $e) {
            throw new \RuntimeException("Failed to parse YAML file '{$filePath}': {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Write array data as YAML file
     */
    protected function writeYamlFile(string $filePath, array $data): void
    {
        $yamlContent = Yaml::dump(
            $data,
            4,
            2,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
        );

        $this->ensureDirectory(\dirname($filePath));
        $this->files->write($filePath, $yamlContent);

        $this->logger?->debug('Wrote YAML file', ['path' => $filePath]);
    }

    /**
     * Read markdown file with frontmatter
     */
    protected function readMarkdownFile(string $filePath): array
    {
        if (!$this->files->exists($filePath)) {
            throw new \RuntimeException("Markdown file not found: {$filePath}");
        }

        $content = $this->files->read($filePath);
        return $this->frontmatterParser->parse($content);
    }

    /**
     * Write markdown file with frontmatter
     */
    protected function writeMarkdownFile(string $filePath, array $frontmatter, string $content): void
    {
        $fileContent = $this->frontmatterParser->combine($frontmatter, $content);

        $this->ensureDirectory(\dirname($filePath));
        $this->files->write($filePath, $fileContent);

        $this->logger?->debug('Wrote markdown file', ['path' => $filePath]);
    }

    /**
     * Generate safe filename from title
     */
    protected function generateFilename(string $title, string $extension = 'md'): string
    {
        $slug = \strtolower($title);
        $slug = \preg_replace('/[^a-z0-9\s\-]/', '', $slug);
        $slug = \preg_replace('/[\s\-]+/', '-', (string) $slug);
        $slug = \trim((string) $slug, '-');

        return $slug . '.' . $extension;
    }

    /**
     * Log operation with context
     */
    protected function logOperation(string $operation, array $context = []): void
    {
        $this->logger?->info("File storage operation: {$operation}", $context);
    }

    /**
     * Log error with context
     */
    protected function logError(string $message, array $context = [], ?\Throwable $exception = null): void
    {
        $this->logger?->error($message, [
            'exception' => $exception?->getMessage(),
            ...$context,
        ]);
    }
}
