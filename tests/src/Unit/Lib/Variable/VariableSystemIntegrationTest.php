<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\Variable;

use Butschster\ContextGenerator\Lib\Variable\Provider\CompositeVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\PredefinedVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\VariableProviderInterface;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VariableSystemIntegrationTest extends TestCase
{
    private VariableProviderInterface $customProvider;
    private VariableResolver $resolver;

    #[Test]
    public function it_should_resolve_variables_in_simple_string(): void
    {
        $input = 'Project: ${PROJECT_NAME}, Version: ${VERSION}';
        $expected = 'Project: context-generator, Version: 1.0.0';

        $result = $this->resolver->resolve($input);

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function it_should_resolve_variables_with_different_syntax(): void
    {
        $input = 'Project: ${PROJECT_NAME}, Version: {{VERSION}}';
        $expected = 'Project: context-generator, Version: 1.0.0';

        $result = $this->resolver->resolve($input);

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function it_should_resolve_system_variables(): void
    {
        $input = 'Current date: ${DATE}, OS: ${OS}';

        $result = $this->resolver->resolve($input);

        // Verify system variables were replaced
        $this->assertStringContainsString('Current date: ' . \date('Y-m-d'), $result);
        $this->assertStringContainsString('OS: ' . PHP_OS, $result);
    }

    #[Test]
    public function it_should_resolve_variables_in_array(): void
    {
        $input = [
            'project' => '${PROJECT_NAME}',
            'version' => '${VERSION}',
            'date' => '${DATE}',
            'nested' => [
                'test' => '${TEST_VAR}',
                'emptyVar' => '${EMPTY_VAR}',
                'unknownVar' => '${UNKNOWN_VAR}',
            ],
        ];

        $result = $this->resolver->resolve($input);

        $this->assertSame('context-generator', $result['project']);
        $this->assertSame('1.0.0', $result['version']);
        $this->assertSame(\date('Y-m-d'), $result['date']);
        $this->assertSame('test_value', $result['nested']['test']);
        $this->assertSame('', $result['nested']['emptyVar']);
        $this->assertSame('${UNKNOWN_VAR}', $result['nested']['unknownVar']);
    }

    #[Test]
    public function it_should_prioritize_custom_variables_over_system_variables(): void
    {
        // Add a custom provider with a variable that overrides a system one
        $customProviderWithOverride = new class implements VariableProviderInterface {
            public function has(string $name): bool
            {
                return $name === 'DATE';
            }

            public function get(string $name): ?string
            {
                if ($name === 'DATE') {
                    return 'CUSTOM_DATE_VALUE';
                }
                return null;
            }
        };

        // Create a new composite provider with our override provider at highest priority
        $compositeProvider = new CompositeVariableProvider(
            $customProviderWithOverride,          // Highest priority
            $this->customProvider,               // Middle priority
            new PredefinedVariableProvider(),    // Lowest priority
        );

        $processor = new VariableReplacementProcessor($compositeProvider);
        $resolver = new VariableResolver($processor);

        $result = $resolver->resolve('Date: ${DATE}');

        // Should use our custom DATE value, not the system one
        $this->assertSame('Date: CUSTOM_DATE_VALUE', $result);
    }

    #[Test]
    public function it_should_leave_unknown_variables_untouched(): void
    {
        $input = 'Unknown: ${DOES_NOT_EXIST}, Known: ${TEST_VAR}';
        $expected = 'Unknown: ${DOES_NOT_EXIST}, Known: test_value';

        $result = $this->resolver->resolve($input);

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function it_should_handle_complex_text_with_multiple_variables(): void
    {
        $input = <<<'TEXT'
            # ${PROJECT_NAME} v${VERSION}
            
            Current date: ${DATE}
            Environment: ${OS}
            
            ## Configuration
            
            - Test variable: ${TEST_VAR}
            - Non-existent: ${NON_EXISTENT}
            - Using double braces: {{PROJECT_NAME}}
            
            ## Contact
            
            User: ${USER}
            TEXT;

        $result = $this->resolver->resolve($input);

        // Check that all variables were properly replaced
        $this->assertStringContainsString('Current date: ' . \date('Y-m-d'), $result);
        $this->assertStringContainsString('Environment: ' . PHP_OS, $result);
        $this->assertStringContainsString('Test variable: test_value', $result);
        $this->assertStringContainsString('Non-existent: ${NON_EXISTENT}', $result);
        $this->assertStringContainsString('Using double braces: context-generator', $result);

        // USER should be resolved from the system provider
        $this->assertStringNotContainsString('User: ${USER}', $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create a custom test provider with some test variables
        $this->customProvider = new class implements VariableProviderInterface {
            private array $variables = [
                'TEST_VAR' => 'test_value',
                'PROJECT_NAME' => 'context-generator',
                'VERSION' => '1.0.0',
                'EMPTY_VAR' => '',
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

        // Create a composite provider with predefined variables and our custom provider
        $compositeProvider = new CompositeVariableProvider(
            new PredefinedVariableProvider(),  // Lower priority (system variables)
            $this->customProvider,              // Higher priority (custom variables)
        );

        // Create processor with composite provider
        $processor = new VariableReplacementProcessor($compositeProvider);

        // Create resolver with processor
        $this->resolver = new VariableResolver($processor);
    }
}
