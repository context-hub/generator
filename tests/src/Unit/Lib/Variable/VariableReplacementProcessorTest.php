<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\Variable;

use Butschster\ContextGenerator\Lib\Variable\Provider\VariableProviderInterface;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(VariableReplacementProcessor::class)]
class VariableReplacementProcessorTest extends TestCase
{
    private VariableProviderInterface $provider;
    private LoggerInterface $logger;

    public static function variableSyntaxProvider(): array
    {
        return [
            'dollar brace syntax' => ['${VAR}', 'VAR'],
            'double brace syntax' => ['{{VAR}}', 'VAR'],
            'dollar brace with underscore' => ['${VAR_NAME}', 'VAR_NAME'],
            'double brace with underscore' => ['{{VAR_NAME}}', 'VAR_NAME'],
            'dollar brace with numbers' => ['${VAR123}', 'VAR123'],
            'double brace with numbers' => ['{{VAR123}}', 'VAR123'],
        ];
    }

    #[Test]
    public function it_should_not_modify_text_without_variables(): void
    {
        $processor = new VariableReplacementProcessor(provider: $this->provider);
        $text = 'This is a simple text without variables';

        $this->assertSame($text, $processor->process(text: $text));
    }

    #[Test]
    public function it_should_replace_dollar_brace_syntax_variables(): void
    {
        // Setup provider mock
        $this->provider->method('has')->with('VAR_NAME')->willReturn(true);
        $this->provider->method('get')->with('VAR_NAME')->willReturn('replacement_value');

        $processor = new VariableReplacementProcessor(provider: $this->provider);
        $text = 'This text has a ${VAR_NAME} variable';

        $expected = 'This text has a replacement_value variable';
        $this->assertSame($expected, $processor->process(text: $text));
    }

    #[Test]
    public function it_should_replace_double_brace_syntax_variables(): void
    {
        // Setup provider mock
        $this->provider->method('has')->with('VAR_NAME')->willReturn(true);
        $this->provider->method('get')->with('VAR_NAME')->willReturn('replacement_value');

        $processor = new VariableReplacementProcessor(provider: $this->provider);
        $text = 'This text has a {{VAR_NAME}} variable';

        $expected = 'This text has a replacement_value variable';
        $this->assertSame($expected, $processor->process(text: $text));
    }

    #[Test]
    public function it_should_replace_multiple_variables(): void
    {
        // Setup provider mock
        $this->provider->method('has')->willReturnMap([
            ['FIRST', true],
            ['SECOND', true],
        ]);

        $this->provider->method('get')->willReturnMap([
            ['FIRST', 'first_value'],
            ['SECOND', 'second_value'],
        ]);

        $processor = new VariableReplacementProcessor(provider: $this->provider);
        $text = 'First: ${FIRST}, Second: {{SECOND}}';

        $expected = 'First: first_value, Second: second_value';
        $this->assertSame($expected, $processor->process(text: $text));
    }

    #[Test]
    public function it_should_keep_unknown_variables_with_original_syntax(): void
    {
        // Setup provider mock to return false for all has() calls
        $this->provider->method('has')->willReturn(false);

        $processor = new VariableReplacementProcessor(provider: $this->provider);
        $text = 'Unknown var: ${UNKNOWN_VAR}';

        // Should keep the original syntax
        $this->assertSame($text, $processor->process(text: $text));
    }

    #[Test]
    public function it_should_handle_empty_replacement(): void
    {
        // Setup provider mock
        $this->provider->method('has')->with('EMPTY_VAR')->willReturn(true);
        $this->provider->method('get')->with('EMPTY_VAR')->willReturn('');

        $processor = new VariableReplacementProcessor(provider: $this->provider);
        $text = 'Empty var: ${EMPTY_VAR}';

        $expected = 'Empty var: ';
        $this->assertSame($expected, $processor->process(text: $text));
    }

    #[Test]
    public function it_should_handle_null_replacement_as_empty_string(): void
    {
        // Setup provider mock
        $this->provider->method('has')->with('NULL_VAR')->willReturn(true);
        $this->provider->method('get')->with('NULL_VAR')->willReturn(null);

        $processor = new VariableReplacementProcessor(provider: $this->provider);
        $text = 'Null var: ${NULL_VAR}';

        $expected = 'Null var: ';
        $this->assertSame($expected, $processor->process(text: $text));
    }

    #[Test]
    public function it_should_log_replacements_when_logger_provided(): void
    {
        // Setup provider mock
        $this->provider->method('has')->with('TEST_VAR')->willReturn(true);
        $this->provider->method('get')->with('TEST_VAR')->willReturn('test_value');

        // Setup logger expectations
        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                'Replacing variable',
                ['name' => 'TEST_VAR', 'value' => 'test_value'],
            );

        $processor = new VariableReplacementProcessor(provider: $this->provider, logger: $this->logger);
        $text = 'Test var: ${TEST_VAR}';

        $processor->process(text: $text);
    }

    #[Test]
    #[DataProvider('variableSyntaxProvider')]
    public function it_should_handle_various_variable_syntax(string $varSyntax, string $name): void
    {
        // Setup provider mock
        $this->provider->method('has')->with($name)->willReturn(true);
        $this->provider->method('get')->with($name)->willReturn('it_works');

        $processor = new VariableReplacementProcessor(provider: $this->provider);
        $text = "Testing {$varSyntax}";

        $expected = 'Testing it_works';
        $this->assertSame($expected, $processor->process(text: $text));
    }

    protected function setUp(): void
    {
        $this->provider = $this->createMock(VariableProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }
}
