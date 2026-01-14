<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Php;

use Butschster\ContextGenerator\McpServer\Action\Tools\Php\Parser\PhpSignatureParser;
use Butschster\ContextGenerator\McpServer\Action\Tools\Php\Result\FileReference;
use Butschster\ContextGenerator\McpServer\Action\Tools\Php\Result\PhpSignature;

/**
 * Orchestrates PHP signature parsing with depth traversal.
 */
final readonly class PhpStructureHandler
{
    public function __construct(
        private PhpSignatureParser $parser,
    ) {}

    /**
     * Parse PHP file and follow relationships up to specified depth.
     *
     * @return array<array{signature: PhpSignature, depth: int}> Array of signatures with depth info
     */
    public function analyze(string $path, int $depth, bool $showPrivate): array
    {
        $results = [];
        $visited = [];
        $queue = [['path' => $path, 'depth' => 0]];

        while (!empty($queue)) {
            $item = \array_shift($queue);
            $currentPath = $item['path'];
            $currentDepth = $item['depth'];

            // Skip if already visited
            if (isset($visited[$currentPath])) {
                continue;
            }
            $visited[$currentPath] = true;

            // Parse file
            $signature = $this->parser->parse($currentPath, $showPrivate);
            $results[] = ['signature' => $signature, 'depth' => $currentDepth];

            // Don't follow links if at max depth or has error
            if ($currentDepth >= $depth || $signature->hasError()) {
                continue;
            }

            // Queue local references for next depth level
            foreach ($signature->getLocalReferences() as $ref) {
                if ($ref->resolvedPath !== null && !isset($visited[$ref->resolvedPath])) {
                    $queue[] = ['path' => $ref->resolvedPath, 'depth' => $currentDepth + 1];
                }
            }
        }

        return $results;
    }

    /**
     * Format results as PHP-like signature output.
     *
     * @param array<array{signature: PhpSignature, depth: int}> $results
     */
    public function format(array $results): string
    {
        $output = [];

        foreach ($results as $i => $item) {
            $signature = $item['signature'];
            $depth = $item['depth'];

            if ($i > 0) {
                $output[] = '';
                $output[] = '// ' . \str_repeat('─', 60);
                $output[] = \sprintf('// Linked (depth %d): %s', $depth, $signature->path);
                $output[] = '';
            } else {
                $output[] = '// ' . $signature->path;
            }

            if ($signature->hasError()) {
                $output[] = '// ERROR: ' . $signature->error;
                continue;
            }

            $output[] = $this->formatSignature($signature);
        }

        return \implode("\n", $output);
    }

    private function formatSignature(PhpSignature $signature): string
    {
        $lines = [];

        // Namespace
        if ($signature->namespace !== null) {
            $lines[] = \sprintf('namespace %s;', $signature->namespace);
            $lines[] = '';
        }

        // Use statements with path comments
        $usesByPath = $this->groupUseStatements($signature);
        foreach ($usesByPath as $fqcn => $pathComment) {
            $lines[] = \sprintf('use %s;  // → %s', $fqcn, $pathComment);
        }

        if (!empty($usesByPath)) {
            $lines[] = '';
        }

        // Attributes
        foreach ($signature->attributes as $attr) {
            $ref = $this->findReference($signature, $attr);
            $comment = $ref !== null ? '  // → ' . $ref->formatComment() : '';
            $lines[] = \sprintf('#[%s]%s', $this->shortName($attr), $comment);
        }

        // Class/interface/trait/enum declaration
        if ($signature->name !== null) {
            $declaration = $this->formatDeclaration($signature);
            $lines[] = $declaration;
            $lines[] = '{';

            // Properties
            foreach ($signature->properties as $prop) {
                $lines[] = $this->formatProperty($prop);
            }

            if (!empty($signature->properties) && !empty($signature->methods)) {
                $lines[] = '';
            }

            // Methods
            foreach ($signature->methods as $method) {
                $lines[] = $this->formatMethod($method, $signature->type === 'interface');
            }

            $lines[] = '}';
        }

        return \implode("\n", $lines);
    }

    /**
     * @return array<string, string> [fqcn => path comment]
     */
    private function groupUseStatements(PhpSignature $signature): array
    {
        $result = [];

        foreach ($signature->useStatements as $fqcn) {
            $ref = $this->findReference($signature, $fqcn);
            $pathComment = $ref !== null ? $ref->formatComment() : '(unknown)';
            $result[$fqcn] = $pathComment;
        }

        return $result;
    }

    private function formatDeclaration(PhpSignature $signature): string
    {
        $parts = [];

        // Type (class, interface, trait, enum)
        $parts[] = $signature->type ?? 'class';
        $parts[] = $signature->name;

        // Extends
        if ($signature->extends !== null) {
            $ref = $this->findReferenceByShortName($signature, $signature->extends);
            $comment = $ref !== null ? '  // → ' . $ref->formatComment() : '';
            $parts[] = 'extends ' . $signature->extends . $comment;
        }

        // Implements
        if (!empty($signature->implements)) {
            $implParts = [];
            foreach ($signature->implements as $impl) {
                $ref = $this->findReferenceByShortName($signature, $impl);
                $comment = $ref !== null ? ' /* → ' . $ref->formatComment() . ' */' : '';
                $implParts[] = $impl . $comment;
            }

            $keyword = $signature->type === 'interface' ? 'extends' : 'implements';
            if ($signature->extends === null || $signature->type === 'interface') {
                $parts[] = $keyword . ' ' . \implode(', ', $implParts);
            } else {
                $parts[\count($parts) - 1] .= "\n    " . $keyword . ' ' . \implode(', ', $implParts);
            }
        }

        return \implode(' ', $parts);
    }

    /**
     * @param array{name: string, visibility: string, type: ?string, default: bool, readonly?: bool, static?: bool} $prop
     */
    private function formatProperty(array $prop): string
    {
        $parts = ['   '];

        $parts[] = $prop['visibility'];

        if ($prop['static'] ?? false) {
            $parts[] = 'static';
        }

        if ($prop['readonly'] ?? false) {
            $parts[] = 'readonly';
        }

        if ($prop['type'] !== null) {
            $parts[] = $prop['type'];
        }

        $parts[] = '$' . $prop['name'];

        $line = \implode(' ', $parts) . ';';

        return $line;
    }

    /**
     * @param array{name: string, visibility: string, params: string, returnType: ?string, isAbstract: bool, isStatic: bool} $method
     */
    private function formatMethod(array $method, bool $isInterface): string
    {
        $parts = ['   '];

        if ($method['isAbstract'] && !$isInterface) {
            $parts[] = 'abstract';
        }

        $parts[] = $method['visibility'];

        if ($method['isStatic']) {
            $parts[] = 'static';
        }

        $parts[] = 'function';
        $parts[] = $method['name'] . '(' . $method['params'] . ')';

        if ($method['returnType'] !== null) {
            $parts[] = ': ' . $method['returnType'];
        }

        $line = \implode(' ', $parts);

        // Abstract methods and interface methods have no body
        if ($method['isAbstract'] || $isInterface) {
            return $line . ';';
        }

        return $line . ' {}';
    }

    private function findReference(PhpSignature $signature, string $fqcn): ?FileReference
    {
        foreach ($signature->references as $ref) {
            if ($ref->fqcn === $fqcn) {
                return $ref;
            }
        }

        return null;
    }

    private function findReferenceByShortName(PhpSignature $signature, string $shortName): ?FileReference
    {
        foreach ($signature->references as $ref) {
            if (\str_ends_with($ref->fqcn, '\\' . $shortName) || $ref->fqcn === $shortName) {
                return $ref;
            }
        }

        return null;
    }

    private function shortName(string $fqcn): string
    {
        $parts = \explode('\\', $fqcn);

        return \end($parts);
    }
}
