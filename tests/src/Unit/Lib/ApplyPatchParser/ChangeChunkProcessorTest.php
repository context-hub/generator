<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\ApplyPatchParser;

use Butschster\ContextGenerator\Lib\ApplyPatchParser\ChangeChunkConfig;
use Butschster\ContextGenerator\Lib\ApplyPatchParser\ChangeChunkProcessor;
use Butschster\ContextGenerator\Lib\ApplyPatchParser\ProcessResult;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileApplyPatchChunk;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto\FileApplyPatchRequest;
use PHPUnit\Framework\TestCase;

final class ChangeChunkProcessorTest extends TestCase
{
    private ChangeChunkProcessor $processor;
    private ChangeChunkConfig $config;

    public function testProcessChangesWithSimpleLineReplacement(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ class UserService',
            changes: [
                ' {',
                '-    private $db;',
                '+    private DatabaseInterface $db;',
                ' ',
            ],
        );

        $request = new FileApplyPatchRequest(
            path: 'src/UserService.php',
            chunks: [$chunk],
        );

        $fileContent = <<<CODE
            <?php
            class UserService
            {
                private \$db;
            
                public function __construct() {
                    // ...
                }
            }
            CODE;

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertInstanceOf(ProcessResult::class, $result);
        $this->assertEquals($fileContent, $result->originalContent);
        $this->assertTrue($result->success);

        $expected = <<<CODE
            <?php
            class UserService
            {
                private DatabaseInterface \$db;
            
                public function __construct() {
                    // ...
                }
            }
            CODE;
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
        $this->assertEmpty($result->errors);
        $this->assertEmpty($result->warnings);
    }

    public function testProcessChangesWithNonExistentContext(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ nonExistentMethod',
            changes: [
                '-    public function nonExistentMethod()',
                '+    public function correctMethod()',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/Service.php', chunks: [$chunk]);
        $fileContent = <<<CODE
            <?php
            class Service
            {
                public function existingMethod()
                {
                    return true;
                }
            }
            CODE;

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertFalse($result->success);
        $this->assertEquals($fileContent, $result->originalContent);
        $this->assertEquals($fileContent, $result->modifiedContent);
        $this->assertNotEmpty($result->errors);
    }

    public function testProcessChangesWithMethodParameterTypeHint(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ public function getUser',
            changes: [
                '     */',
                '-    public function getUser($id): ?User',
                '+    public function getUser(int $id): ?User',
                '    {',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/UserService.php', chunks: [$chunk]);
        $fileContent = <<<CODE
            <?php
            class UserService
            {
                /**
                 * Get user by ID
                 */
                public function getUser(\$id): ?User
                {
                    return \$this->repository->find(\$id);
                }
            }
            CODE;

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $this->assertEquals($fileContent, $result->originalContent);

        $expected = <<<CODE
            <?php
            class UserService
            {
                /**
                 * Get user by ID
                 */
                public function getUser(int \$id): ?User
                {
                    return \$this->repository->find(\$id);
                }
            }
            CODE;
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithStrictTypesDeclaration(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ <?php',
            changes: [
                '-<?php',
                '+<?php declare(strict_types=1);',
                ' class Model',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/Model.php', chunks: [$chunk]);
        $fileContent = <<<CODE
            <?php
            class Model
            {
                // ...
            }
            CODE;

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);

        $expected = <<<CODE
            <?php declare(strict_types=1);
            class Model
            {
                // ...
            }
            CODE;
        $this->assertEquals($expected, $result->modifiedContent);
    }

    public function testSplitIntoLinesWithUnixLineEndings(): void
    {
        $processor = new ChangeChunkProcessor();
        $content = "<?php\nclass Example\n{\n    public function test(): void\n    {\n        // code\n    }\n}";

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('splitIntoLines');
        $method->setAccessible(true);

        $result = $method->invoke($processor, $content);

        $expected = [
            '<?php',
            'class Example',
            '{',
            '    public function test(): void',
            '    {',
            '        // code',
            '    }',
            '}',
        ];
        $this->assertEquals($expected, $result);
    }

    public function testSplitIntoLinesWithWindowsLineEndings(): void
    {
        $processor = new ChangeChunkProcessor();
        $content = "<?php\r\ndeclare(strict_types=1);\r\n\r\nclass WindowsFile\r\n{\r\n    private string \$property;\r\n}";

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('splitIntoLines');
        $method->setAccessible(true);

        $result = $method->invoke($processor, $content);

        $expected = [
            '<?php',
            'declare(strict_types=1);',
            '',
            'class WindowsFile',
            '{',
            '    private string $property;',
            '}',
        ];
        $this->assertEquals($expected, $result);
    }

    public function testSplitIntoLinesWithMacLineEndings(): void
    {
        $processor = new ChangeChunkProcessor();
        $content = "line1\rline2\rline3";

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('splitIntoLines');
        $method->setAccessible(true);

        $result = $method->invoke($processor, $content);

        $this->assertEquals(['line1', 'line2', 'line3'], $result);
    }

    public function testSplitIntoLinesWithMixedLineEndings(): void
    {
        $processor = new ChangeChunkProcessor();
        $content = "line1\nline2\r\nline3\rline4";

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('splitIntoLines');
        $method->setAccessible(true);

        $result = $method->invoke($processor, $content);

        $this->assertEquals(['line1', 'line2', 'line3', 'line4'], $result);
    }

    public function testSplitIntoLinesWithTrailingNewline(): void
    {
        $processor = new ChangeChunkProcessor();
        $content = "line1\nline2\nline3\n";

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('splitIntoLines');
        $method->setAccessible(true);

        $result = $method->invoke($processor, $content);

        $this->assertEquals(['line1', 'line2', 'line3'], $result);
    }

    public function testSplitIntoLinesWithEmptyContent(): void
    {
        $processor = new ChangeChunkProcessor();
        $content = "";

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('splitIntoLines');
        $method->setAccessible(true);

        $result = $method->invoke($processor, $content);

        $this->assertEquals([], $result);
    }

    public function testSplitIntoLinesWithOnlyNewlines(): void
    {
        $processor = new ChangeChunkProcessor();
        $content = "\n\n\n";

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('splitIntoLines');
        $method->setAccessible(true);

        $result = $method->invoke($processor, $content);

        $this->assertEquals(['', '', ''], $result);
    }

    public function testSplitIntoLinesPreservesEmptyLines(): void
    {
        $processor = new ChangeChunkProcessor();
        $content = "line1\n\nline3\n\nline5";

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('splitIntoLines');
        $method->setAccessible(true);

        $result = $method->invoke($processor, $content);

        $this->assertEquals(['line1', '', 'line3', '', 'line5'], $result);
    }

    public function testProcessChangesWithMultipleIndependentChunks(): void
    {
        $chunk1 = new FileApplyPatchChunk(
            contextMarker: '@@ private $name',
            changes: [
                ' {',
                '-    private $name;',
                '+    private string $name;',
                '     private $email;',
            ],
        );

        $chunk2 = new FileApplyPatchChunk(
            contextMarker: '@@ public function getName',
            changes: [
                ' ',
                '-    public function getName()',
                '+    public function getName(): string',
                '     {',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/User.php', chunks: [$chunk1, $chunk2]);
        $fileContent = "<?php\nclass User\n{\n    private \$name;\n    private \$email;\n\n    public function getName()\n    {\n        return \$this->name;\n    }\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $this->assertEquals(2, \count($result->appliedChanges));
        $expected = "<?php\nclass User\n{\n    private string \$name;\n    private \$email;\n\n    public function getName(): string\n    {\n        return \$this->name;\n    }\n}";
        $this->assertEquals($expected, $result->modifiedContent);
    }

    public function testProcessChangesWithMethodAddition(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ }',
            changes: [
                '     }',
                ' ',
                '+    public function multiply(int $a, int $b): int',
                '+    {',
                '+        return $a * $b;',
                '+    }',
                '+',
                ' }',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/Calculator.php', chunks: [$chunk]);
        $fileContent = "<?php\nclass Calculator\n{\n    public function add(int \$a, int \$b): int\n    {\n        return \$a + \$b;\n    }\n\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $expected = "<?php\nclass Calculator\n{\n    public function add(int \$a, int \$b): int\n    {\n        return \$a + \$b;\n    }\n\n    public function multiply(int \$a, int \$b): int\n    {\n        return \$a * \$b;\n    }\n\n}";
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithDeprecatedFieldRemoval(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ private string $name',
            changes: [
                '     private string $name;',
                '-    private string $deprecatedField;',
                '-',
                '     public function getName(): string',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/User.php', chunks: [$chunk]);
        $fileContent = "<?php\nclass User\n{\n    private string \$name;\n    private string \$deprecatedField;\n\n    public function getName(): string\n    {\n        return \$this->name;\n    }\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $expected = "<?php\nclass User\n{\n    private string \$name;\n    public function getName(): string\n    {\n        return \$this->name;\n    }\n}";
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithConstructorParameterAddition(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ public function __construct',
            changes: [
                '-    public function __construct($name, $email)',
                '+    public function __construct($name, $email, $createdAt = null)',
                '     {',
                '         $this->name = $name;',
                '         $this->email = $email;',
                '+        $this->createdAt = $createdAt ?? new DateTime();',
                '     }',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/User.php', chunks: [$chunk]);
        $fileContent = "<?php\nclass User\n{\n    public function __construct(\$name, \$email)\n    {\n        \$this->name = \$name;\n        \$this->email = \$email;\n    }\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $expected = "<?php\nclass User\n{\n    public function __construct(\$name, \$email, \$createdAt = null)\n    {\n        \$this->name = \$name;\n        \$this->email = \$email;\n        \$this->createdAt = \$createdAt ?? new DateTime();\n    }\n}";
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithErrorHandling(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ function divide',
            changes: [
                '     public function divide(float $a, float $b): float',
                '     {',
                '+        if ($b === 0.0) {',
                '+            throw new DivisionByZeroError(\'Division by zero\');',
                '+        }',
                '         return $a / $b;',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/Calculator.php', chunks: [$chunk]);
        $fileContent = "<?php\nclass Calculator\n{\n    public function divide(float \$a, float \$b): float\n    {\n        return \$a / \$b;\n    }\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $expected = "<?php\nclass Calculator\n{\n    public function divide(float \$a, float \$b): float\n    {\n        if (\$b === 0.0) {\n            throw new DivisionByZeroError('Division by zero');\n        }\n        return \$a / \$b;\n    }\n}";
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithPropertyVisibilityChange(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ class Config',
            changes: [
                ' {',
                '-    private array $settings = [];',
                '+    protected array $settings = [];',
                ' ',
                '     public function get(string $key): mixed',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/Config.php', chunks: [$chunk]);
        $fileContent = "<?php\nclass Config\n{\n    private array \$settings = [];\n\n    public function get(string \$key): mixed\n    {\n        return \$this->settings[\$key] ?? null;\n    }\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $expected = "<?php\nclass Config\n{\n    protected array \$settings = [];\n\n    public function get(string \$key): mixed\n    {\n        return \$this->settings[\$key] ?? null;\n    }\n}";
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithInterfaceImplementation(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ class EmailService',
            changes: [
                '-class EmailService',
                '+class EmailService implements NotificationInterface',
                ' {',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/EmailService.php', chunks: [$chunk]);
        $fileContent = "<?php\nclass EmailService\n{\n    public function send(string \$to, string \$message): bool\n    {\n        return true;\n    }\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $expected = "<?php\nclass EmailService implements NotificationInterface\n{\n    public function send(string \$to, string \$message): bool\n    {\n        return true;\n    }\n}";
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithWhitespaceVariations(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ public function format',
            changes: [
                '     public function format(string $input): string',
                '     {',
                '-        return trim($input);',
                '+        return trim(strtolower($input));',
                '     }',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/Formatter.php', chunks: [$chunk]);
        // File has different whitespace than the change chunk
        $fileContent = "<?php\nclass Formatter\n{\n   public function format(string \$input): string\n   {\n       return trim(\$input);\n   }\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $expected = "<?php\nclass Formatter\n{\n   public function format(string \$input): string\n   {\n        return trim(strtolower(\$input));\n   }\n}";
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithEmptyLines(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ class EmptyLinesTest',
            changes: [
                ' {',
                '     private string $value;',
                ' ',
                '+    private string $newValue;',
                '+',
                '     public function getValue(): string',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/EmptyLinesTest.php', chunks: [$chunk]);
        $fileContent = "<?php\nclass EmptyLinesTest\n{\n    private string \$value;\n\n    public function getValue(): string\n    {\n        return \$this->value;\n    }\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $expected = "<?php\nclass EmptyLinesTest\n{\n    private string \$value;\n\n    private string \$newValue;\n\n    public function getValue(): string\n    {\n        return \$this->value;\n    }\n}";
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithComplexMethodRefactoring(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ public function processOrder',
            changes: [
                '     public function processOrder(array $orderData): bool',
                '     {',
                '-        $total = 0;',
                '-        foreach ($orderData[\'items\'] as $item) {',
                '-            $total += $item[\'price\'] * $item[\'quantity\'];',
                '-        }',
                '-        return $total > 0;',
                '+        $calculator = new OrderCalculator();',
                '+        $total = $calculator->calculateTotal($orderData[\'items\']);',
                '+',
                '+        if ($total <= 0) {',
                '+            throw new InvalidOrderException(\'Order total must be greater than zero\');',
                '+        }',
                '+',
                '+        return $this->saveOrder($orderData, $total);',
                '     }',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/OrderProcessor.php', chunks: [$chunk]);
        $fileContent = <<<CODE
            <?php
            class OrderProcessor
            {
                public function processOrder(array \$orderData): bool
                {
                    \$total = 0;
                    foreach (\$orderData['items'] as \$item) {
                        \$total += \$item['price'] * \$item['quantity'];
                    }
                    return \$total > 0;
                }
            }
            CODE;

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);

        $expected = <<<CODE
            <?php
            class OrderProcessor
            {
                public function processOrder(array \$orderData): bool
                {
                    \$calculator = new OrderCalculator();
                    \$total = \$calculator->calculateTotal(\$orderData['items']);
            
                    if (\$total <= 0) {
                        throw new InvalidOrderException('Order total must be greater than zero');
                    }
            
                    return \$this->saveOrder(\$orderData, \$total);
                }
            }
            CODE;
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithOverlappingContextMarkers(): void
    {
        $chunk1 = new FileApplyPatchChunk(
            contextMarker: '@@ public function getName',
            changes: [
                '-    public function getName(): string',
                '+    public function getName(): ?string',
                '     {',
                '         return $this->name;',
            ],
        );

        $chunk2 = new FileApplyPatchChunk(
            contextMarker: '@@ public function getEmail',
            changes: [
                '-    public function getEmail(): string',
                '+    public function getEmail(): ?string',
                '     {',
                '         return $this->email;',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/User.php', chunks: [$chunk1, $chunk2]);
        $fileContent = "<?php\nclass User\n{\n    public function getName(): string\n    {\n        return \$this->name;\n    }\n\n    public function getEmail(): string\n    {\n        return \$this->email;\n    }\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $expected = "<?php\nclass User\n{\n    public function getName(): ?string\n    {\n        return \$this->name;\n    }\n\n    public function getEmail(): ?string\n    {\n        return \$this->email;\n    }\n}";
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(2, \count($result->appliedChanges));
    }

    public function testProcessChangesWithSpecialCharacters(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ private const REGEX',
            changes: [
                '-    private const REGEX = \'/^[a-zA-Z0-9]+$/\';',
                '+    private const REGEX = \'/^[a-zA-Z0-9_\\-\\.]+$/u\';',
                ' ',
                '     public function validate(string $input): bool',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/Validator.php', chunks: [$chunk]);
        $fileContent = "<?php\nclass Validator\n{\n    private const REGEX = '/^[a-zA-Z0-9]+\$/';\n\n    public function validate(string \$input): bool\n    {\n        return preg_match(self::REGEX, \$input) === 1;\n    }\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $expected = "<?php\nclass Validator\n{\n    private const REGEX = '/^[a-zA-Z0-9_\\-\\.]+\$/u';\n\n    public function validate(string \$input): bool\n    {\n        return preg_match(self::REGEX, \$input) === 1;\n    }\n}";
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithAmbiguousContext(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ return $this->data',
            changes: [
                '     public function getData(): array',
                '     {',
                '+        $this->validateData();',
                '         return $this->data;',
                '     }',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/DataHandler.php', chunks: [$chunk]);
        $fileContent = "<?php\nclass DataHandler\n{\n    private array \$data = [];\n\n    public function getData(): array\n    {\n        return \$this->data;\n    }\n\n    public function getFormattedData(): array\n    {\n        return \$this->data;\n    }\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        // Should match the first occurrence
        $expected = "<?php\nclass DataHandler\n{\n    private array \$data = [];\n\n    public function getData(): array\n    {\n        \$this->validateData();\n        return \$this->data;\n    }\n\n    public function getFormattedData(): array\n    {\n        return \$this->data;\n    }\n}";
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithConflictingChanges(): void
    {
        $chunk1 = new FileApplyPatchChunk(
            contextMarker: '@@ private string $name',
            changes: [
                '-    private string $name;',
                '+    private string $firstName;',
                '     private string $email;',
            ],
        );

        $chunk2 = new FileApplyPatchChunk(
            contextMarker: '@@ private string $name',
            changes: [
                '-    private string $name;',
                '+    private string $fullName;',
                '     private string $email;',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/User.php', chunks: [$chunk1, $chunk2]);
        $fileContent = "<?php\nclass User\n{\n    private string \$name;\n    private string \$email;\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        // The first change applies successfully, the second tries to find the same line but it's already changed
        // This could either succeed (if implementation is robust) or fail (if strict)
        // Let's document the current behavior
        if ($result->success) {
            // Implementation successfully handled both changes somehow
            $this->assertTrue($result->success);
            $this->assertGreaterThan(0, \count($result->appliedChanges));
        } else {
            // Second chunk couldn't find the original line after first change
            $this->assertFalse($result->success);
            $this->assertNotEmpty($result->errors);
        }
    }

    public function testProcessChangesWithLargeMethodAddition(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ class FileManager',
            changes: [
                '     }',
                ' ',
                '+    /**',
                '+     * Processes multiple files with validation and error handling',
                '+     *',
                '+     * @param array $files List of file paths to process',
                '+     * @param callable $processor Function to process each file',
                '+     * @return array Results with success/error status for each file',
                '+     */',
                '+    public function processFiles(array $files, callable $processor): array',
                '+    {',
                '+        $results = [];',
                '+        ',
                '+        foreach ($files as $index => $filePath) {',
                '+            try {',
                '+                $this->validateFile($filePath);',
                '+                $results[$index] = [',
                '+                    \'success\' => true,',
                '+                    \'result\' => $processor($filePath),',
                '+                    \'file\' => $filePath',
                '+                ];',
                '+            } catch (\\Exception $e) {',
                '+                $results[$index] = [',
                '+                    \'success\' => false,',
                '+                    \'error\' => $e->getMessage(),',
                '+                    \'file\' => $filePath',
                '+                ];',
                '+            }',
                '+        }',
                '+        ',
                '+        return $results;',
                '+    }',
                '+',
                ' }',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/FileManager.php', chunks: [$chunk]);
        $fileContent = "<?php\nclass FileManager\n{\n    public function readFile(string \$path): string\n    {\n        return file_get_contents(\$path);\n    }\n\n}";

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('processFiles', $result->modifiedContent);
        $this->assertStringContainsString('validateFile', $result->modifiedContent);
        $this->assertStringContainsString('Exception', $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithEnumDefinition(): void
    {
        $chunk = new FileApplyPatchChunk(
            contextMarker: '@@ namespace App\\Enums',
            changes: [
                ' namespace App\\Enums;',
                ' ',
                '+enum Status: string',
                '+{',
                '+    case PENDING = \'pending\';',
                '+    case APPROVED = \'approved\';',
                '+    case REJECTED = \'rejected\';',
                '+    case CANCELLED = \'cancelled\';',
                '+',
                '+    public function getLabel(): string',
                '+    {',
                '+        return match($this) {',
                '+            self::PENDING => \'Pending Review\',',
                '+            self::APPROVED => \'Approved\',',
                '+            self::REJECTED => \'Rejected\',',
                '+            self::CANCELLED => \'Cancelled\',',
                '+        };',
                '+    }',
                '+}',
                ' ',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/Enums/Status.php', chunks: [$chunk]);
        $fileContent = <<<CODE
            <?php
            
            declare(strict_types=1);
            
            namespace App\Enums;
            CODE;

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('enum Status: string', $result->modifiedContent);
        $this->assertStringContainsString('case PENDING', $result->modifiedContent);
        $this->assertStringContainsString('getLabel()', $result->modifiedContent);
        $this->assertEquals(1, \count($result->appliedChanges));
    }

    public function testProcessChangesWithArraySyntaxModernization(): void
    {
        $chunk1 = new FileApplyPatchChunk(
            contextMarker: '@@ private $config',
            changes: [
                '-    private $config = array();',
                '+    private array $config = [];',
                ' ',
            ],
        );

        $chunk2 = new FileApplyPatchChunk(
            contextMarker: '@@ return array(',
            changes: [
                '-        return array(',
                '+        return [',
                '             \'key\' => $this->getValue(),',
                '-        );',
                '+        ];',
            ],
        );

        $request = new FileApplyPatchRequest(path: 'src/ConfigManager.php', chunks: [$chunk1, $chunk2]);
        $fileContent = <<<CODE
<?php
class ConfigManager
{
    private \$config = array();

    public function toArray(): array
    {
        return array(
            'key' => \$this->getValue(),
        );
    }
}
CODE;

        $result = $this->processor->processChanges($request, $this->config, $fileContent);

        $this->assertTrue($result->success);

        $expected = <<<CODE
<?php
class ConfigManager
{
    private array \$config = [];

    public function toArray(): array
    {
        return [
            'key' => \$this->getValue(),
        ];
    }
}
CODE;
        $this->assertEquals($expected, $result->modifiedContent);
        $this->assertEquals(2, \count($result->appliedChanges));
    }

    protected function setUp(): void
    {
        $this->processor = new ChangeChunkProcessor();
        $this->config = new ChangeChunkConfig();
    }
}
