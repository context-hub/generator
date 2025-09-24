<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Storage\FileStorage;

use Butschster\ContextGenerator\Research\Domain\Model\Entry;
use Butschster\ContextGenerator\Research\Domain\Model\Research;
use Butschster\ContextGenerator\Research\Domain\Model\Template;
use Butschster\ContextGenerator\Research\Domain\ValueObject\EntryId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\ResearchId;
use Butschster\ContextGenerator\Research\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Research\MCP\DTO\EntryCreateRequest;
use Butschster\ContextGenerator\Research\MCP\DTO\EntryUpdateRequest;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchCreateRequest;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchMemory;
use Butschster\ContextGenerator\Research\MCP\DTO\ResearchUpdateRequest;
use Butschster\ContextGenerator\Research\Repository\EntryRepositoryInterface;
use Butschster\ContextGenerator\Research\Repository\ResearchRepositoryInterface;
use Butschster\ContextGenerator\Research\Repository\TemplateRepositoryInterface;
use Butschster\ContextGenerator\Research\Exception\ResearchNotFoundException;
use Butschster\ContextGenerator\Research\Exception\EntryNotFoundException;
use Butschster\ContextGenerator\Research\Exception\TemplateNotFoundException;
use Butschster\ContextGenerator\Research\Storage\StorageDriverInterface;
use Cocur\Slugify\SlugifyInterface;
use Psr\Log\LoggerInterface;

final readonly class FileStorageDriver implements StorageDriverInterface
{
    public function __construct(
        private FileStorageConfig $driverConfig,
        private TemplateRepositoryInterface $templateRepository,
        private ResearchRepositoryInterface $researchRepository,
        private EntryRepositoryInterface $entryRepository,
        private SlugifyInterface $slugify,
        private ?LoggerInterface $logger = null,
    ) {}

    public function supports(string $type): bool
    {
        return $type === 'markdown' || $type === 'file';
    }

    public function getName(): string
    {
        return 'file_storage';
    }

    public function createResearch(ResearchCreateRequest $request): Research
    {
        // Validate template exists
        $templateKey = TemplateKey::fromString($request->templateId);
        $template = $this->templateRepository->findByKey($templateKey);

        if ($template === null) {
            throw new TemplateNotFoundException("Template '{$request->templateId}' not found");
        }

        $suffix = '';

        do {
            $researchId = $this->generateId($request->title . $suffix);
            $suffix = '-' . \date('YmdHis');
        } while ($this->researchRepository->exists(ResearchId::fromString($researchId)));

        $research = new Research(
            id: $researchId,
            name: $request->title,
            description: $request->description,
            template: $request->templateId,
            status: $this->driverConfig->defaultEntryStatus,
            tags: $request->tags,
            entryDirs: !empty($request->entryDirs) ? $request->entryDirs : $this->getDefaultEntryDirs($template),
            memory: $request->memory,
        );

        $this->researchRepository->save($research);
        $this->logger->debug('Created research', ['id' => $researchId, 'name' => $request->title]);

        return $research;
    }

    public function updateResearch(ResearchId $researchId, ResearchUpdateRequest $request): Research
    {
        $research = $this->researchRepository->findById($researchId);
        if ($research === null) {
            throw new ResearchNotFoundException("Research '{$researchId->value}' not found");
        }

        if (!$request->hasUpdates()) {
            return $research;
        }

        $updated = $research->withUpdates(
            name: $request->title,
            description: $request->description,
            status: $request->status,
            tags: $request->tags,
            entryDirs: $request->entryDirs,
            memory: \array_map(
                static fn(ResearchMemory $memory): string => $memory->record,
                $request->memory,
            ),
        );

        $this->researchRepository->save($updated);
        $this->logger->debug('Updated research', ['id' => $researchId->value]);

        return $updated;
    }

    public function deleteResearch(ResearchId $researchId): bool
    {
        if (!$this->researchRepository->exists($researchId)) {
            return false;
        }

        $deleted = $this->researchRepository->delete($researchId);
        if ($deleted) {
            $this->logger->debug('Deleted research', ['id' => $researchId->value]);
        }

        return $deleted;
    }

    public function createEntry(ResearchId $researchId, EntryCreateRequest $request): Entry
    {
        // Verify research exists
        $research = $this->researchRepository->findById($researchId);
        if ($research === null) {
            throw new ResearchNotFoundException("Research '{$researchId->value}' not found");
        }

        // Get template for validation and key resolution
        $templateKey = TemplateKey::fromString($research->template);
        $template = $this->templateRepository->findByKey($templateKey);
        if ($template === null) {
            throw new TemplateNotFoundException("Template '{$research->template}' not found");
        }

        // Resolve display names to internal keys
        $resolvedRequest = $this->resolveEntryCreateRequestKeys($request, $template);

        // Validate resolved request against template
        $this->validateEntryAgainstTemplate($template, $resolvedRequest);

        // Generate entry ID and create entry
        $entryId = $this->generateId('entry_');
        $now = new \DateTime();

        $entry = new Entry(
            entryId: $entryId,
            title: $resolvedRequest->getProcessedTitle(), // Use processed title
            description: $resolvedRequest->getProcessedDescription(), // Use processed description
            entryType: $resolvedRequest->entryType,
            category: $resolvedRequest->category,
            status: $resolvedRequest->status ?? $this->driverConfig->defaultEntryStatus,
            createdAt: $now,
            updatedAt: $now,
            tags: $resolvedRequest->tags,
            content: $resolvedRequest->content,
        );

        $this->entryRepository->save($researchId, $entry);
        $this->logger->debug('Created entry', [
            'research_id' => $researchId->value,
            'entry_id' => $entryId,
            'title' => $entry->title,
        ]);

        return $entry;
    }

    public function updateEntry(ResearchId $researchId, EntryId $entryId, EntryUpdateRequest $request): Entry
    {
        $entry = $this->entryRepository->findById($researchId, $entryId);
        if ($entry === null) {
            throw new EntryNotFoundException("Entry '{$entryId->value}' not found in research '{$researchId->value}'");
        }

        if (!$request->hasUpdates()) {
            return $entry;
        }

        // Resolve status if provided
        $resolvedRequest = $request;
        if ($request->status !== null) {
            $research = $this->researchRepository->findById($researchId);
            if ($research !== null) {
                $templateKey = TemplateKey::fromString($research->template);
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
            description: $resolvedRequest->description,
            status: $resolvedRequest->status,
            tags: $resolvedRequest->tags,
            content: $finalContent, // Use processed content with text replacement
        );

        $this->entryRepository->save($researchId, $updatedEntry);
        $this->logger->debug('Updated entry', [
            'research_id' => $researchId->value,
            'entry_id' => $entryId->value,
        ]);

        return $updatedEntry;
    }

    public function deleteEntry(ResearchId $researchId, EntryId $entryId): bool
    {
        if (!$this->entryRepository->exists($researchId, $entryId)) {
            return false;
        }

        $deleted = $this->entryRepository->delete($researchId, $entryId);
        if ($deleted) {
            $this->logger->debug('Deleted entry', [
                'research_id' => $researchId->value,
                'entry_id' => $entryId->value,
            ]);
        }

        return $deleted;
    }

    /**
     * Generate unique ID for entities
     */
    private function generateId(string $prefix = ''): string
    {
        return $this->slugify->slugify($prefix);
    }

    /**
     * Get default entry directories from template
     */
    private function getDefaultEntryDirs(Template $template): array
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
        Template $template,
    ): EntryCreateRequest {
        // Resolve category
        $resolvedCategory = $this->resolveCategoryKey($template, $request->category);
        if ($resolvedCategory === null) {
            throw new \InvalidArgumentException(
                "Category '{$request->category}' not found in template '{$template->key}'",
            );
        }

        // Resolve entry type
        $resolvedEntryType = $this->resolveEntryTypeKey($template, $request->entryType);
        if ($resolvedEntryType === null) {
            throw new \InvalidArgumentException(
                "Entry type '{$request->entryType}' not found in template '{$template->key}'",
            );
        }

        // Resolve status if provided
        $resolvedStatus = null;
        if ($request->status !== null) {
            $resolvedStatus = $this->resolveStatusForEntryType($template, $resolvedEntryType, $request->status);
            if ($resolvedStatus === null) {
                throw new \InvalidArgumentException(
                    "Status '{$request->status}' not found for entry type '{$resolvedEntryType}' in template '{$template->key}'",
                );
            }
        }

        return $request->withResolvedKeys($resolvedCategory, $resolvedEntryType, $resolvedStatus);
    }

    /**
     * Validate entry request against research template
     */
    private function validateEntryAgainstTemplate(
        Template $template,
        EntryCreateRequest $request,
    ): void {
        // Validate category exists
        if (!$template->hasCategory($request->category)) {
            throw new \InvalidArgumentException(
                "Category '{$request->category}' not found in template '{$template->key}'",
            );
        }

        // Validate entry type exists
        if (!$template->hasEntryType($request->entryType)) {
            throw new \InvalidArgumentException(
                "Entry type '{$request->entryType}' not found in template '{$template->key}'",
            );
        }

        // Validate entry type is allowed in category
        if (!$template->validateEntryInCategory($request->category, $request->entryType)) {
            throw new \InvalidArgumentException(
                "Entry type '{$request->entryType}' is not allowed in category '{$request->category}'",
            );
        }

        // Validate status if provided
        if ($request->status !== null) {
            $entryType = $template->getEntryType($request->entryType);
            if ($entryType !== null && !$entryType->hasStatus($request->status)) {
                throw new \InvalidArgumentException(
                    "Status '{$request->status}' is not valid for entry type '{$request->entryType}'",
                );
            }
        }
    }

    /**
     * Resolve category display name to internal key
     */
    private function resolveCategoryKey(
        Template $template,
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
        Template $template,
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
        Template $template,
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
