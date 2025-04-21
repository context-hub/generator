<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects\Repository;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Projects\DTO\ProjectStateDTO;
use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Repository for managing project state persistence
 */
#[LoggerPrefix(prefix: 'projects.repository')]
final readonly class ProjectStateRepository implements ProjectStateRepositoryInterface
{
    /**
     * File path for storing project state
     */
    private const string STATE_FILE = '.project-state.json';

    public function __construct(
        private FilesInterface $files,
        private LoggerInterface $logger,
        private string $stateDirectory,
    ) {}

    public function load(): ProjectStateDTO
    {
        $stateFile = $this->getStateFilePath();

        if (!$this->files->exists($stateFile)) {
            return new ProjectStateDTO();
        }

        try {
            $content = $this->files->read($stateFile);
            $stateArray = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (!\is_array($stateArray)) {
                throw new \RuntimeException('Invalid state file format');
            }

            return ProjectStateDTO::fromArray($stateArray);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to load project state', [
                'error' => $e->getMessage(),
                'file' => $stateFile,
            ]);

            // Return empty state on error
            return new ProjectStateDTO();
        }
    }

    public function save(ProjectStateDTO $state): bool
    {
        $stateFile = $this->getStateFilePath();

        try {
            $content = \json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $this->files->ensureDirectory(\dirname($stateFile));
            $this->files->write($stateFile, $content);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to save project state', [
                'error' => $e->getMessage(),
                'file' => $stateFile,
            ]);

            return false;
        }
    }

    /**
     * Get the path to the state file
     */
    private function getStateFilePath(): string
    {
        return (string) FSPath::create($this->stateDirectory)->join(self::STATE_FILE);
    }
}
