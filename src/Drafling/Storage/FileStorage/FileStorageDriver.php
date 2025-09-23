<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Storage\FileStorage;

use Butschster\ContextGenerator\Drafling\Config\DraflingConfigInterface;
use Butschster\ContextGenerator\Drafling\Domain\Model\Entry;
use Butschster\ContextGenerator\Drafling\Domain\Model\Project;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\ProjectId;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;
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
use Spiral\Exceptions\ExceptionReporterInterface;
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
        ExceptionReporterInterface $reporter,
        ?\Psr\Log\LoggerInterface $logger = null,
    ) {
        parent::__construct($draflingConfig, $logger);

        // Initialize repositories
        $frontmatterParser = new FrontmatterParser();
        $directoryScanner = new DirectoryScanner($files, $reporter);

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
        $templateKey = TemplateKey::fromString($request->templateId);
        $template = $this->templateRepository->findByKey($templateKey);

        if ($template === null) {
            throw TemplateNotFoundException::withKey($request->templateId);
        }

        // Generate project ID and create project
        $projectId = $this->generateId('proj_');
        $project = new Project(
            id: $projectId,
            name: $request->getName(), // Use getName() method for consistency
            description: $request->description,
            template: $request->templateId,
            status: $this->config->defaultEntryStatus,
            tags: $request->tags,
            entryDirs: !empty($request->entryDirs) ? $request->entryDirs : $this->getDefaultEntryDirs($template),
        );

        $this->projectRepository->save($project);
        $this->logOperation('Created project', ['id' => $projectId, 'name' => $request->getName()]);

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
            name: $request->getName(), // Use getName() method for consistency
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

        // Get template for validation and key resolution
        $templateKey = TemplateKey::fromString($project->template);
        $template = $this->templateRepository->findByKey($templateKey);
        if ($template === null) {
            throw TemplateNotFoundException::withKey($project->template);
        }

        // Resolve display names to internal keys
        $resolvedRequest = $this->resolveEntryCreateRequestKeys($request, $template);

        // Validate resolved request against template
        $this->validateEntryAgainstTemplate($template, $resolvedRequest);

        // Generate entry ID and create entry
        $entryId = $this->generateId('entry_');
        $now = $this->getCurrentTimestamp();

        $entry = new Entry(
            entryId: $entryId,
            title: $resolvedRequest->getProcessedTitle(), // Use processed title
            entryType: $resolvedRequest->entryType,
            category: $resolvedRequest->category,
            status: $resolvedRequest->status ?? $this->config->defaultEntryStatus,
            createdAt: $now,
            updatedAt: $now,
            tags: $resolvedRequest->tags,
            content: $resolvedRequest->content,
        );

        $this->entryRepository->save($projectId, $entry);
        $this->logOperation('Created entry', [
            'project_id' => $projectId->value,
            'entry_id' => $entryId,
            'title' => $entry->title,
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

        // Resolve status if provided
        $resolvedRequest = $request;
        if ($request->status !== null) {
            $project = $this->projectRepository->findById($projectId);
            if ($project !== null) {
                $templateKey = TemplateKey::fromString($project->template);
                $template = $this->templateRepository->findByKey($templateKey);
                if ($template !== null) {
                    $resolvedStatus = $this->resolveStatusForEntryType($template, $entry->entryType, $request->status);
                    $resolvedRequest = $request->withResolvedStatus($resolvedStatus);
                }
            }
        }

        // Get final content considering text replacement
        $finalContent = $resolvedRequest->getFinalContent($entry->content);

        $updatedEntry = $entry->withUpdates(
            title: $resolvedRequest->title,
            status: $resolvedRequest->status,
            tags: $resolvedRequest->tags,
            content: $finalContent, // Use processed content with text replacement
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
     * Resolve display names in entry create request to internal keys
     */
    private function resolveEntryCreateRequestKeys(
        EntryCreateRequest $request,
        \Butschster\ContextGenerator\Drafling\Domain\Model\Template $template,
    ): EntryCreateRequest {
        // Resolve category
        $resolvedCategory = $this->resolveCategoryKey($template, $request->category);
        if ($resolvedCategory === null) {
            throw new \InvalidArgumentException("Category '{$request->category}' not found in template '{$template->key}'");
        }

        // Resolve entry type
        $resolvedEntryType = $this->resolveEntryTypeKey($template, $request->entryType);
        if ($resolvedEntryType === null) {
            throw new \InvalidArgumentException("Entry type '{$request->entryType}' not found in template '{$template->key}'");
        }

        // Resolve status if provided
        $resolvedStatus = null;
        if ($request->status !== null) {
            $resolvedStatus = $this->resolveStatusForEntryType($template, $resolvedEntryType, $request->status);
            if ($resolvedStatus === null) {
                throw new \InvalidArgumentException("Status '{$request->status}' not found for entry type '{$resolvedEntryType}' in template '{$template->key}'");
            }
        }

        return $request->withResolvedKeys($resolvedCategory, $resolvedEntryType, $resolvedStatus);
    }

    /**
     * Validate entry request against project template
     */
    private function validateEntryAgainstTemplate(
        \Butschster\ContextGenerator\Drafling\Domain\Model\Template $template,
        EntryCreateRequest $request,
    ): void {
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
     * Resolve category display name to internal key
     */
    private function resolveCategoryKey(
        \Butschster\ContextGenerator\Drafling\Domain\Model\Template $template,
        string $displayNameOrKey,
    ): ?string {
        foreach ($template->categories as $category) {
            if ($category->name === $displayNameOrKey || $category->displayName === $displayNameOrKey) {
                return $category->name;
            }
        }
        return null;
    }

    /**
     * Resolve entry type display name to internal key
     */
    private function resolveEntryTypeKey(
        \Butschster\ContextGenerator\Drafling\Domain\Model\Template $template,
        string $displayNameOrKey,
    ): ?string {
        foreach ($template->entryTypes as $entryType) {
            if ($entryType->key === $displayNameOrKey || $entryType->displayName === $displayNameOrKey) {
                return $entryType->key;
            }
        }
        return null;
    }

    /**
     * Resolve status display name to internal value for specific entry type
     */
    private function resolveStatusForEntryType(
        \Butschster\ContextGenerator\Drafling\Domain\Model\Template $template,
        string $entryTypeKey,
        string $displayNameOrValue,
    ): ?string {
        $entryType = $template->getEntryType($entryTypeKey);
        if ($entryType === null) {
            return null;
        }

        foreach ($entryType->statuses as $status) {
            if ($status->value === $displayNameOrValue || $status->displayName === $displayNameOrValue) {
                return $status->value;
            }
        }

        return null;
    }
}
