<?php

declare(strict_types=1);

namespace Tests\McpInspector\Tools;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\McpInspector\McpInspectorTestCase;

#[Group('mcp-inspector')]
final class PhpStructureToolTest extends McpInspectorTestCase
{
    #[Test]
    public function it_parses_simple_class(): void
    {
        // Arrange
        $this->createFile('src/SimpleClass.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

final class SimpleClass
{
    public function __construct(
        private string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/SimpleClass.php',
            'depth' => 0,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'namespace App');
        $this->assertContentContains($result, 'final class SimpleClass');
        $this->assertContentContains($result, 'function getName()');
        $this->assertContentContains($result, ': string');
    }

    #[Test]
    public function it_shows_use_statements_with_vendor_marker(): void
    {
        // Arrange
        $this->createFile(
            'src/ServiceClass.php',
            <<<'PHP'
                              <?php
                              
                              declare(strict_types=1);
                              
                              namespace App;
                              
                              use Psr\Log\LoggerInterface;
                              
                              final class ServiceClass
                              {
                                  public function __construct(
                                      private LoggerInterface $logger,
                                  ) {}
                              }
                              PHP,
        );

        $this->createFile(
            'vendor/composer/installed.json',
            \file_get_contents($this->vendorDir('composer/installed.json')),
        );

        $this->createFile(
            'vendor/psr/log/src/LoggerInterface.php',
            \file_get_contents($this->vendorDir('psr/log/src/LoggerInterface.php')),
        );

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/ServiceClass.php',
            'depth' => 0,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'use Psr\Log\LoggerInterface');
        $this->assertContentContains($result, 'vendor/psr/log/src/LoggerInterface.php');
    }

    #[Test]
    public function it_hides_private_members_by_default(): void
    {
        // Arrange
        $this->createFile('src/MixedVisibility.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

class MixedVisibility
{
    public string $publicProp;
    protected string $protectedProp;
    private string $privateProp;

    public function publicMethod(): void {}
    protected function protectedMethod(): void {}
    private function privateMethod(): void {}
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/MixedVisibility.php',
            'depth' => 0,
            'showPrivate' => false,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'publicMethod');
        $this->assertContentContains($result, 'protectedMethod');
        $this->assertContentNotContains($result, 'privateMethod');
        $this->assertContentContains($result, '$publicProp');
        $this->assertContentContains($result, '$protectedProp');
        $this->assertContentNotContains($result, '$privateProp');
    }

    #[Test]
    public function it_shows_private_members_when_enabled(): void
    {
        // Arrange
        $this->createFile('src/MixedVisibility.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

class MixedVisibility
{
    private string $privateProp;
    private function privateMethod(): void {}
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/MixedVisibility.php',
            'depth' => 0,
            'showPrivate' => true,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'privateMethod');
        $this->assertContentContains($result, '$privateProp');
    }

    #[Test]
    public function it_parses_interface(): void
    {
        // Arrange
        $this->createFile('src/MyInterface.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

interface MyInterface
{
    public function process(string $data): array;
    public function validate(): bool;
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/MyInterface.php',
            'depth' => 0,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'interface MyInterface');
        $this->assertContentContains($result, 'function process(string $data)');
        $this->assertContentContains($result, ': array');
        $this->assertContentContains($result, 'function validate()');
        $this->assertContentContains($result, ': bool');
    }

    #[Test]
    public function it_parses_trait(): void
    {
        // Arrange
        $this->createFile('src/MyTrait.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

trait MyTrait
{
    protected string $traitProperty;

    public function traitMethod(): void
    {
        // Implementation
    }
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/MyTrait.php',
            'depth' => 0,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'trait MyTrait');
        $this->assertContentContains($result, 'traitMethod');
        $this->assertContentContains($result, '$traitProperty');
    }

    #[Test]
    public function it_parses_enum(): void
    {
        // Arrange
        $this->createFile('src/Status.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/Status.php',
            'depth' => 0,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'enum Status');
        $this->assertContentContains($result, 'function label()');
    }

    #[Test]
    public function it_shows_extends_relationship(): void
    {
        // Arrange
        $this->createFile('src/BaseClass.php', <<<'PHP'
<?php

namespace App;

abstract class BaseClass
{
    abstract public function handle(): void;
}
PHP);

        $this->createFile('src/ChildClass.php', <<<'PHP'
<?php

namespace App;

class ChildClass extends BaseClass
{
    public function handle(): void {}
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/ChildClass.php',
            'depth' => 0,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'class ChildClass extends BaseClass');
    }

    #[Test]
    public function it_shows_implements_relationship(): void
    {
        // Arrange
        $this->createFile('src/Handler.php', <<<'PHP'
<?php

namespace App;

interface Processable
{
    public function process(): void;
}

interface Validatable
{
    public function validate(): bool;
}
PHP);

        $this->createFile('src/ConcreteHandler.php', <<<'PHP'
<?php

namespace App;

class ConcreteHandler implements Processable, Validatable
{
    public function process(): void {}
    public function validate(): bool { return true; }
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/ConcreteHandler.php',
            'depth' => 0,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'implements');
        $this->assertContentContains($result, 'Processable');
        $this->assertContentContains($result, 'Validatable');
    }

    #[Test]
    public function it_follows_depth_one_relationships(): void
    {
        // Arrange - Create linked files with proper autoload structure
        $this->createFile('composer.json', \json_encode([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/',
                ],
            ],
        ]));

        $this->createFile('src/Repository.php', <<<'PHP'
<?php

namespace App;

interface Repository
{
    public function find(int $id): ?object;
}
PHP);

        $this->createFile('src/UserRepository.php', <<<'PHP'
<?php

namespace App;

class UserRepository implements Repository
{
    public function find(int $id): ?object
    {
        return null;
    }
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/UserRepository.php',
            'depth' => 1,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'class UserRepository');
        $this->assertContentContains($result, 'Linked (depth 1)');
        $this->assertContentContains($result, 'interface Repository');
    }

    #[Test]
    public function it_respects_depth_zero(): void
    {
        // Arrange
        $this->createFile('composer.json', \json_encode([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/',
                ],
            ],
        ]));

        $this->createFile('src/Repository.php', <<<'PHP'
<?php

namespace App;

interface Repository {}
PHP);

        $this->createFile('src/UserRepository.php', <<<'PHP'
<?php

namespace App;

class UserRepository implements Repository {}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/UserRepository.php',
            'depth' => 0,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'class UserRepository');
        $this->assertContentNotContains($result, 'Linked (depth');
    }

    #[Test]
    public function it_shows_php_attributes(): void
    {
        // Arrange
        $this->createFile('src/AttributedClass.php', <<<'PHP'
<?php

namespace App;

use Attribute;

#[Attribute]
class MyAttribute {}

#[MyAttribute]
final class AttributedClass
{
    public function __construct() {}
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/AttributedClass.php',
            'depth' => 0,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, '#[MyAttribute]');
    }

    #[Test]
    public function it_fails_for_non_php_file(): void
    {
        // Arrange
        $this->createFile('readme.txt', 'This is not a PHP file');

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'readme.txt',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, '.php');
    }

    #[Test]
    public function it_fails_for_non_existent_file(): void
    {
        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'non-existent.php',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertContentContains($result, 'not found');
    }

    #[Test]
    public function it_handles_syntax_error_gracefully(): void
    {
        // Arrange
        $this->createFile('src/Broken.php', <<<'PHP'
<?php

class Broken {
    public function broken( // Missing closing
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/Broken.php',
            'depth' => 0,
        ]);

        // Assert - Should not crash, returns error info
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'ERROR');
    }

    #[Test]
    public function it_shows_constructor_promoted_properties(): void
    {
        // Arrange
        $this->createFile('src/PromotedProps.php', <<<'PHP'
<?php

namespace App;

final readonly class PromotedProps
{
    public function __construct(
        public string $name,
        protected int $age,
        private bool $active = true,
    ) {}
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/PromotedProps.php',
            'depth' => 0,
            'showPrivate' => true,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, 'public string $name');
        $this->assertContentContains($result, 'protected int $age');
        $this->assertContentContains($result, 'private bool $active');
    }

    #[Test]
    public function it_shows_return_types(): void
    {
        // Arrange
        $this->createFile('src/TypedMethods.php', <<<'PHP'
<?php

namespace App;

class TypedMethods
{
    public function getString(): string {}
    public function getNullableInt(): ?int {}
    public function getUnion(): string|int {}
    public function getVoid(): void {}
    public function getArray(): array {}
}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/TypedMethods.php',
            'depth' => 0,
        ]);

        // Assert
        $this->assertInspectorSuccess($result);
        $this->assertContentContains($result, ': string');
        $this->assertContentContains($result, ': ?int');
        $this->assertContentContains($result, ': string|int');
        $this->assertContentContains($result, ': void');
        $this->assertContentContains($result, ': array');
    }

    #[Test]
    public function it_fails_with_invalid_project_parameter(): void
    {
        // Arrange
        $this->createFile('src/Test.php', <<<'PHP'
<?php

class Test {}
PHP);

        // Act
        $result = $this->inspector->callTool('php-structure', [
            'path' => 'src/Test.php',
            'project' => 'non-existent-project',
        ]);

        // Assert
        $this->assertInspectorSuccess($result); // CLI succeeds
        $this->assertToolError($result);        // But tool returns error
        $this->assertContentContains($result, 'not available');
    }

    /**
     * Assert content does NOT contain string.
     */
    protected function assertContentNotContains(mixed $result, string $needle): void
    {
        $content = $result->getContent() ?? $result->output;

        $this->assertStringNotContainsString(
            $needle,
            $content,
            "Content should not contain: {$needle}",
        );
    }
}
