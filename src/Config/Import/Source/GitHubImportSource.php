<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\Source;

use Butschster\ContextGenerator\Config\Import\ImportConfig;
use Butschster\ContextGenerator\Config\Reader\StringJsonReader;
use Butschster\ContextGenerator\Lib\GithubClient\GithubClientInterface;
use Butschster\ContextGenerator\Lib\GithubClient\Model\GithubRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Import source for GitHub repository configurations
 */
final class GitHubImportSource extends AbstractImportSource
{
    public function __construct(
        private readonly GithubClientInterface $githubClient,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
    }

    public function getName(): string
    {
        return 'github';
    }

    public function supports(ImportConfig $config): bool
    {
        return ($config->type ?? 'local') === 'github';
    }

    public function load(ImportConfig $config): array
    {
        if (!$this->supports($config)) {
            throw Exception\ImportSourceException::sourceNotSupported(
                $config->path,
                $config->type ?? 'unknown',
            );
        }

        // Set GitHub token if provided
        if (isset($config->token)) {
            $this->githubClient->setToken($this->resolveEnvToken($config->token));
        }

        try {
            // Parse GitHub repository path
            $repository = $this->parseRepository($config->path);
            $branch = $config->ref ?? $repository->branch;

            $this->logger->debug('Loading GitHub import', [
                'repository' => $repository->getFullName(),
                'branch' => $branch,
                'path' => $config->path,
            ]);

            // Fetch the configuration file
            $configPath = $this->getRepositoryPath($config);
            $fileContent = $this->githubClient->getFileContent(
                $repository->owner,
                $repository->name,
                $configPath,
                $branch,
            );

            // Parse the file based on its extension
            $extension = \pathinfo($configPath, PATHINFO_EXTENSION);
            $importedConfig = $this->parseContent($fileContent, $extension);

            // Process selective imports if specified
            return $this->processSelectiveImports($importedConfig, $config);
        } catch (\Throwable $e) {
            $this->logger->error('GitHub import failed', [
                'path' => $config->path,
                'error' => $e->getMessage(),
            ]);

            throw Exception\ImportSourceException::githubError(
                $config->path,
                $e->getMessage(),
            );
        }
    }

    /**
     * Parse GitHub repository from path string
     */
    private function parseRepository(string $path): GithubRepository
    {
        // Path format: owner/repo/path/to/file.yaml
        // Extract the owner/repo part
        $parts = \explode('/', $path, 3);
        if (\count($parts) < 2) {
            throw new \InvalidArgumentException(
                "Invalid GitHub repository path format: {$path}. Expected format: owner/repo/path/to/file",
            );
        }

        return GithubRepository::fromString("{$parts[0]}/{$parts[1]}");
    }

    /**
     * Get the path within the repository
     */
    private function getRepositoryPath(ImportConfig $config): string
    {
        $path = $config->path;
        $parts = \explode('/', $path, 3);

        // If there's no path part (e.g., owner/repo), use a default file
        if (\count($parts) < 3 || empty($parts[2])) {
            return 'context.yaml';
        }

        return $parts[2];
    }

    /**
     * Parse content based on file extension
     */
    private function parseContent(string $content, string $extension): array
    {
        return match ($extension) {
            'json' => (new StringJsonReader($content))->read(''),
            'yaml', 'yml' => Yaml::parse($content),
            default => throw new \InvalidArgumentException(
                "Unsupported file extension for GitHub import: {$extension}",
            ),
        };
    }

    /**
     * Resolve environment variables in token
     */
    private function resolveEnvToken(string $token): string
    {
        // If token starts with ${...}, get it from env
        if (\preg_match('/^\${([^}]+)}$/', $token, $matches)) {
            $envVar = $matches[1];
            return \getenv($envVar) ?: '';
        }

        return $token;
    }
}
