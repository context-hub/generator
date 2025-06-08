<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\Variable;

use Butschster\ContextGenerator\Lib\Variable\Provider\VariableProviderInterface;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VariableSystemEdgeCasesTest extends TestCase
{
    public static function specialCharactersProvider(): array
    {
        return [
            'backslash' => ['value with \\ backslash'],
            'dollar sign' => ['value with $ dollar'],
            'curly braces' => ['value with {} curly braces'],
            'regex special chars' => ['value with []*+?|$^()'],
            'html tags' => ['value with <b>HTML</b> tags'],
            'newlines' => ["value with \n newlines"],
            'tabs' => ["value with \t tabs"],
            'quotes' => ['value with "double" and \'single\' quotes'],
            'unicode' => ['value with Unicode: 你好, こんにちは, 안녕하세요'],
            'emoji' => ['value with emoji: 🚀✨🔥🌈'],
        ];
    }

    #[Test]
    public function it_should_handle_deeply_nested_variable_references(): void
    {
        // Create a provider where variables can reference other variables
        $nestedProvider = new class implements VariableProviderInterface {
            private array $variables = [
                'LEVEL1' => 'value1',
                'LEVEL2' => 'uses ${LEVEL1}',
                'LEVEL3' => 'references ${LEVEL2}',
                'CIRCULAR1' => '${CIRCULAR2}',
                'CIRCULAR2' => '${CIRCULAR1}',
            ];

            public function has(string $name): bool
            {
                return isset($this->variables[$name]);
            }

            public function get(string $name): ?string
            {
                return $this->variables[$name] ?? null;
            }
        };

        $processor = new VariableReplacementProcessor($nestedProvider);
        $resolver = new VariableResolver($processor);

        // Test case for nested variables - these are not automatically resolved recursively
        // since the processor doesn't recursively process
        $result = $resolver->resolve('${LEVEL3}');

        // Should only resolve one level: "${LEVEL3}" -> "references ${LEVEL2}"
        $this->assertSame('references ${LEVEL2}', $result);

        // If we process it a second time, we get one level deeper
        $result = $resolver->resolve($result);
        $this->assertSame('references uses ${LEVEL1}', $result);

        // And once more to fully resolve
        $result = $resolver->resolve($result);
        $this->assertSame('references uses value1', $result);
    }

    #[Test]
    public function it_should_handle_circular_references_without_infinite_loop(): void
    {
        // Create a provider with circular references
        $circularProvider = new class implements VariableProviderInterface {
            private array $variables = [
                'CIRCULAR1' => '${CIRCULAR2}',
                'CIRCULAR2' => '${CIRCULAR1}',
            ];

            public function has(string $name): bool
            {
                return isset($this->variables[$name]);
            }

            public function get(string $name): ?string
            {
                return $this->variables[$name] ?? null;
            }
        };

        $processor = new VariableReplacementProcessor($circularProvider);
        $resolver = new VariableResolver($processor);

        // With circular references, each resolution replaces one level
        $result = $resolver->resolve('${CIRCULAR1}');
        $this->assertSame('${CIRCULAR2}', $result);

        // Second level replaces to the other circular var
        $result = $resolver->resolve($result);
        $this->assertSame('${CIRCULAR1}', $result);

        // You could potentially get in an infinite loop if you keep reprocessing,
        // but the resolver itself doesn't have recursion, so it's safe
    }

    #[Test]
    #[DataProvider('specialCharactersProvider')]
    public function it_should_handle_variables_with_special_characters_in_values(string $value): void
    {
        // Create a provider with values containing special characters
        $specialCharsProvider = new readonly class($value) implements VariableProviderInterface {
            public function __construct(private readonly string $testValue) {}

            public function has(string $name): bool
            {
                return $name === 'SPECIAL';
            }

            public function get(string $name): ?string
            {
                if ($name === 'SPECIAL') {
                    return $this->testValue;
                }
                return null;
            }
        };

        $processor = new VariableReplacementProcessor($specialCharsProvider);
        $resolver = new VariableResolver($processor);

        $result = $resolver->resolve('Value: ${SPECIAL}');
        $expected = 'Value: ' . $value;

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function it_should_handle_adjacent_variables(): void
    {
        $provider = new class implements VariableProviderInterface {
            private array $variables = [
                'FIRST' => 'first',
                'SECOND' => 'second',
                'THIRD' => 'third',
            ];

            public function has(string $name): bool
            {
                return isset($this->variables[$name]);
            }

            public function get(string $name): ?string
            {
                return $this->variables[$name] ?? null;
            }
        };

        $processor = new VariableReplacementProcessor($provider);
        $resolver = new VariableResolver($processor);

        // Test adjacent variables
        $result = $resolver->resolve('${FIRST}${SECOND}${THIRD}');
        $this->assertSame('firstsecondthird', $result);

        // Test mixed syntax adjacent variables
        $result = $resolver->resolve('${FIRST}{{SECOND}}${THIRD}');
        $this->assertSame('firstsecondthird', $result);
    }

    #[Test]
    public function it_should_handle_malformed_variable_syntax(): void
    {
        $provider = new class implements VariableProviderInterface {
            private array $variables = [
                'VAR' => 'value',
            ];

            public function has(string $name): bool
            {
                return isset($this->variables[$name]);
            }

            public function get(string $name): ?string
            {
                return $this->variables[$name] ?? null;
            }
        };

        $processor = new VariableReplacementProcessor($provider);
        $resolver = new VariableResolver($processor);

        // Test various malformed variable patterns
        $testCases = [
            // Unclosed variable references
            '${VAR' => '${VAR',
            '{{VAR' => '{{VAR',

            // Empty variable names
            '${}' => '${}',
            '{{}}' => '{{}}',

            // Partial variable pattern
            '$VAR}' => '$VAR}',
            '{VAR}' => '{VAR}',

            // Nested braces (not supported)
            '${VAR${NESTED}}' => '${VAR${NESTED}}',

            // Valid variable mixed with invalid
            '${VAR} and ${INVALID' => 'value and ${INVALID',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $resolver->resolve($input);
            $this->assertSame($expected, $result, "Failed for input: $input");
        }
    }
}
