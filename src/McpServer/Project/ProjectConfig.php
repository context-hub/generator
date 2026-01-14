<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Project;

/**
 * Configuration for a whitelisted project.
 *
 * Represents a project that has been configured in context.yaml
 * and is available for use with the `project` parameter in tools.
 */
final readonly class ProjectConfig implements \JsonSerializable
{
    public function __construct(
        /**
         * Project alias (must match an alias in .project-state.json).
         */
        public string $name,
        /**
         * Optional description to help AI understand the project's purpose.
         */
        public ?string $description = null,
    ) {}

    /**
     * Create a ProjectConfig from an array (typically from YAML parsing).
     *
     * @param array{name?: string, description?: string|null} $data
     */
    public static function fromArray(array $data): ?self
    {
        $name = $data['name'] ?? null;

        if ($name === null || $name === '') {
            return null;
        }

        return new self(
            name: $name,
            description: $data['description'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return \array_filter([
            'name' => $this->name,
            'description' => $this->description,
        ], static fn($value) => $value !== null);
    }
}
