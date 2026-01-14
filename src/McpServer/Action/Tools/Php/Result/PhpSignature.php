<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Php\Result;

/**
 * Represents parsed PHP file signature with relationships.
 */
final readonly class PhpSignature
{
    /**
     * @param string $path Relative file path
     * @param string|null $namespace PHP namespace
     * @param string|null $name Class/interface/trait/enum name
     * @param string|null $type Type: class, abstract class, final class, interface, trait, enum
     * @param string|null $extends Parent class FQCN
     * @param string[] $implements Implemented interfaces FQCNs
     * @param string[] $uses Used traits FQCNs
     * @param array<string, string> $useStatements Import statements [alias => FQCN]
     * @param string[] $attributes Class attribute FQCNs
     * @param array<array{name: string, visibility: string, type: ?string, default: bool}> $properties
     * @param array<array{name: string, visibility: string, params: string, returnType: ?string, isAbstract: bool, isStatic: bool}> $methods
     * @param FileReference[] $references All file references
     */
    public function __construct(
        public string $path,
        public ?string $namespace,
        public ?string $name,
        public ?string $type,
        public ?string $extends,
        public array $implements,
        public array $uses,
        public array $useStatements,
        public array $attributes,
        public array $properties,
        public array $methods,
        public array $references,
        public ?string $error = null,
    ) {}

    public static function error(string $path, string $error): self
    {
        return new self(
            path: $path,
            namespace: null,
            name: null,
            type: null,
            extends: null,
            implements: [],
            uses: [],
            useStatements: [],
            attributes: [],
            properties: [],
            methods: [],
            references: [],
            error: $error,
        );
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getFqcn(): ?string
    {
        if ($this->name === null) {
            return null;
        }

        return $this->namespace !== null
            ? $this->namespace . '\\' . $this->name
            : $this->name;
    }

    /**
     * Get local (non-vendor) references for depth traversal.
     *
     * @return FileReference[]
     */
    public function getLocalReferences(): array
    {
        return \array_filter(
            $this->references,
            static fn(FileReference $ref): bool => !$ref->isVendor && $ref->resolvedPath !== null,
        );
    }
}
