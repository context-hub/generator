<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier\PhpSignature;

use Butschster\ContextGenerator\Modifier\SourceModifierInterface;
use Nette\PhpGenerator\ClassLike;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\EnumType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\TraitType;

/**
 * Parser for PHP class files to extract signatures without implementation details.
 */
final class PhpSignature implements SourceModifierInterface
{
    private const array MAGIC_METHODS = [
        '__construct',
    ];

    /**
     *
     * @psalm-return 'php-signature'
     */
    public function getIdentifier(): string
    {
        return 'php-signature';
    }

    public function supports(string $contentType): bool
    {
        return \str_ends_with(haystack: $contentType, needle: '.php');
    }

    public function modify(string $content, array $context = []): string
    {
        try {
            $file = PhpFile::fromCode(code: $content);

            $output = '';
            foreach ($file->getNamespaces() as $namespace) {
                $output .= "namespace {$namespace->getName()};\n\n";

                foreach ($namespace->getUses() as $use) {
                    $output .= "use $use;\n";
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
     * Process a class and generate its signature
     */
    private function processClass(ClassLike $class): string
    {
        $output = '';

        // Determine class type and generate signature
        if ($class->isInterface()) {
            $output .= $this->generateInterfaceSignature(interface: $class);
        } elseif ($class->isTrait()) {
            $output .= $this->generateTraitSignature(trait: $class);
        } elseif (PHP_VERSION_ID >= 80100 && \method_exists(object_or_class: $class, method: 'isEnum') && $class->isEnum()) {
            $output .= $this->generateEnumSignature(enum: $class);
        } else {
            $output .= $this->generateClassSignature(class: $class);
        }

        return $output . "\n";
    }

    /**
     * Generate class signature
     */
    private function generateClassSignature(ClassType $class): string
    {
        // Remove not public properties
        foreach ($class->getProperties() as $property) {
            if (!$property->isPublic()) {
                $class->removeProperty(name: $property->getName());
            }
        }

        // Remove not public constants
        foreach ($class->getConstants() as $constant) {
            if (!$constant->isPublic()) {
                $class->removeConstant(name: $constant->getName());
            }
        }


        // Remove method bodies
        foreach ($class->getMethods() as $method) {
            // if is magic method, skip
            if (\in_array(needle: $method->getName(), haystack: self::MAGIC_METHODS, strict: true)) {
                continue;
            }

            if (!$method->isPublic()) {
                $class->removeMethod(name: $method->getName());
            }

            $method->setBody(code: $method->isAbstract() ? '' : '/* ... */');
        }

        // Generate the class code
        return (string) $class;
    }

    /**
     * Generate interface signature
     */
    private function generateInterfaceSignature(InterfaceType $interface): string
    {
        // Generate the interface code
        return (string) $interface;
    }

    /**
     * Generate trait signature
     */
    private function generateTraitSignature(TraitType $trait): string
    {
        // Remove not public properties
        foreach ($trait->getProperties() as $property) {
            if (!$property->isPublic()) {
                $trait->removeProperty(name: $property->getName());
            }
        }

        // Remove not public constants
        foreach ($trait->getConstants() as $constant) {
            if (!$constant->isPublic()) {
                $trait->removeConstant(name: $constant->getName());
            }
        }

        foreach ($trait->getMethods() as $method) {
            if (!$method->isPublic()) {
                $trait->removeMethod(name: $method->getName());
            }
            $method->setBody(code: '/* ... */');
        }

        // Generate the trait code
        return (string) $trait;
    }

    /**
     * Generate enum signature (PHP 8.1+)
     */
    private function generateEnumSignature(EnumType $enum): string
    {
        // Remove method bodies
        foreach ($enum->getMethods() as $method) {
            if (!$method->isPublic()) {
                $enum->removeMethod(name: $method->getName());
            }
            $method->setBody(code: '/* ... */');
        }

        // Generate the enum code
        return (string) $enum;
    }
}
