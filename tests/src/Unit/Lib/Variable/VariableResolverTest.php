<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\Variable;

use Butschster\ContextGenerator\Lib\Variable\Provider\VariableProviderInterface;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VariableResolver::class)]
class VariableResolverTest extends TestCase
{
    #[Test]
    public function it_should_return_null_for_null_input(): void
    {
        // Create a custom processor to use instead of a mock
        $customProcessor = new VariableReplacementProcessor(
            $this->createCustomProvider(),
        );

        $resolver = new VariableResolver($customProcessor);
        $this->assertNull($resolver->resolve(null));
    }

    #[Test]
    public function it_should_process_string_input(): void
    {
        // Create a resolver with a custom provider that will return "processed string"
        // for any variable request
        $provider = $this->createCustomProvider([
            'VAR' => 'processed string',
        ]);

        $processor = new VariableReplacementProcessor($provider);
        $resolver = new VariableResolver($processor);

        $result = $resolver->resolve('${VAR}');

        $this->assertSame('processed string', $result);
    }

    #[Test]
    public function it_should_process_each_string_in_array_input(): void
    {
        // Create a custom provider with known variables
        $provider = $this->createCustomProvider([
            'VAR' => 'processed',
            'OTHER_VAR' => 'also processed',
        ]);

        $processor = new VariableReplacementProcessor($provider);
        $resolver = new VariableResolver($processor);

        $input = [
            'first ${VAR}',
            'second {{VAR}}',
            'third ${OTHER_VAR}',
        ];

        $expected = [
            'first processed',
            'second processed',
            'third also processed',
        ];

        $this->assertSame($expected, $resolver->resolve($input));
    }

    #[Test]
    public function it_should_handle_nested_arrays(): void
    {
        // Create a custom provider
        $provider = $this->createCustomProvider([
            'VAR' => 'processed',
        ]);

        $processor = new VariableReplacementProcessor($provider);
        $resolver = new VariableResolver($processor);

        $input = [
            'outer ${VAR}',
            'nested' => [
                'inner ${VAR}',
                'another ${VAR}',
            ],
        ];

        $expected = [
            'outer processed',
            'nested' => [
                'inner processed',
                'another processed',
            ],
        ];

        $this->assertSame($expected, $resolver->resolve($input));
    }

    #[Test]
    public function it_should_handle_empty_string(): void
    {
        $provider = $this->createCustomProvider();
        $processor = new VariableReplacementProcessor($provider);
        $resolver = new VariableResolver($processor);

        $this->assertSame('', $resolver->resolve(''));
    }

    #[Test]
    public function it_should_handle_empty_array(): void
    {
        $provider = $this->createCustomProvider();
        $processor = new VariableReplacementProcessor($provider);
        $resolver = new VariableResolver($processor);

        $this->assertSame([], $resolver->resolve([]));
    }

    #[Test]
    public function it_should_pass_array_values_to_processor(): void
    {
        // Create a provider that will prefix all variable values with "processed:"
        $provider = new class implements VariableProviderInterface {
            public function has(string $name): bool
            {
                return $name === 'PREFIX';
            }

            public function get(string $name): ?string
            {
                return 'processed:';
            }
        };

        $processor = new VariableReplacementProcessor($provider);
        $resolver = new VariableResolver($processor);

        $input = [
            '${PREFIX}first',
            '${PREFIX}second',
            '${PREFIX}third',
        ];

        $expected = [
            'processed:first',
            'processed:second',
            'processed:third',
        ];

        $this->assertSame($expected, $resolver->resolve($input));
    }

    /**
     * Create a custom provider with predefined variables for testing
     *
     * @param array<string, string> $variables Variables to include in the provider
     */
    private function createCustomProvider(array $variables = []): VariableProviderInterface
    {
        return new class($variables) implements VariableProviderInterface {
            /**
             * @param array<string, string> $variables
             */
            public function __construct(private array $variables = []) {}

            public function has(string $name): bool
            {
                return isset($this->variables[$name]);
            }

            public function get(string $name): ?string
            {
                return $this->variables[$name] ?? null;
            }
        };
    }
}
