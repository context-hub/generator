<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Config;

/**
 * Represents a JSON schema for tool arguments.
 */
final readonly class ToolSchema implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $schema The JSON schema definition
     */
    public function __construct(
        private array $schema,
    ) {}

    /**
     * Creates a ToolSchema from a configuration array.
     *
     * @param array<string, mixed> $config The schema configuration
     * @throws \InvalidArgumentException If the configuration is invalid
     */
    public static function fromArray(array $config): ?self
    {
        if (empty($config)) {
            return new self([]);
        }

        // Validate basic schema structure
        if (!isset($config['type']) || $config['type'] !== 'object') {
            throw new \InvalidArgumentException('Schema must have a "type" property set to "object"');
        }

        return new self($config);
    }

    /**
     * Gets the required properties from the schema.
     *
     * @return array<string> List of required property names
     */
    public function getRequiredProperties(): array
    {
        return $this->schema['required'] ?? [];
    }

    /**
     * Gets all property definitions from the schema.
     *
     * @return array<string, array<string, mixed>> Property definitions
     */
    public function getProperties(): array
    {
        return $this->schema['properties'] ?? [];
    }

    /**
     * Gets the default value for a property if defined in the schema.
     *
     * @param string $propertyName The name of the property
     * @return mixed The default value or null if not defined
     */
    public function getDefaultValue(string $propertyName): mixed
    {
        $properties = $this->getProperties();

        if (!isset($properties[$propertyName])) {
            return null;
        }

        // Return the default value if it exists, otherwise null
        return $properties[$propertyName]['default'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->schema;
    }
}
