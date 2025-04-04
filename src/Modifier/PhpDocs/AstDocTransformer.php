<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\PhpDocs;

use Butschster\ContextGenerator\Modifier\SourceModifierInterface;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\EnumType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Property;
use Nette\PhpGenerator\TraitType;

/**
 * AstDocTransformer - A modifier that transforms PHP code into structured markdown documentation.
 *
 * This modifier uses nette/php-generator to parse PHP code and transform it into a
 * well-formatted markdown document with preserved code blocks for method implementations.
 */
final class AstDocTransformer implements SourceModifierInterface
{
    /**
     * Configuration options for the transformer
     *
     * @var array<string, mixed>
     */
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = \array_merge([
            // Include private methods in documentation
            'include_private_methods' => false,
            // Include protected methods in documentation
            'include_protected_methods' => false,
            // Include private properties in documentation
            'include_private_properties' => false,
            // Include protected properties in documentation
            'include_protected_properties' => false,
            // Include method implementations
            'include_implementations' => false,
            // Include property default values
            'include_property_defaults' => true,
            // Include constants
            'include_constants' => false,
            // Format for code blocks
            'code_block_format' => 'php',
            // Heading level for class names (1-6)
            'class_heading_level' => 2,
            // Whether to extract route annotations
            'extract_routes' => true,
            // Whether to keep doc comments
            'keep_doc_comments' => false,
        ], $config);
    }

    public function getIdentifier(): string
    {
        return 'php-docs';
    }

    public function supports(string $contentType): bool
    {
        return \str_ends_with($contentType, '.php');
    }

    public function modify(string $content, array $context = []): string
    {
        // Merge context configuration with defaults
        $this->config = \array_merge($this->config, $context);

        try {
            $file = PhpFile::fromCode($content);
            $output = '';

            foreach ($file->getNamespaces() as $namespace) {
                $namespaceName = $namespace->getName();

                // Process classes in this namespace
                foreach ($namespace->getClasses() as $class) {
                    $className = $class->getName();
                    $fullClassName = $namespaceName ? "$namespaceName\\$className" : $className;

                    // Process the appropriate type
                    if ($class instanceof InterfaceType) {
                        $output .= $this->processInterface($class, $fullClassName);
                    } elseif ($class instanceof TraitType) {
                        $output .= $this->processTrait($class, $fullClassName);
                    } elseif (PHP_VERSION_ID >= 80100 && $class instanceof EnumType) {
                        $output .= $this->processEnum($class, $fullClassName);
                    } else {
                        $output .= $this->processClass($class, $fullClassName);
                    }
                }
            }

            return $output;
        } catch (\Throwable $e) {
            return "// Error parsing file: {$e->getMessage()}\n";
        }
    }

    /**
     * Process a class and generate documentation
     */
    private function processClass(ClassType $class, string $fullClassName): string
    {
        $headingLevel = $this->config['class_heading_level'];
        $output = \str_repeat('#', $headingLevel) . " Class: $fullClassName\n\n";

        // Add class docblock if available and enabled
        if ($this->config['keep_doc_comments'] && $class->getComment()) {
            $docComment = $this->formatDocComment($class->getComment());
            $output .= "$docComment\n\n";
        }

        // Process class metadata
        $metadata = [];

        if ($class->getExtends()) {
            $metadata[] = "**Extends:** `{$class->getExtends()}`";
        }

        if ($class->getImplements()) {
            $implements = \array_map(static fn($i) => "`$i`", $class->getImplements());
            $metadata[] = "**Implements:** " . \implode(', ', $implements);
        }

        if (!empty($metadata)) {
            $output .= \implode("\n", $metadata) . "\n\n";
        }

        // Process constants if enabled
        if ($this->config['include_constants'] && !empty($class->getConstants())) {
            $output .= "## Constants\n\n";
            foreach ($class->getConstants() as $constant) {
                $output .= $this->processConstant($constant);
            }
        }

        // Process properties
        $properties = $class->getProperties();
        if (!empty($properties)) {
            $output .= "## Properties\n\n";
            foreach ($properties as $property) {
                $output .= $this->processProperty($property);
            }
        }

        // Process methods
        $methods = $class->getMethods();
        if (!empty($methods)) {
            $output .= "## Methods\n\n";
            foreach ($methods as $method) {
                $output .= $this->processMethod($method);
            }
        }

        return $output;
    }

    /**
     * Process an interface and generate documentation
     */
    private function processInterface(InterfaceType $interface, string $fullInterfaceName): string
    {
        $headingLevel = $this->config['class_heading_level'];
        $output = \str_repeat('#', $headingLevel) . " Interface: $fullInterfaceName\n\n";

        // Add interface docblock if available and enabled
        if ($this->config['keep_doc_comments'] && $interface->getComment()) {
            $docComment = $this->formatDocComment($interface->getComment());
            $output .= "$docComment\n\n";
        }

        // Process interface metadata
        if ($interface->getExtends()) {
            $extends = \array_map(static fn($e) => "`$e`", $interface->getExtends());
            $output .= "**Extends:** " . \implode(', ', $extends) . "\n\n";
        }

        // Process constants if enabled
        if ($this->config['include_constants'] && !empty($interface->getConstants())) {
            $output .= "## Constants\n\n";
            foreach ($interface->getConstants() as $constant) {
                $output .= $this->processConstant($constant);
            }
        }

        // Process methods
        $methods = $interface->getMethods();
        if (!empty($methods)) {
            $output .= "## Methods\n\n";
            foreach ($methods as $method) {
                $output .= $this->processMethod($method);
            }
        }

        return $output;
    }

    /**
     * Process a trait and generate documentation
     */
    private function processTrait(TraitType $trait, string $fullTraitName): string
    {
        $headingLevel = $this->config['class_heading_level'];
        $output = \str_repeat('#', $headingLevel) . " Trait: $fullTraitName\n\n";

        // Add trait docblock if available and enabled
        if ($this->config['keep_doc_comments'] && $trait->getComment()) {
            $docComment = $this->formatDocComment($trait->getComment());
            $output .= "$docComment\n\n";
        }

        // Process properties
        $properties = $trait->getProperties();
        if (!empty($properties)) {
            $output .= "## Properties\n\n";
            foreach ($properties as $property) {
                $output .= $this->processProperty($property);
            }
        }

        // Process methods
        $methods = $trait->getMethods();
        if (!empty($methods)) {
            $output .= "## Methods\n\n";
            foreach ($methods as $method) {
                $output .= $this->processMethod($method);
            }
        }

        return $output;
    }

    /**
     * Process an enum and generate documentation (PHP 8.1+)
     */
    private function processEnum(EnumType $enum, string $fullEnumName): string
    {
        $headingLevel = $this->config['class_heading_level'];
        $output = \str_repeat('#', $headingLevel) . " Enum: $fullEnumName\n\n";

        // Add enum docblock if available and enabled
        if ($this->config['keep_doc_comments'] && $enum->getComment()) {
            $docComment = $this->formatDocComment($enum->getComment());
            $output .= "$docComment\n\n";
        }

        // Process enum cases
        $cases = $enum->getCases();
        if (!empty($cases)) {
            $output .= "## Cases\n\n";
            foreach ($cases as $case) {
                $caseName = $case->getName();
                $output .= "- `$caseName`";

                if ($case->getValue() !== null) {
                    $output .= " = {$case->getValue()}";
                }

                if ($this->config['keep_doc_comments'] && $case->getComment()) {
                    $caseComment = \trim($this->formatDocComment($case->getComment()));
                    $output .= " - $caseComment";
                }

                $output .= "\n";
            }
            $output .= "\n";
        }

        // Process methods
        $methods = $enum->getMethods();
        if (!empty($methods)) {
            $output .= "## Methods\n\n";
            foreach ($methods as $method) {
                $output .= $this->processMethod($method);
            }
        }

        return $output;
    }

    /**
     * Process a property and generate documentation
     */
    private function processProperty(Property $property): string
    {
        // Skip properties based on visibility settings
        if ($property->isPrivate() && !$this->config['include_private_properties']) {
            return '';
        }

        if ($property->isProtected() && !$this->config['include_protected_properties']) {
            return '';
        }

        $propertyName = $property->getName();
        $output = "### \$$propertyName\n\n";

        // Add property visibility
        $visibility = $property->isPublic() ? 'public' : ($property->isProtected() ? 'protected' : 'private');
        $staticFlag = $property->isStatic() ? ' static' : '';

        // Add readonly flag if available (PHP 8.1+)
        $readonlyFlag = '';
        if (\method_exists($property, 'isReadonly') && $property->isReadonly()) {
            $readonlyFlag = ' readonly';
        }

        $output .= "`$visibility$staticFlag$readonlyFlag`\n\n";

        // Add property docblock if available and enabled
        if ($this->config['keep_doc_comments'] && $property->getComment()) {
            $docComment = $this->formatDocComment($property->getComment());
            $output .= "$docComment\n\n";
        }

        // Add property type if available
        if ($property->getType()) {
            $type = $property->getType();
            $output .= "**Type:** `$type`\n\n";
        }

        // Add default value if available and enabled
        if ($this->config['include_property_defaults'] && $property->getValue() !== null) {
            $defaultValue = \var_export($property->getValue(), true);
            $output .= "**Default:** `$defaultValue`\n\n";
        }

        return $output;
    }

    /**
     * Process a method and generate documentation
     */
    private function processMethod(Method $method): string
    {
        // Skip methods based on visibility settings
        if ($method->isPrivate() && !$this->config['include_private_methods']) {
            return '';
        }

        if ($method->isProtected() && !$this->config['include_protected_methods']) {
            return '';
        }

        $methodName = $method->getName();
        $output = "### $methodName\n\n";

        // Add method visibility
        $visibility = $method->isPublic() ? 'public' : ($method->isProtected() ? 'protected' : 'private');
        $staticFlag = $method->isStatic() ? ' static' : '';
        $abstractFlag = $method->isAbstract() ? ' abstract' : '';
        $finalFlag = $method->isFinal() ? ' final' : '';

        $output .= "`$visibility$staticFlag$abstractFlag$finalFlag`\n\n";

        // Add method docblock if available and enabled
        if ($this->config['keep_doc_comments'] && $method->getComment()) {
            $docComment = $this->formatDocComment($method->getComment());
            $output .= "$docComment\n\n";

            // Extract route information if enabled
            if ($this->config['extract_routes']) {
                $routeInfo = $this->extractRouteInfo($method->getComment());
                if ($routeInfo) {
                    $output .= "**Route:** $routeInfo\n\n";
                }
            }
        }

        // Process method parameters
        $parameters = $method->getParameters();
        if (!empty($parameters)) {
            $output .= "**Parameters:**\n\n";
            foreach ($parameters as $param) {
                $paramName = $param->getName();
                $paramType = $param->getType() ?? 'mixed';

                $output .= "- `$paramType \$$paramName`";

                // Add default value if available
                if ($param->hasDefaultValue()) {
                    $defaultValue = \var_export($param->getDefaultValue(), true);
                    $output .= " = $defaultValue";
                }

                $output .= "\n";
            }
            $output .= "\n";
        }

        // Add return type if available
        if ($method->getReturnType()) {
            $returnType = $method->getReturnType();
            $output .= "**Returns:** `$returnType`\n\n";
        }

        // Include method body as a code block if enabled and not abstract
        if ($this->config['include_implementations'] && !$method->isAbstract()) {
            $methodCode = (string) $method;
            $output .= "```{$this->config['code_block_format']}\n$methodCode\n```\n\n";
        } else {
            // Just show the method signature
            $signature = $this->getMethodSignature($method);
            $output .= "```{$this->config['code_block_format']}\n$signature\n```\n\n";
        }

        return $output;
    }

    /**
     * Process a constant and generate documentation
     *
     * @param mixed $constant Constant object from nette/php-generator
     */
    private function processConstant($constant): string
    {
        $constName = $constant->getName();
        $output = "### $constName\n\n";

        // Add constant docblock if available and enabled
        if ($this->config['keep_doc_comments'] && \method_exists($constant, 'getComment') && $constant->getComment()) {
            $docComment = $this->formatDocComment($constant->getComment());
            $output .= "$docComment\n\n";
        }

        // Get constant value
        $constValue = \var_export($constant->getValue(), true);
        $output .= "**Value:** `$constValue`\n\n";

        return $output;
    }

    /**
     * Extract route information from method doc comment
     */
    private function extractRouteInfo(string $docComment): ?string
    {
        // Look for Symfony style route annotations
        if (\preg_match(
            '/@Route\s*\(\s*["\']([^"\']+)["\'](?:.*?methods\s*=\s*\{([^}]+)\})?/s',
            $docComment,
            $matches,
        )) {
            $path = $matches[1];
            $methods = isset($matches[2]) ? \trim(\str_replace(['"', "'"], '', $matches[2])) : 'GET';
            return "`$path` ($methods)";
        }

        // Look for Laravel style route annotations
        if (\preg_match('/@(Get|Post|Put|Delete|Patch)\s*\(\s*["\']([^"\']+)["\']/', $docComment, $matches)) {
            $method = \strtoupper($matches[1]);
            $path = $matches[2];
            return "`$path` ($method)";
        }

        return null;
    }

    /**
     * Get method signature without implementation
     */
    private function getMethodSignature(Method $method): string
    {
        $visibility = $method->isPublic() ? 'public ' : ($method->isProtected() ? 'protected ' : 'private ');
        $staticFlag = $method->isStatic() ? 'static ' : '';
        $abstractFlag = $method->isAbstract() ? 'abstract ' : '';
        $finalFlag = $method->isFinal() ? 'final ' : '';

        $methodName = $method->getName();

        $params = [];
        foreach ($method->getParameters() as $param) {
            $paramType = $param->getType() ? (string) $param->getType() . ' ' : '';
            $paramName = '$' . $param->getName();

            $paramStr = $paramType . $paramName;

            if ($param->hasDefaultValue()) {
                $defaultValue = \var_export($param->getDefaultValue(), true);
                $paramStr .= ' = ' . $defaultValue;
            }

            $params[] = $paramStr;
        }

        $paramsStr = \implode(', ', $params);
        $returnType = $method->getReturnType() ? ': ' . (string) $method->getReturnType() : '';

        return "{$visibility}{$staticFlag}{$abstractFlag}{$finalFlag}function {$methodName}({$paramsStr}){$returnType}";
    }

    /**
     * Format a doc comment by removing the comment markers
     */
    private function formatDocComment(string $docComment): string
    {
        // Remove comment start and end markers
        $docComment = \preg_replace(['#^\s*/\*+#', '#\*+/\s*$#'], '', $docComment);

        // Split into lines
        $lines = \explode("\n", (string) $docComment);
        $result = [];

        foreach ($lines as $line) {
            // Remove leading asterisks and whitespace
            $line = \preg_replace('#^\s*\*\s?#', '', $line);

            // Skip empty lines at the beginning
            if (empty($result) && \trim((string) $line) === '') {
                continue;
            }

            $result[] = $line;
        }

        // Join lines back together
        return \implode("\n", $result);
    }
}
