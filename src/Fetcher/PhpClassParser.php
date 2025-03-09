<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Fetcher;

use Nette\PhpGenerator\ClassLike;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\EnumType;
use Nette\PhpGenerator\InterfaceType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\TraitType;

/**
 * Parser for PHP class files to extract signatures without implementation details.
 * Uses Nette PHP Generator for robust parsing.
 */
final class PhpClassParser
{
    private const MAGIC_METHODS = [
        '__construct',
    ];

    /**
     * Parse a PHP file and extract class signatures
     */
    public function parse(string $content): string
    {
        try {
            $file = PhpFile::fromCode($content);

            $output = '';
            foreach ($file->getNamespaces() as $namespace) {
                $output .= "namespace {$namespace->getName()};\n\n";

                foreach ($namespace->getUses() as $use) {
                    $output .= "use $use;\n";
                }

                foreach ($namespace->getClasses() as $class) {
                    $output .= $this->processClass($class);
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
            $output .= $this->generateInterfaceSignature($class);
        } elseif ($class->isTrait()) {
            $output .= $this->generateTraitSignature($class);
        } elseif (PHP_VERSION_ID >= 80100 && method_exists($class, 'isEnum') && $class->isEnum()) {
            $output .= $this->generateEnumSignature($class);
        } else {
            $output .= $this->generateClassSignature($class);
        }

        return $output . "\n";
    }

    /**
     * Generate class signature
     */
    private function generateClassSignature(ClassType $class): string
    {
        // Remove method bodies
        foreach ($class->getMethods() as $method) {
            // if is magic method, skip
            if (\in_array($method->getName(), self::MAGIC_METHODS, true)) {
                continue;
            }

            if (!$method->isPublic()) {
                $class->removeMethod($method->getName());
            }

            $method->setBody($method->isAbstract() ? '' : '/* ... */');
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
        foreach ($trait->getMethods() as $method) {
            if (!$method->isPublic()) {
                $trait->removeMethod($method->getName());
            }
            $method->setBody('/* ... */');
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
                $enum->removeMethod($method->getName());
            }
            $method->setBody('/* ... */');
        }

        // Generate the enum code
        return (string) $enum;
    }
}