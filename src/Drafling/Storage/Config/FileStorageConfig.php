<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Storage\Config;

/**
 * Configuration for file-based storage driver
 */
final readonly class FileStorageConfig implements \JsonSerializable
{
    public function __construct(
        public string $basePath,
        public string $templatesPath,
        public string $defaultEntryStatus = 'draft',
        public bool $createDirectoriesOnDemand = true,
        public bool $validateTemplatesOnBoot = true,
        public int $maxFileSize = 10485760, // 10MB
        public array $allowedExtensions = ['md', 'yaml'],
        public string $fileEncoding = 'utf-8',
    ) {}

    /**
     * Create from array configuration
     */
    public static function fromArray(array $config): self
    {
        return new self(
            basePath: $config['base_path'] ?? '.projects',
            templatesPath: $config['templates_path'] ?? '.templates',
            defaultEntryStatus: $config['default_entry_status'] ?? 'draft',
            createDirectoriesOnDemand: $config['create_directories_on_demand'] ?? true,
            validateTemplatesOnBoot: $config['validate_templates_on_boot'] ?? true,
            maxFileSize: $config['max_file_size'] ?? 10485760,
            allowedExtensions: $config['allowed_extensions'] ?? ['md', 'yaml'],
            fileEncoding: $config['file_encoding'] ?? 'utf-8',
        );
    }

    /**
     * Validate configuration values
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->basePath)) {
            $errors[] = 'Base path cannot be empty';
        }

        if (empty($this->templatesPath)) {
            $errors[] = 'Templates path cannot be empty';
        }

        if (empty($this->defaultEntryStatus)) {
            $errors[] = 'Default entry status cannot be empty';
        }

        if ($this->maxFileSize <= 0) {
            $errors[] = 'Max file size must be greater than 0';
        }

        if (empty($this->allowedExtensions)) {
            $errors[] = 'At least one allowed extension must be specified';
        }

        return $errors;
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'base_path' => $this->basePath,
            'templates_path' => $this->templatesPath,
            'default_entry_status' => $this->defaultEntryStatus,
            'create_directories_on_demand' => $this->createDirectoriesOnDemand,
            'validate_templates_on_boot' => $this->validateTemplatesOnBoot,
            'max_file_size' => $this->maxFileSize,
            'allowed_extensions' => $this->allowedExtensions,
            'file_encoding' => $this->fileEncoding,
        ];
    }
}
