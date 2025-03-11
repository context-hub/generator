<?php

declare(strict_types=1);

namespace Tests\Modifier;

use Butschster\ContextGenerator\Modifier\PhpContentFilter;
use PHPUnit\Framework\TestCase;

class PhpContentFilterTest extends TestCase
{
    private const SAMPLE_CLASS = <<<'PHP_WRAP'
    <?php
    namespace App\Example;
    
    use App\SomeClass;
    use App\OtherClass;
    
    /**
     * Sample class for testing the PHP content filter
     */
    #[SomeAttribute]
    class SampleClass
    {
        public const PUBLIC_CONST = 'public';
        protected const PROTECTED_CONST = 'protected';
        private const PRIVATE_CONST = 'private';
    
        /**
         * @var string
         */
        #[Property]
        public string $publicProperty = 'public';
    
        /**
         * @var string
         */
        protected string $protectedProperty = 'protected';
    
        /**
         * @var string
         */
        private string $privateProperty = 'private';
    
        /**
         * Constructor
         */
        public function __construct(
            public readonly string $name,
            protected readonly int $age,
            private readonly bool $active,
        ) {
            // Constructor implementation
        }
    
        /**
         * Public method
         */
        #[Route('/')]
        public function publicMethod(string $param): string
        {
            return "Public method with param: {$param}";
        }
    
        /**
         * Protected method
         */
        protected function protectedMethod(): void
        {
            // Protected method implementation
        }
    
        /**
         * Private method
         */
        private function privateMethod(): bool
        {
            return true;
        }
    
        /**
         * Getter method
         */
        public function getName(): string
        {
            return $this->name;
        }
    
        /**
         * Getter method
         */
        public function getAge(): int
        {
            return $this->age;
        }
    
        /**
         * Getter method
         */
        public function isActive(): bool
        {
            return $this->active;
        }
    }
    PHP_WRAP;

    public function testKeepOnlyPublicMethods(): void
    {
        $filter = new PhpContentFilter([
            'method_visibility' => ['public'],
        ]);

        $result = $filter->modify(self::SAMPLE_CLASS);

        $this->assertStringContainsString('public function __construct', $result);
        $this->assertStringContainsString('public function publicMethod', $result);
        $this->assertStringContainsString('public function getName', $result);
        $this->assertStringContainsString('public function getAge', $result);
        $this->assertStringContainsString('public function isActive', $result);

        $this->assertStringNotContainsString('protected function protectedMethod', $result);
        $this->assertStringNotContainsString('private function privateMethod', $result);
    }

    public function testKeepOnlyGetterMethods(): void
    {
        $filter = new PhpContentFilter([
            'include_methods_pattern' => '/^(get|is)/',
        ]);

        $result = $filter->modify(self::SAMPLE_CLASS);

        $this->assertStringContainsString('public function getName', $result);
        $this->assertStringContainsString('public function getAge', $result);
        $this->assertStringContainsString('public function isActive', $result);

        $this->assertStringNotContainsString('public function __construct', $result);
        $this->assertStringNotContainsString('public function publicMethod', $result);
        $this->assertStringNotContainsString('protected function protectedMethod', $result);
        $this->assertStringNotContainsString('private function privateMethod', $result);
    }

    public function testExcludePrivateProperties(): void
    {
        $filter = new PhpContentFilter([
            'property_visibility' => ['public', 'protected'],
        ]);

        $result = $filter->modify(self::SAMPLE_CLASS);

        $this->assertStringContainsString('public string $publicProperty', $result);
        $this->assertStringContainsString('protected string $protectedProperty', $result);
        $this->assertStringNotContainsString('private string $privateProperty', $result);
    }

    public function testKeepMethodBodies(): void
    {
        $filter = new PhpContentFilter([
            'keep_method_bodies' => true,
        ]);

        $result = $filter->modify(self::SAMPLE_CLASS);

        $this->assertStringContainsString('return "Public method with param: {$param}";', $result);
        $this->assertStringContainsString('return $this->name;', $result);
    }

    public function testCustomMethodBodyPlaceholder(): void
    {
        $filter = new PhpContentFilter([
            'keep_method_bodies' => false,
            'method_body_placeholder' => '// Method body removed',
        ]);

        $result = $filter->modify(self::SAMPLE_CLASS);

        $this->assertStringContainsString('// Method body removed', $result);
        $this->assertStringNotContainsString('return "Public method with param: {$param}";', $result);
    }

    public function testRemoveDocComments(): void
    {
        $filter = new PhpContentFilter([
            'keep_doc_comments' => false,
        ]);

        $result = $filter->modify(self::SAMPLE_CLASS);

        $this->assertStringNotContainsString('/**', $result);
        $this->assertStringNotContainsString('* Public method', $result);
    }

    public function testIncludeSpecificMethods(): void
    {
        $filter = new PhpContentFilter([
            'include_methods' => ['__construct', 'publicMethod'],
        ]);

        $result = $filter->modify(self::SAMPLE_CLASS);

        $this->assertStringContainsString('public function __construct', $result);
        $this->assertStringContainsString('public function publicMethod', $result);

        $this->assertStringNotContainsString('public function getName', $result);
        $this->assertStringNotContainsString('public function getAge', $result);
        $this->assertStringNotContainsString('public function isActive', $result);
        $this->assertStringNotContainsString('protected function protectedMethod', $result);
        $this->assertStringNotContainsString('private function privateMethod', $result);
    }

    public function testExcludeSpecificMethods(): void
    {
        $filter = new PhpContentFilter([
            'exclude_methods' => ['__construct', 'privateMethod'],
        ]);

        $result = $filter->modify(self::SAMPLE_CLASS);

        $this->assertStringNotContainsString('public function __construct', $result);
        $this->assertStringNotContainsString('private function privateMethod', $result);

        $this->assertStringContainsString('public function publicMethod', $result);
        $this->assertStringContainsString('public function getName', $result);
        $this->assertStringContainsString('public function getAge', $result);
        $this->assertStringContainsString('public function isActive', $result);
        $this->assertStringContainsString('protected function protectedMethod', $result);
    }

    public function testRemoveAttributes(): void
    {
        $filter = new PhpContentFilter([
            'keep_attributes' => false,
        ]);

        $result = $filter->modify(self::SAMPLE_CLASS);

        $this->assertStringNotContainsString('#[SomeAttribute]', $result);
        $this->assertStringNotContainsString('#[Property]', $result);
        $this->assertStringNotContainsString('#[Route', $result);
    }

    public function testComplexFiltering(): void
    {
        $filter = new PhpContentFilter([
            'method_visibility' => ['public'],
            'property_visibility' => ['public'],
            'constant_visibility' => ['public'],
            'exclude_methods' => ['__construct'],
            'include_methods_pattern' => '/^(get|is)/',
            'keep_method_bodies' => false,
            'keep_doc_comments' => true,
            'keep_attributes' => false,
        ]);

        $result = $filter->modify(self::SAMPLE_CLASS);

        // Should include these
        $this->assertStringContainsString('public const PUBLIC_CONST', $result);
        $this->assertStringContainsString('public string $publicProperty', $result);
        $this->assertStringContainsString('public function getName', $result);
        $this->assertStringContainsString('public function getAge', $result);
        $this->assertStringContainsString('public function isActive', $result);

        // Should exclude these
        $this->assertStringNotContainsString('protected const PROTECTED_CONST', $result);
        $this->assertStringNotContainsString('private const PRIVATE_CONST', $result);
        $this->assertStringNotContainsString('protected string $protectedProperty', $result);
        $this->assertStringNotContainsString('private string $privateProperty', $result);
        $this->assertStringNotContainsString('public function __construct', $result);
        $this->assertStringNotContainsString('public function publicMethod', $result);
        $this->assertStringNotContainsString('protected function protectedMethod', $result);
        $this->assertStringNotContainsString('private function privateMethod', $result);

        // Should have docs but not attributes
        $this->assertStringContainsString('/**', $result);
        $this->assertStringNotContainsString('#[', $result);

        // Should not have method bodies
        $this->assertStringContainsString('/* ... */', $result);
        $this->assertStringNotContainsString('return $this->name;', $result);
    }
}
