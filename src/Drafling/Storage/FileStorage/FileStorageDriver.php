<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Storage\FileStorage;

use Butschster\ContextGenerator\Drafling\Config\DraflingConfigInterface;
use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryCreateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\EntryUpdateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectCreateRequest;
use Butschster\ContextGenerator\Drafling\MCP\DTO\ProjectUpdateRequest;
use Butschster\ContextGenerator\Drafling\Repository\EntryRepositoryInterface;
use Butschster\ContextGenerator\Drafling\Repository\ProjectRepositoryInterface;
use Butschster\ContextGenerator\Drafling\Repository\TemplateRepositoryInterface;
use Butschster\ContextGenerator\Drafling\Storage\AbstractStorageDriver;
use Butschster\ContextGenerator\Drafling\Storage\Config\FileStorageConfig;
use Butschster\ContextGenerator\Drafling\Exception\ProjectNotFoundException;
use Butschster\ContextGenerator\Drafling\Exception\EntryNotFoundException;
use Butschster\ContextGenerator\Drafling\Exception\TemplateNotFoundException;
use Spiral\Files\FilesInterface;

/**
 * File-based storage driver implementation using Markdown files with YAML frontmatter
 * 
 * @extends AbstractStorageDriver<FileStorageConfig>
 */
final class FileStorageDriver extends AbstractStorageDriver
{
    private readonly ProjectRepositoryInterface $projectRepository;
    private readonly EntryRepositoryInterface $entryRepository;
    private readonly TemplateRepositoryInterface $templateRepository;

    public function __construct(
        DraflingConfigInterface $draflingConfig,
        FilesInterface $files,
        ?\Psr\Log\LoggerInterface $logger = null,
    ) {
        parent::__construct($draflingConfig, $logger);
        
        // Initialize repositories
        $frontmatterParser = new FrontmatterParser();
        $directoryScanner = new DirectoryScanner($files);
        
        $this->templateRepository = new FileTemplateRepository(
            $files,
            $draflingConfig,
            $frontmatterParser,
            $directoryScanner,
            $logger,
        );
        
        $this->projectRepository = new FileProjectRepository(
            $files,
            $draflingConfig,
            $frontmatterParser,
            $directoryScanner,
            $logger,
        );
        
        $this->entryRepository = new FileEntryRepository(
            $files,
            $draflingConfig,
            $frontmatterParser,
            $directoryScanner,
            $logger,
        );
    }

    #[\Override]
    public function supports(string $type): bool
    {
        return $type === 'markdown' || $type === 'file';
    }

    #[\Override]
    public function getName(): string
    {
        return 'file_storage';
    }

    #[\Override]
    public function initialize(object $config): void
    {
        if (!$config instanceof FileStorageConfig) {
            throw new \InvalidArgumentException('FileStorageDriver requires FileStorageConfig');
        }

        // Validate configuration
        $errors = $config->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Invalid FileStorageConfig: ' . \implode(', ', $errors));
        }

        parent::initialize($config);
    }

    #[\Override]
    public function createProject(ProjectCreateRequest $request): Project
    {
        // Validate template exists
        $templateKey = \Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey::fromString($request->templateId);
        $template = $this->templateRepository->findByKey($templateKey);
        
        if ($template === null) {
            throw TemplateNotFoundException::withKey($request->templateId);
        }

        // Generate project ID and create project
        $projectId = $this->generateId('proj_');
        $project = new Project(
            id: $projectId,
            name: $request->name,
            description: $request->description,
            template: $request->templateId,
            status: $this->config->defaultEntryStatus,
            tags: $request->tags,
            entryDirs: !empty($request->entryDirs) ? $request->entryDirs : $this->getDefaultEntryDirs($template),
        );

        $this->projectRepository->save($project);
        $this->logOperation('Created project', ['id' => $projectId, 'name' => $request->name]);

        return $project;
    }

    #[\Override]
    public function updateProject(ProjectId $projectId, ProjectUpdateRequest $request): Project
    {
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw ProjectNotFoundException::withId($projectId->value);
        }

        if (!$request->hasUpdates()) {
            return $project;
        }

        $updatedProject = $project->withUpdates(
            name: $request->name,
            description: $request->description,
            status: $request->status,
            tags: $request->tags,
            entryDirs: $request->entryDirs,
        );

        $this->projectRepository->save($updatedProject);
        $this->logOperation('Updated project', ['id' => $projectId->value]);

        return $updatedProject;
    }

    #[\Override]
    public function deleteProject(ProjectId $projectId): bool
    {
        if (!$this->projectRepository->exists($projectId)) {
            return false;
        }

        $deleted = $this->projectRepository->delete($projectId);
        if ($deleted) {
            $this->logOperation('Deleted project', ['id' => $projectId->value]);
        }

        return $deleted;
    }

    #[\Override]
    public function createEntry(ProjectId $projectId, EntryCreateRequest $request): Entry
    {
        // Verify project exists
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw ProjectNotFoundException::withId($projectId->value);
        }

        // Validate against template
        $this->validateEntryAgainstTemplate($project, $request);

        // Generate entry ID and create entry
        $entryId = $this->generateId('entry_');
        $now = $this->getCurrentTimestamp();
        
        $entry = new Entry(
            entryId: $entryId,
            title: $request->title,
            entryType: $request->entryType,
            category: $request->category,
            status: $request->status ?? $this->config->defaultEntryStatus,
            createdAt: $now,
            updatedAt: $now,
            tags: $request->tags,
            content: $request->content,
        );

        $this->entryRepository->save($projectId, $entry);
        $this->logOperation('Created entry', [
            'project_id' => $projectId->value,
            'entry_id' => $entryId,
            'title' => $request->title,
        ]);

        return $entry;
    }

    #[\Override]
    public function updateEntry(ProjectId $projectId, EntryId $entryId, EntryUpdateRequest $request): Entry
    {
        $entry = $this->entryRepository->findById($projectId, $entryId);
        if ($entry === null) {
            throw EntryNotFoundException::withId($projectId->value, $entryId->value);
        }

        if (!$request->hasUpdates()) {
            return $entry;
        }

        $updatedEntry = $entry->withUpdates(
            title: $request->title,
            status: $request->status,
            tags: $request->tags,
            content: $request->content,
        );

        $this->entryRepository->save($projectId, $updatedEntry);
        $this->logOperation('Updated entry', [
            'project_id' => $projectId->value,
            'entry_id' => $entryId->value,
        ]);

        return $updatedEntry;
    }

    #[\Override]
    public function deleteEntry(ProjectId $projectId, EntryId $entryId): bool
    {
        if (!$this->entryRepository->exists($projectId, $entryId)) {
            return false;
        }

        $deleted = $this->entryRepository->delete($projectId, $entryId);
        if ($deleted) {
            $this->logOperation('Deleted entry', [
                'project_id' => $projectId->value,
                'entry_id' => $entryId->value,
            ]);
        }

        return $deleted;
    }

    #[\Override]
    protected function performSynchronization(): void
    {
        // Refresh template cache
        $this->templateRepository->refresh();
        
        $this->logOperation('Synchronized file storage');
    }

    /**
     * Get default entry directories from template
     */
    private function getDefaultEntryDirs(\Butschster\ContextGenerator\Drafling\Domain\Model\Template $template): array
    {
        $dirs = [];
        foreach ($template->categories as $category) {
            $dirs[] = $category->name;
        }
        return $dirs;
    }

    /**
     * Validate entry request against project template
     */
    private function validateEntryAgainstTemplate(Project $project, EntryCreateRequest $request): void
    {
        $templateKey = \Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey::fromString($project->template);
        $template = $this->templateRepository->findByKey($templateKey);
        
        if ($template === null) {
            throw TemplateNotFoundException::withKey($project->template);
        }

        // Validate category exists
        if (!$template->hasCategory($request->category)) {
            throw new \InvalidArgumentException("Category '{$request->category}' not found in template '{$template->key}'");
        }

        // Validate entry type exists
        if (!$template->hasEntryType($request->entryType)) {
            throw new \InvalidArgumentException("Entry type '{$request->entryType}' not found in template '{$template->key}'");
        }

        // Validate entry type is allowed in category
        if (!$template->validateEntryInCategory($request->category, $request->entryType)) {
            throw new \InvalidArgumentException("Entry type '{$request->entryType}' is not allowed in category '{$request->category}'");
        }

        // Validate status if provided
        if ($request->status !== null) {
            $entryType = $template->getEntryType($request->entryType);
            if ($entryType !== null && !$entryType->hasStatus($request->status)) {
                throw new \InvalidArgumentException("Status '{$request->status}' is not valid for entry type '{$request->entryType}'");
            }
        }
    }

    /**
     * Get project repository
     */
    public function getProjectRepository(): ProjectRepositoryInterface
    {
        return $this->projectRepository;
    }

    /**
     * Get entry repository
     */
    public function getEntryRepository(): EntryRepositoryInterface
    {
        return $this->entryRepository;
    }

    /**
     * Get template repository
     */
    public function getTemplateRepository(): TemplateRepositoryInterface
    {
        return $this->templateRepository;
    }
}
