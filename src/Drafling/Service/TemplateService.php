<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Service;

use Butschster\ContextGenerator\Drafling\Domain\Model\Template;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;
use Butschster\ContextGenerator\Drafling\Repository\TemplateRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Template service implementation providing display name resolution and template operations
 */
final readonly class TemplateService implements TemplateServiceInterface
{
    public function __construct(
        private TemplateRepositoryInterface $templateRepository,
        private ?LoggerInterface $logger = null,
    ) {}

    #[\Override]
    public function getAllTemplates(): array
    {
        return $this->templateRepository->findAll();
    }

    #[\Override]
    public function getTemplate(TemplateKey $key): ?Template
    {
        return $this->templateRepository->findByKey($key);
    }

    #[\Override]
    public function templateExists(TemplateKey $key): bool
    {
        return $this->templateRepository->exists($key);
    }

    #[\Override]
    public function resolveCategoryKey(Template $template, string $displayNameOrKey): ?string
    {
        foreach ($template->categories as $category) {
            if ($category->name === $displayNameOrKey || $category->displayName === $displayNameOrKey) {
                $this->logger?->debug('Resolved category key', [
                    'input' => $displayNameOrKey,
                    'resolved' => $category->name,
                    'template' => $template->key,
                ]);
                return $category->name;
            }
        }

        $this->logger?->warning('Could not resolve category key', [
            'input' => $displayNameOrKey,
            'template' => $template->key,
            'available_categories' => \array_map(static fn($cat) => [
                'name' => $cat->name,
                'display_name' => $cat->displayName,
            ], $template->categories),
        ]);

        return null;
    }

    #[\Override]
    public function resolveEntryTypeKey(Template $template, string $displayNameOrKey): ?string
    {
        foreach ($template->entryTypes as $entryType) {
            if ($entryType->key === $displayNameOrKey || $entryType->displayName === $displayNameOrKey) {
                $this->logger?->debug('Resolved entry type key', [
                    'input' => $displayNameOrKey,
                    'resolved' => $entryType->key,
                    'template' => $template->key,
                ]);
                return $entryType->key;
            }
        }

        $this->logger?->warning('Could not resolve entry type key', [
            'input' => $displayNameOrKey,
            'template' => $template->key,
            'available_entry_types' => \array_map(static fn($type) => [
                'key' => $type->key,
                'display_name' => $type->displayName,
            ], $template->entryTypes),
        ]);

        return null;
    }

    #[\Override]
    public function resolveStatusValue(Template $template, string $entryTypeKey, string $displayNameOrValue): ?string
    {
        $entryType = $this->getEntryTypeByKey($template, $entryTypeKey);
        if ($entryType === null) {
            $this->logger?->error('Entry type not found for status resolution', [
                'entry_type_key' => $entryTypeKey,
                'template' => $template->key,
            ]);
            return null;
        }

        foreach ($entryType->statuses as $status) {
            if ($status->value === $displayNameOrValue || $status->displayName === $displayNameOrValue) {
                $this->logger?->debug('Resolved status value', [
                    'input' => $displayNameOrValue,
                    'resolved' => $status->value,
                    'entry_type' => $entryTypeKey,
                    'template' => $template->key,
                ]);
                return $status->value;
            }
        }

        $this->logger?->warning('Could not resolve status value', [
            'input' => $displayNameOrValue,
            'entry_type' => $entryTypeKey,
            'template' => $template->key,
            'available_statuses' => \array_map(static fn($status) => [
                'value' => $status->value,
                'display_name' => $status->displayName,
            ], $entryType->statuses),
        ]);

        return null;
    }

    #[\Override]
    public function getAvailableStatuses(Template $template, string $entryTypeKey): array
    {
        $entryType = $this->getEntryTypeByKey($template, $entryTypeKey);
        if ($entryType === null) {
            return [];
        }

        return \array_map(static fn($status) => $status->value, $entryType->statuses);
    }

    #[\Override]
    public function refreshTemplates(): void
    {
        $this->templateRepository->refresh();
        $this->logger?->info('Templates refreshed from storage');
    }

    /**
     * Get entry type from template by key
     */
    private function getEntryTypeByKey(Template $template, string $key): ?\Butschster\ContextGenerator\Drafling\Domain\Model\EntryType
    {
        foreach ($template->entryTypes as $entryType) {
            if ($entryType->key === $key) {
                return $entryType;
            }
        }
        return null;
    }
}
