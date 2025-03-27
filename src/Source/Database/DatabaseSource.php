<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Database;

use Butschster\ContextGenerator\Modifier\Modifier;
use Butschster\ContextGenerator\Modifier\ModifiersApplier;
use Butschster\ContextGenerator\SourceInterface;
use Butschster\ContextGenerator\SourceParserInterface;

/**
 * Source for extracting database schema information using Cycle ORM
 */
final class DatabaseSource implements SourceInterface, \JsonSerializable
{
    /**
     * @var array<string>
     */
    private array $includeSchemas = [];

    /**
     * @var array<string>
     */
    private array $includeTables = [];

    /**
     * @var array<string>
     */
    private array $excludeTables = [];

    /**
     * @var array<string>
     */
    private array $tags = [];

    /**
     * @var array<Modifier>
     */
    private array $modifiers = [];

    /**
     * @param array<string, mixed> $connection Database connection configuration
     * @param string|null $description Human-readable description
     * @param bool $showIndexes Whether to include indexes in schema output
     * @param bool $showConstraints Whether to include constraints in schema output
     * @param bool $showRelationships Whether to include relationships in schema output
     * @param string $format Output format (markdown, etc.)
     */
    public function __construct(
        private readonly array $connection,
        private readonly ?string $description = null,
        private readonly bool $showIndexes = true,
        private readonly bool $showConstraints = true,
        private readonly bool $showRelationships = true,
        private readonly string $format = 'markdown',
    ) {}

    /**
     * Create a database source from an array configuration
     *
     * @param array<string, mixed> $data Configuration data
     * @throws \InvalidArgumentException If connection configuration is missing
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['connection']) || empty($data['connection'])) {
            throw new \InvalidArgumentException('Database connection configuration is required');
        }

        $source = new self(
            connection: $data['connection'],
            description: $data['description'] ?? null,
            showIndexes: $data['showIndexes'] ?? true,
            showConstraints: $data['showConstraints'] ?? true,
            showRelationships: $data['showRelationships'] ?? true,
            format: $data['format'] ?? 'markdown',
        );

        // Process include schemas
        if (isset($data['includeSchemas'])) {
            $includeSchemas = \is_array($data['includeSchemas'])
                ? $data['includeSchemas']
                : [$data['includeSchemas']];

            $source->includeSchemas(...$includeSchemas);
        }

        // Process include tables
        if (isset($data['includeTables'])) {
            $includeTables = \is_array($data['includeTables'])
                ? $data['includeTables']
                : [$data['includeTables']];

            $source->includeTables(...$includeTables);
        }

        // Process exclude tables
        if (isset($data['excludeTables'])) {
            $excludeTables = \is_array($data['excludeTables'])
                ? $data['excludeTables']
                : [$data['excludeTables']];

            $source->excludeTables(...$excludeTables);
        }

        // Process tags
        if (isset($data['tags']) && \is_array($data['tags'])) {
            $source->addTag(...$data['tags']);
        }

        // Process modifiers
        if (isset($data['modifiers']) && \is_array($data['modifiers'])) {
            $source->modifiers = $data['modifiers'];
        }

        return $source;
    }

    /**
     * Set schemas to include in output
     *
     * @param string ...$schemaNames Names of schemas to include
     */
    public function includeSchemas(string ...$schemaNames): self
    {
        $this->includeSchemas = \array_values(\array_filter($schemaNames));
        return $this;
    }

    /**
     * Set tables to include in output
     *
     * @param string ...$tableNames Names of tables to include
     */
    public function includeTables(string ...$tableNames): self
    {
        $this->includeTables = \array_values(\array_filter($tableNames));
        return $this;
    }

    /**
     * Set tables to exclude from output
     *
     * @param string ...$tableNames Names of tables to exclude
     */
    public function excludeTables(string ...$tableNames): self
    {
        $this->excludeTables = \array_values(\array_filter($tableNames));
        return $this;
    }

    /**
     * Add tags to this source
     *
     * @param string ...$tags Tags to add
     */
    public function addTag(string ...$tags): self
    {
        $this->tags = \array_values(\array_unique([...$this->tags, ...$tags]));
        return $this;
    }

    /**
     * Get all tags for this source
     *
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Add modifiers to this source
     *
     * @param Modifier ...$modifiers Modifiers to add
     */
    public function addModifier(Modifier ...$modifiers): self
    {
        $this->modifiers = [...$this->modifiers, ...$modifiers];
        return $this;
    }

    /**
     * Get all modifiers for this source
     *
     * @return array<Modifier>
     */
    public function getModifiers(): array
    {
        return \array_values($this->modifiers);
    }

    /**
     * Check if source has a description
     *
     */
    public function hasDescription(): bool
    {
        return !empty($this->description);
    }

    /**
     * Get source description
     *
     */
    public function getDescription(): string
    {
        return $this->description ?? 'Database Schema';
    }

    /**
     * Parse the source content
     *
     * @param SourceParserInterface $parser The source parser
     * @param ModifiersApplier $modifiersApplier The modifiers applier
     * @return string The parsed content
     */
    public function parseContent(SourceParserInterface $parser, ModifiersApplier $modifiersApplier): string
    {
        $fetcher = new DatabaseSourceFetcher();
        $content = $fetcher->fetch($this, $modifiersApplier);

        return $content;
    }

    /**
     * Get database connection configuration
     *
     * @return array<string, mixed>
     */
    public function getConnection(): array
    {
        return $this->connection;
    }

    /**
     * Get schemas to include in output
     *
     * @return array<string>
     */
    public function getIncludeSchemas(): array
    {
        return $this->includeSchemas;
    }

    /**
     * Get tables to include in output
     *
     * @return array<string>
     */
    public function getIncludeTables(): array
    {
        return $this->includeTables;
    }

    /**
     * Get tables to exclude from output
     *
     * @return array<string>
     */
    public function getExcludeTables(): array
    {
        return $this->excludeTables;
    }

    /**
     * Check if indexes should be included in output
     *
     */
    public function shouldShowIndexes(): bool
    {
        return $this->showIndexes;
    }

    /**
     * Check if constraints should be included in output
     *
     */
    public function shouldShowConstraints(): bool
    {
        return $this->showConstraints;
    }

    /**
     * Check if relationships should be included in output
     *
     */
    public function shouldShowRelationships(): bool
    {
        return $this->showRelationships;
    }

    /**
     * Get output format
     *
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Convert source to JSON serializable array
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return \array_filter([
            'type' => 'database',
            'connection' => $this->connection,
            'description' => $this->description,
            'includeSchemas' => $this->includeSchemas,
            'includeTables' => $this->includeTables,
            'excludeTables' => $this->excludeTables,
            'showIndexes' => $this->showIndexes,
            'showConstraints' => $this->showConstraints,
            'showRelationships' => $this->showRelationships,
            'format' => $this->format,
            'tags' => $this->tags,
            'modifiers' => $this->modifiers,
        ], static fn($value) => $value !== null && $value !== []);
    }
}
