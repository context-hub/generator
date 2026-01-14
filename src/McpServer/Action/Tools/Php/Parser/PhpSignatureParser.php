<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Php\Parser;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Tools\Php\Result\FileReference;
use Butschster\ContextGenerator\McpServer\Action\Tools\Php\Result\PhpSignature;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\Parser;
use Spiral\Files\FilesInterface;

/**
 * Parses PHP files into signature representation with relationships.
 */
final class PhpSignatureParser
{
    private ?Parser $parser = null;

    public function __construct(
        private readonly FilesInterface $files,
        private readonly DirectoriesInterface $dirs,
        private readonly NamespaceResolver $namespaceResolver,
    ) {}

    public function parse(string $relativePath, bool $showPrivate = false): PhpSignature
    {
        $fullPath = (string) $this->dirs->getRootPath()->join($relativePath);

        if (!$this->files->exists($fullPath)) {
            return PhpSignature::error($relativePath, "File not found: {$relativePath}");
        }

        try {
            $code = $this->files->read($fullPath);
            $ast = $this->getParser()->parse($code);

            if ($ast === null) {
                return PhpSignature::error($relativePath, 'Failed to parse PHP file');
            }

            return $this->extractSignature($relativePath, $ast, $showPrivate);
        } catch (\Throwable $e) {
            return PhpSignature::error($relativePath, "Parse error: {$e->getMessage()}");
        }
    }

    private function extractSignature(string $path, array $ast, bool $showPrivate): PhpSignature
    {
        $visitor = new class($showPrivate) extends NodeVisitorAbstract {
            public ?string $namespace = null;
            public ?string $name = null;
            public ?string $type = null;
            public ?string $extends = null;

            /** @var string[] */
            public array $implements = [];

            /** @var string[] */
            public array $uses = [];

            /** @var array<string, string> */
            public array $useStatements = [];

            /** @var string[] */
            public array $attributes = [];

            /** @var array<array{name: string, visibility: string, type: ?string, default: bool, readonly?: bool, static?: bool}> */
            public array $properties = [];

            /** @var array<array{name: string, visibility: string, params: string, returnType: ?string, isAbstract: bool, isStatic: bool}> */
            public array $methods = [];

            /** @var string[] All referenced FQCNs */
            public array $referencedTypes = [];

            public function __construct(private readonly bool $showPrivate) {}

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Stmt\Namespace_) {
                    $this->namespace = $node->name?->toString();
                }

                if ($node instanceof Stmt\Use_) {
                    foreach ($node->uses as $use) {
                        $fqcn = $use->name->toString();
                        $alias = $use->alias?->toString() ?? $use->name->getLast();
                        $this->useStatements[$alias] = $fqcn;
                        $this->referencedTypes[] = $fqcn;
                    }
                }

                if ($node instanceof Stmt\Class_) {
                    $this->name = $node->name?->toString();
                    $this->type = $this->resolveClassType($node);

                    if ($node->extends !== null) {
                        $this->extends = $node->extends->toString();
                        $this->referencedTypes[] = $this->resolveType($node->extends->toString());
                    }

                    foreach ($node->implements as $interface) {
                        $name = $interface->toString();
                        $this->implements[] = $name;
                        $this->referencedTypes[] = $this->resolveType($name);
                    }

                    $this->extractTraitUses($node->stmts);
                    $this->extractAttributes($node->attrGroups);
                    $this->extractProperties($node->stmts);
                    $this->extractMethods($node->stmts);

                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof Stmt\Interface_) {
                    $this->name = $node->name?->toString();
                    $this->type = 'interface';

                    foreach ($node->extends as $extend) {
                        $name = $extend->toString();
                        $this->implements[] = $name;
                        $this->referencedTypes[] = $this->resolveType($name);
                    }

                    $this->extractAttributes($node->attrGroups);
                    $this->extractMethods($node->stmts);

                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof Stmt\Trait_) {
                    $this->name = $node->name?->toString();
                    $this->type = 'trait';

                    $this->extractTraitUses($node->stmts);
                    $this->extractAttributes($node->attrGroups);
                    $this->extractProperties($node->stmts);
                    $this->extractMethods($node->stmts);

                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof Stmt\Enum_) {
                    $this->name = $node->name?->toString();
                    $this->type = 'enum';

                    foreach ($node->implements as $interface) {
                        $name = $interface->toString();
                        $this->implements[] = $name;
                        $this->referencedTypes[] = $this->resolveType($name);
                    }

                    $this->extractTraitUses($node->stmts);
                    $this->extractAttributes($node->attrGroups);
                    $this->extractMethods($node->stmts);

                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }

                return null;
            }

            private function resolveClassType(Stmt\Class_ $node): string
            {
                if ($node->isAbstract()) {
                    return 'abstract class';
                }
                if ($node->isFinal()) {
                    return 'final class';
                }
                if ($node->isReadonly()) {
                    return 'readonly class';
                }

                return 'class';
            }

            /**
             * @param Node\AttributeGroup[] $attrGroups
             */
            private function extractAttributes(array $attrGroups): void
            {
                foreach ($attrGroups as $group) {
                    foreach ($group->attrs as $attr) {
                        $name = $attr->name->toString();
                        $this->attributes[] = $name;
                        $this->referencedTypes[] = $this->resolveType($name);
                    }
                }
            }

            /**
             * @param Stmt[] $stmts
             */
            private function extractTraitUses(array $stmts): void
            {
                foreach ($stmts as $stmt) {
                    if ($stmt instanceof Stmt\TraitUse) {
                        foreach ($stmt->traits as $trait) {
                            $name = $trait->toString();
                            $this->uses[] = $name;
                            $this->referencedTypes[] = $this->resolveType($name);
                        }
                    }
                }
            }

            /**
             * @param Stmt[] $stmts
             */
            private function extractProperties(array $stmts): void
            {
                foreach ($stmts as $stmt) {
                    if ($stmt instanceof Stmt\Property) {
                        $visibility = $this->getVisibility($stmt);

                        if (!$this->showPrivate && $visibility === 'private') {
                            continue;
                        }

                        $type = $stmt->type !== null ? $this->nodeToTypeString($stmt->type) : null;

                        if ($type !== null) {
                            $this->collectTypesFromNode($stmt->type);
                        }

                        foreach ($stmt->props as $prop) {
                            $this->properties[] = [
                                'name' => $prop->name->toString(),
                                'visibility' => $visibility,
                                'type' => $type,
                                'default' => $prop->default !== null,
                                'readonly' => $stmt->isReadonly(),
                                'static' => $stmt->isStatic(),
                            ];
                        }
                    }
                }
            }

            /**
             * @param Stmt[] $stmts
             */
            private function extractMethods(array $stmts): void
            {
                foreach ($stmts as $stmt) {
                    if ($stmt instanceof Stmt\ClassMethod) {
                        $visibility = $this->getMethodVisibility($stmt);

                        if (!$this->showPrivate && $visibility === 'private') {
                            continue;
                        }

                        $params = $this->formatParams($stmt->params);
                        $returnType = $stmt->returnType !== null
                            ? $this->nodeToTypeString($stmt->returnType)
                            : null;

                        if ($stmt->returnType !== null) {
                            $this->collectTypesFromNode($stmt->returnType);
                        }

                        $this->methods[] = [
                            'name' => $stmt->name->toString(),
                            'visibility' => $visibility,
                            'params' => $params,
                            'returnType' => $returnType,
                            'isAbstract' => $stmt->isAbstract(),
                            'isStatic' => $stmt->isStatic(),
                        ];
                    }
                }
            }

            /**
             * @param Node\Param[] $params
             */
            private function formatParams(array $params): string
            {
                $parts = [];

                foreach ($params as $param) {
                    $part = '';

                    // Visibility (for promoted properties)
                    if ($param->flags !== 0) {
                        if ($param->flags & Stmt\Class_::MODIFIER_PUBLIC) {
                            $part .= 'public ';
                        } elseif ($param->flags & Stmt\Class_::MODIFIER_PROTECTED) {
                            $part .= 'protected ';
                        } elseif ($param->flags & Stmt\Class_::MODIFIER_PRIVATE) {
                            if (!$this->showPrivate) {
                                continue;
                            }
                            $part .= 'private ';
                        }

                        if ($param->flags & Stmt\Class_::MODIFIER_READONLY) {
                            $part .= 'readonly ';
                        }
                    }

                    // Type
                    if ($param->type !== null) {
                        $part .= $this->nodeToTypeString($param->type) . ' ';
                        $this->collectTypesFromNode($param->type);
                    }

                    // Variadic
                    if ($param->variadic) {
                        $part .= '...';
                    }

                    // Reference
                    if ($param->byRef) {
                        $part .= '&';
                    }

                    // Name
                    if ($param->var instanceof \PhpParser\Node\Expr\Variable && \is_string($param->var->name)) {
                        $part .= '$' . $param->var->name;
                    }

                    $parts[] = $part;
                }

                return \implode(', ', $parts);
            }

            private function getVisibility(Stmt\Property $prop): string
            {
                if ($prop->isPublic()) {
                    return 'public';
                }
                if ($prop->isProtected()) {
                    return 'protected';
                }

                return 'private';
            }

            private function getMethodVisibility(Stmt\ClassMethod $method): string
            {
                if ($method->isPublic()) {
                    return 'public';
                }
                if ($method->isProtected()) {
                    return 'protected';
                }

                return 'private';
            }

            private function nodeToTypeString(Node $node): string
            {
                if ($node instanceof Node\Identifier) {
                    return $node->toString();
                }

                if ($node instanceof Node\Name) {
                    return $node->toString();
                }

                if ($node instanceof Node\NullableType) {
                    return '?' . $this->nodeToTypeString($node->type);
                }

                if ($node instanceof Node\UnionType) {
                    $types = \array_map($this->nodeToTypeString(...), $node->types);
                    return \implode('|', $types);
                }

                if ($node instanceof Node\IntersectionType) {
                    $types = \array_map($this->nodeToTypeString(...), $node->types);
                    return \implode('&', $types);
                }

                return 'mixed';
            }

            private function collectTypesFromNode(Node $node): void
            {
                if ($node instanceof Node\Name && !$node->isSpecialClassName()) {
                    $name = $node->toString();
                    // Skip built-in types
                    if ($this->isBuiltInType($name)) {
                        return;
                    }
                    $this->referencedTypes[] = $this->resolveType($name);
                }

                if ($node instanceof Node\NullableType) {
                    $this->collectTypesFromNode($node->type);
                }

                if ($node instanceof Node\UnionType || $node instanceof Node\IntersectionType) {
                    foreach ($node->types as $type) {
                        $this->collectTypesFromNode($type);
                    }
                }
            }

            private function isBuiltInType(string $name): bool
            {
                $lower = \strtolower($name);

                // Scalar types and special types
                $scalarTypes = [
                    'int', 'string', 'bool', 'float', 'array', 'object',
                    'mixed', 'void', 'null', 'false', 'true', 'never',
                    'iterable', 'callable', 'self', 'static', 'parent', 'resource',
                ];

                if (\in_array($lower, $scalarTypes, true)) {
                    return true;
                }

                // PHP built-in interfaces and classes (case-insensitive check)
                $builtInClasses = [
                    'traversable', 'iterator', 'iteratoraggregate', 'arrayaccess',
                    'serializable', 'closure', 'generator', 'countable',
                    'throwable', 'exception', 'error', 'errorexception',
                    'jsonserializable', 'stringable', 'attribute', 'fiber',
                    'weakreference', 'weakmap', 'datetime', 'datetimeimmutable',
                    'datetimeinterface', 'datetimezone', 'dateinterval', 'dateperiod',
                    'splfileinfo', 'splfileobject', 'stdclass', 'arrayobject',
                    'arrayiterator', 'recursivearrayiterator', 'splobjectstorage',
                ];

                return \in_array($lower, $builtInClasses, true);
            }

            private function resolveType(string $name): string
            {
                // Already fully qualified
                if (\str_starts_with($name, '\\')) {
                    return \ltrim($name, '\\');
                }

                // Check use statements
                $parts = \explode('\\', $name);
                $first = $parts[0];

                if (isset($this->useStatements[$first])) {
                    if (\count($parts) === 1) {
                        return $this->useStatements[$first];
                    }

                    \array_shift($parts);
                    return $this->useStatements[$first] . '\\' . \implode('\\', $parts);
                }

                // Same namespace
                if ($this->namespace !== null) {
                    return $this->namespace . '\\' . $name;
                }

                return $name;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        // Build file references
        $references = [];
        $seen = [];

        foreach (\array_unique($visitor->referencedTypes) as $fqcn) {
            if (isset($seen[$fqcn])) {
                continue;
            }
            $seen[$fqcn] = true;

            $type = $this->determineReferenceType($fqcn, $visitor);

            $resolvedPath = $this->namespaceResolver->resolve($fqcn);
            $references[] = FileReference::local($fqcn, $resolvedPath, $type);
        }

        return new PhpSignature(
            path: $path,
            namespace: $visitor->namespace,
            name: $visitor->name,
            type: $visitor->type,
            extends: $visitor->extends,
            implements: $visitor->implements,
            uses: $visitor->uses,
            useStatements: $visitor->useStatements,
            attributes: $visitor->attributes,
            properties: $visitor->properties,
            methods: $visitor->methods,
            references: $references,
        );
    }

    private function determineReferenceType(string $fqcn, object $visitor): string
    {
        if ($visitor->extends !== null && \str_ends_with($fqcn, $visitor->extends)) {
            return 'extends';
        }

        foreach ($visitor->implements as $impl) {
            if (\str_ends_with($fqcn, $impl)) {
                return 'implements';
            }
        }

        foreach ($visitor->uses as $use) {
            if (\str_ends_with($fqcn, $use)) {
                return 'trait';
            }
        }

        foreach ($visitor->attributes as $attr) {
            if (\str_ends_with($fqcn, $attr)) {
                return 'attribute';
            }
        }

        return 'use';
    }

    private function getParser(): Parser
    {
        return $this->parser ??= (new ParserFactory())->createForNewestSupportedVersion();
    }
}
