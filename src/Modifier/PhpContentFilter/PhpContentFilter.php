<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\PhpContentFilter;

use Butschster\ContextGenerator\Modifier\SourceModifierInterface;
use Nette\PhpGenerator\ClassLike;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Constant;
use Nette\PhpGenerator\EnumType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\Property;
use Nette\PhpGenerator\TraitType;

/**
 * Modifier for filtering PHP class content.
 *
 * Allows for selective inclusion or exclusion of class elements like methods, properties,
 * constants, and comments based on configurable criteria.
 */
final class PhpContentFilter implements SourceModifierInterface
{
    /**
     * Filter configuration
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Create a new PHP content filter
     *
     * @param array<string, mixed> $config Filter configuration
     */
    public function __construct(array $config = [])
    {
        // Set default configuration values
        $this->config = \array_merge([
            // Methods to include (empty means include all unless exclude_methods is set)
            'include_methods' => [],
            // Methods to exclude (empty means exclude none)
            'exclude_methods' => [],
            // Properties to include (empty means include all unless exclude_properties is set)
            'include_properties' => [],
            // Properties to exclude (empty means exclude none)
            'exclude_properties' => [],
            // Constants to include (empty means include all unless exclude_constants is set)
            'include_constants' => [],
            // Constants to exclude (empty means exclude none)
            'exclude_constants' => [],
            // Include methods matching these visibilities (public, protected, private)
            'method_visibility' => ['public', 'protected', 'private'],
            // Include properties matching these visibilities (public, protected, private)
            'property_visibility' => ['public', 'protected', 'private'],
            // Include constants matching these visibilities (public, protected, private)
            'constant_visibility' => ['public', 'protected', 'private'],
            // Whether to keep method bodies (true) or replace with placeholders (false)
            'keep_method_bodies' => true,
            // Placeholder for method bodies when keep_method_bodies is false
            'method_body_placeholder' => '/* ... */',
            // Whether to keep doc comments
            'keep_doc_comments' => true,
            // Whether to keep attributes (PHP 8+ attributes)
            'keep_attributes' => true,
            // Regular expression patterns for methods to include
            'include_methods_pattern' => null,
            // Regular expression patterns for methods to exclude
            'exclude_methods_pattern' => null,
            // Regular expression patterns for properties to include
            'include_properties_pattern' => null,
            // Regular expression patterns for properties to exclude
            'exclude_properties_pattern' => null,
        ], $config);
    }

    /**
     * Get the modifier identifier.
     */
    public function getIdentifier(): string
    {
        return 'php-content-filter';
    }

    /**
     * Check if this modifier supports the given content type.
     */
    public function supports(string $contentType): bool
    {
        return \str_ends_with(haystack: $contentType, needle: '.php');
    }

    /**
     * Modify PHP content by applying configured filters.
     *
     * @param string $content The PHP content to modify
     * @param array<string, mixed> $context Optional context information
     * @return string The modified PHP content
     */
    public function modify(string $content, array $context = []): string
    {
        $this->config = \array_merge($this->config, $context);

        try {
            $file = PhpFile::fromCode(code: $content);
            $output = '';

            foreach ($file->getNamespaces() as $namespace) {
                $output .= "namespace {$namespace->getName()};\n\n";

                foreach ($namespace->getUses() as $alias => $use) {
                    if ($alias === '') {
                        $output .= "use $use;\n";
                    } else {
                        $output .= "use $use as $alias;\n";
                    }
                }

                if (!empty($namespace->getUses())) {
                    $output .= "\n";
                }

                foreach ($namespace->getClasses() as $class) {
                    $output .= $this->processClass(class: $class);
                }
            }

            return $output;
        } catch (\Throwable $e) {
            return "// Error parsing file: {$e->getMessage()}\n";
        }
    }

    /**
     * Process a class and filter its contents according to the configuration.
     */
    private function processClass(ClassLike $class): string
    {
        // Determine class type and process accordingly
        if ($class->isInterface()) {
            return $this->processInterface(interface: $class);
        } elseif ($class->isTrait()) {
            return $this->processTrait(trait: $class);
        } elseif (\PHP_VERSION_ID >= 80100 && \method_exists(object_or_class: $class, method: 'isEnum') && $class->isEnum()) {
            return $this->processEnum(enum: $class);
        }

        return $this->processStandardClass(class: $class);
    }

    /**
     * Process a standard class by applying filters.
     */
    private function processStandardClass(ClassType $class): string
    {
        // Filter properties
        $this->filterProperties(class: $class);

        // Filter constants
        $this->filterConstants(class: $class);

        // Filter methods
        $this->filterMethods(class: $class);

        // Generate the class code
        return (string) $class . "\n";
    }

    /**
     * Process an interface by applying filters.
     */
    private function processInterface(InterfaceType $interface): string
    {
        // Filter constants
        $this->filterConstants(class: $interface);

        // Filter methods
        $this->filterMethods(class: $interface);

        // Generate the interface code
        return (string) $interface . "\n";
    }

    /**
     * Process a trait by applying filters.
     */
    private function processTrait(TraitType $trait): string
    {
        // Filter properties
        $this->filterProperties(class: $trait);

        // Filter constants
        $this->filterConstants(class: $trait);

        // Filter methods
        $this->filterMethods(class: $trait);

        // Generate the trait code
        return (string) $trait . "\n";
    }

    /**
     * Process an enum by applying filters.
     */
    private function processEnum(EnumType $enum): string
    {
        // Filter constants (for backed enums)
        $this->filterConstants(class: $enum);

        // Filter methods
        $this->filterMethods(class: $enum);

        // Generate the enum code
        return (string) $enum . "\n";
    }

    /**
     * Filter class properties based on configuration.
     */
    private function filterProperties(ClassLike $class): void
    {
        if (!\method_exists(object_or_class: $class, method: 'getProperties')) {
            return;
        }

        $propertiesToRemove = [];

        foreach ($class->getProperties() as $property) {
            if (!$this->shouldKeepProperty(property: $property)) {
                $propertiesToRemove[] = $property->getName();
                continue;
            }

            // Handle doc comments if needed
            if (!$this->config['keep_doc_comments'] && \method_exists(object_or_class: $property, method: 'setComment')) {
                $property->setComment('');
            }

            // Handle attributes if needed
            if (!$this->config['keep_attributes'] && \method_exists(object_or_class: $property, method: 'getAttributes')) {
                foreach ($property->getAttributes() as $attribute) {
                    /** @psalm-suppress UndefinedMethod */
                    $property->removeAttribute($attribute->getName());
                }
            }
        }

        // Remove properties that didn't pass the filter
        foreach ($propertiesToRemove as $propertyName) {
            /** @psalm-suppress UndefinedMethod */
            $class->removeProperty($propertyName);
        }
    }

    /**
     * Filter class constants based on configuration.
     */
    private function filterConstants(ClassLike $class): void
    {
        if (!\method_exists(object_or_class: $class, method: 'getConstants')) {
            return;
        }

        $constantsToRemove = [];

        foreach ($class->getConstants() as $constant) {
            if (!$this->shouldKeepConstant(constant: $constant)) {
                $constantsToRemove[] = $constant->getName();
                continue;
            }

            // Handle doc comments if needed
            if (!$this->config['keep_doc_comments'] && \method_exists(object_or_class: $constant, method: 'setComment')) {
                $constant->setComment('');
            }

            // Handle attributes if needed
            if (!$this->config['keep_attributes'] && \method_exists(object_or_class: $constant, method: 'getAttributes')) {
                foreach ($constant->getAttributes() as $attribute) {
                    /** @psalm-suppress UndefinedMethod */
                    $constant->removeAttribute($attribute->getName());
                }
            }
        }

        // Remove constants that didn't pass the filter
        foreach ($constantsToRemove as $constantName) {
            /** @psalm-suppress UndefinedMethod */
            $class->removeConstant($constantName);
        }
    }

    /**
     * Filter class methods based on configuration.
     */
    private function filterMethods(ClassLike $class): void
    {
        if (!\method_exists(object_or_class: $class, method: 'getMethods')) {
            return;
        }

        $methodsToRemove = [];

        foreach ($class->getMethods() as $method) {
            if (!$this->shouldKeepMethod(method: $method)) {
                $methodsToRemove[] = $method->getName();
                continue;
            }

            // Handle method body if needed
            if (!$this->config['keep_method_bodies'] && !$method->isAbstract()) {
                $method->setBody($this->config['method_body_placeholder']);
            }

            // Handle doc comments if needed
            if (!$this->config['keep_doc_comments']) {
                $method->setComment('');
            }

            // Handle attributes if needed
            if (!$this->config['keep_attributes']) {
                foreach ($method->getAttributes() as $attribute) {
                    /** @psalm-suppress UndefinedMethod */
                    $method->removeAttribute($attribute->getName());
                }
            }
        }

        // Remove methods that didn't pass the filter
        foreach ($methodsToRemove as $methodName) {
            $class->removeMethod($methodName);
        }
    }

    /**
     * Determine if a property should be kept based on configuration.
     */
    private function shouldKeepProperty(Property $property): bool
    {
        // Check visibility
        $visibility = $property->isPublic() ? 'public' : ($property->isProtected() ? 'protected' : 'private');
        if (!\in_array(needle: $visibility, haystack: $this->config['property_visibility'], strict: true)) {
            return false;
        }

        $propertyName = $property->getName();

        // If include list is provided and not empty, check against it
        if (!empty($this->config['include_properties'])) {
            if (\in_array(needle: $propertyName, haystack: $this->config['include_properties'], strict: true)) {
                return true;
            }

            // If we have an include list but the property isn't in it,
            // still check pattern matches if configured
            if ($this->config['include_properties_pattern'] !== null) {
                if (\preg_match(pattern: $this->config['include_properties_pattern'], subject: $propertyName)) {
                    return true;
                }
            }

            // Not in include list and doesn't match pattern
            return false;
        }

        // Check exclude list
        if (\in_array(needle: $propertyName, haystack: $this->config['exclude_properties'], strict: true)) {
            return false;
        }

        // Check exclude pattern
        if ($this->config['exclude_properties_pattern'] !== null) {
            if (\preg_match(pattern: $this->config['exclude_properties_pattern'], subject: $propertyName)) {
                return false;
            }
        }

        // Check include pattern (when no explicit include list exists)
        if (empty($this->config['include_properties']) && $this->config['include_properties_pattern'] !== null) {
            return (bool) \preg_match(pattern: $this->config['include_properties_pattern'], subject: $propertyName);
        }

        // No filters matched, keep the property
        return true;
    }

    /**
     * Determine if a constant should be kept based on configuration.
     */
    private function shouldKeepConstant(Constant $constant): bool
    {
        // Check visibility if applicable
        if (\method_exists(object_or_class: $constant, method: 'isPublic')) {
            $visibility = $constant->isPublic() ? 'public' : ($constant->isProtected() ? 'protected' : 'private');
            if (!\in_array(needle: $visibility, haystack: $this->config['constant_visibility'], strict: true)) {
                return false;
            }
        }

        $constantName = $constant->getName();

        // If include list is provided, check against it
        if (!empty($this->config['include_constants'])) {
            return \in_array(needle: $constantName, haystack: $this->config['include_constants'], strict: true);
        }

        // Check exclude list
        if (\in_array(needle: $constantName, haystack: $this->config['exclude_constants'], strict: true)) {
            return false;
        }

        // No filters matched, keep the constant
        return true;
    }

    /**
     * Determine if a method should be kept based on configuration.
     */
    private function shouldKeepMethod(Method $method): bool
    {
        // Check visibility
        $visibility = $method->isPublic() ? 'public' : ($method->isProtected() ? 'protected' : 'private');
        if (!\in_array(needle: $visibility, haystack: $this->config['method_visibility'], strict: true)) {
            return false;
        }

        $methodName = $method->getName();

        // If include list is provided and not empty, check against it
        if (!empty($this->config['include_methods'])) {
            if (\in_array(needle: $methodName, haystack: $this->config['include_methods'], strict: true)) {
                return true;
            }

            // If we have an include list but the method isn't in it,
            // still check pattern matches if configured
            if ($this->config['include_methods_pattern'] !== null) {
                if (\preg_match(pattern: $this->config['include_methods_pattern'], subject: $methodName)) {
                    return true;
                }
            }

            // Not in include list and doesn't match pattern
            return false;
        }

        // Check exclude list
        if (\in_array(needle: $methodName, haystack: $this->config['exclude_methods'], strict: true)) {
            return false;
        }

        // Check exclude pattern
        if ($this->config['exclude_methods_pattern'] !== null) {
            if (\preg_match(pattern: $this->config['exclude_methods_pattern'], subject: $methodName)) {
                return false;
            }
        }

        // Check include pattern (when no explicit include list exists)
        if (empty($this->config['include_methods']) && $this->config['include_methods_pattern'] !== null) {
            return (bool) \preg_match(pattern: $this->config['include_methods_pattern'], subject: $methodName);
        }

        // No filters matched, keep the method
        return true;
    }
}
