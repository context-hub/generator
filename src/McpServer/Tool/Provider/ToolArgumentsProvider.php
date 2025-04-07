<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Provider;

use Butschster\ContextGenerator\Lib\Variable\Provider\VariableProviderInterface;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolSchema;

/**
 * Provider for tool arguments passed during execution.
 * Supports type casting based on schema definition.
 */
final readonly class ToolArgumentsProvider implements VariableProviderInterface
{
    public function __construct(
        private array $arguments,
        private ToolSchema $schema,
    ) {}

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->arguments);
    }

    /**
     * Get a value with proper type casting based on schema (if available).
     * Falls back to default value from schema if property is not provided.
     */
    public function get(string $name): ?string
    {
        // If the argument is not provided, check if there's a default value in the schema
        return $this->castValueFromSchema(
            $name,
            $this->arguments[$name] ?? $this->schema->getDefaultValue($name),
        );
    }

    /**
     * Get all arguments
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        // Start with all explicitly provided arguments
        $result = [];
        $properties = $this->schema->getProperties();

        // First apply any default values for properties not in arguments
        foreach ($properties as $name => $definition) {
            if (!$this->has($name) && isset($definition['default'])) {
                $result[$name] = $this->castValueFromSchema($name, $definition['default']);
            }
        }

        // Then add explicitly provided arguments (these will override defaults)
        foreach ($this->arguments as $name => $value) {
            // Apply type casting to the arguments
            $result[$name] = $this->castValueFromSchema($name, $value);
        }

        return $result;
    }

    /**
     * Cast a value based on schema type definition
     *
     * @param string $name The argument name
     * @param mixed $value The value to cast
     * @return mixed The cast value
     */
    private function castValueFromSchema(string $name, mixed $value): string
    {
        // Return null directly if value is null
        if ($value === null) {
            return 'null';
        }

        $properties = $this->schema->getProperties();
        if (!isset($properties[$name])) {
            return $value;
        }

        $propertyDef = $properties[$name];
        $type = $propertyDef['type'] ?? null;

        if ($type === null) {
            return 'null';
        }

        return match ($type) {
            'string' => $this->castToString($value),
            'number' => $this->castToFloat($value),
            'integer' => $this->castToInt($value),
            'boolean' => $this->castToBool($value),
            'array' => \json_encode($this->castToArray($value)),
            'object' => \json_encode($this->castToObject($value)),
            default => (string) $value,
        };
    }

    /**
     * Cast a value to string
     */
    private function castToString(mixed $value): string
    {
        if (\is_array($value) || \is_object($value)) {
            return \json_encode($value) ?: '';
        }

        return (string) $value;
    }

    /**
     * Cast a value to integer
     */
    private function castToInt(mixed $value): string
    {
        if (\is_string($value) && \trim($value) === '') {
            return '0';
        }

        return (string) $value;
    }

    /**
     * Cast a value to float
     */
    private function castToFloat(mixed $value): string
    {
        if (\is_string($value) && \trim($value) === '') {
            return '0.0';
        }

        return \number_format((float) $value, 2, '.', '');
    }

    /**
     * Cast a value to boolean
     */
    private function castToBool(mixed $value): string
    {
        if (\is_string($value)) {
            $value = \strtolower(\trim($value));
            return ($value === 'true' || $value === '1' || $value === 'yes' || $value === 'y') ? 'true' : 'false';
        }

        return $value ? 'true' : 'false';
    }

    /**
     * Cast a value to array
     */
    private function castToArray(mixed $value): array
    {
        if (\is_string($value)) {
            // Try to parse JSON string
            $decoded = \json_decode($value, true);
            if (\json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                return $decoded;
            }

            // Split by comma if not a valid JSON
            return \array_map('trim', \explode(',', $value));
        }

        if (\is_object($value)) {
            return (array) $value;
        }

        if (!\is_array($value)) {
            return [$value];
        }

        return $value;
    }

    /**
     * Cast a value to object (associative array)
     */
    private function castToObject(mixed $value): array
    {
        if (\is_string($value)) {
            // Try to parse JSON string
            $decoded = \json_decode($value, true);
            if (\json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                return $decoded;
            }

            // Can't convert simple string to object
            return [];
        }

        if (\is_object($value)) {
            return (array) $value;
        }

        if (!\is_array($value)) {
            return [];
        }

        return $value;
    }
}
