<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\Variable;

use Butschster\ContextGenerator\Lib\Variable\Provider\VariableProviderInterface;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
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
        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'This is a simple text without variables';

        $this->assertSame($text, $processor->process($text));
    }

    #[Test]
    public function it_should_replace_dollar_brace_syntax_variables(): void
    {
        // Setup provider mock
        $this->provider->method('has')->with('VAR_NAME')->willReturn(true);
        $this->provider->method('get')->with('VAR_NAME')->willReturn('replacement_value');

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'This text has a ${VAR_NAME} variable';

        $expected = 'This text has a replacement_value variable';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_replace_double_brace_syntax_variables(): void
    {
        // Setup provider mock
        $this->provider->method('has')->with('VAR_NAME')->willReturn(true);
        $this->provider->method('get')->with('VAR_NAME')->willReturn('replacement_value');

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'This text has a {{VAR_NAME}} variable';

        $expected = 'This text has a replacement_value variable';
        $this->assertSame($expected, $processor->process($text));
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

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'First: ${FIRST}, Second: {{SECOND}}';

        $expected = 'First: first_value, Second: second_value';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_keep_unknown_variables_with_original_syntax(): void
    {
        // Setup provider mock to return false for all has() calls
        $this->provider->method('has')->willReturn(false);

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Unknown var: ${UNKNOWN_VAR}';

        // Should keep the original syntax
        $this->assertSame($text, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_empty_replacement(): void
    {
        // Setup provider mock
        $this->provider->method('has')->with('EMPTY_VAR')->willReturn(true);
        $this->provider->method('get')->with('EMPTY_VAR')->willReturn('');

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Empty var: ${EMPTY_VAR}';

        $expected = 'Empty var: ';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_null_replacement_as_empty_string(): void
    {
        // Setup provider mock
        $this->provider->method('has')->with('NULL_VAR')->willReturn(true);
        $this->provider->method('get')->with('NULL_VAR')->willReturn(null);

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Null var: ${NULL_VAR}';

        $expected = 'Null var: ';
        $this->assertSame($expected, $processor->process($text));
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

        $processor = new VariableReplacementProcessor($this->provider, $this->logger);
        $text = 'Test var: ${TEST_VAR}';

        $processor->process($text);
    }

    #[Test]
    #[DataProvider('variableSyntaxProvider')]
    public function it_should_handle_various_variable_syntax(string $varSyntax, string $name): void
    {
        // Setup provider mock
        $this->provider->method('has')->with($name)->willReturn(true);
        $this->provider->method('get')->with($name)->willReturn('it_works');

        $processor = new VariableReplacementProcessor($this->provider);
        $text = "Testing {$varSyntax}";

        $expected = 'Testing it_works';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_use_default_value_when_variable_not_found_dollar_brace(): void
    {
        $this->provider->method('has')->with('MISSING_VAR')->willReturn(false);

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Value: ${MISSING_VAR:-fallback_value}';

        $expected = 'Value: fallback_value';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_use_default_value_when_variable_not_found_double_brace(): void
    {
        $this->provider->method('has')->with('MISSING_VAR')->willReturn(false);

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Value: {{MISSING_VAR:-fallback_value}}';

        $expected = 'Value: fallback_value';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_ignore_default_value_when_variable_exists(): void
    {
        $this->provider->method('has')->with('EXISTING_VAR')->willReturn(true);
        $this->provider->method('get')->with('EXISTING_VAR')->willReturn('actual_value');

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Value: ${EXISTING_VAR:-fallback_value}';

        $expected = 'Value: actual_value';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_empty_default_value(): void
    {
        $this->provider->method('has')->with('MISSING_VAR')->willReturn(false);

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Value: ${MISSING_VAR:-}end';

        $expected = 'Value: end';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_default_value_with_spaces(): void
    {
        $this->provider->method('has')->with('MISSING_VAR')->willReturn(false);

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Value: ${MISSING_VAR:-hello world}';

        $expected = 'Value: hello world';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_default_value_with_path(): void
    {
        $this->provider->method('has')->with('PATH_VAR')->willReturn(false);

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Path: ${PATH_VAR:-/usr/local/bin}';

        $expected = 'Path: /usr/local/bin';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_replace_consecutive_variables_without_spacing(): void
    {
        $this->provider->method('has')->willReturnMap([
            ['VAR1', true],
            ['VAR2', true],
        ]);

        $this->provider->method('get')->willReturnMap([
            ['VAR1', 'first'],
            ['VAR2', 'second'],
        ]);

        $processor = new VariableReplacementProcessor($this->provider);
        $text = '${VAR1}${VAR2}';

        $expected = 'firstsecond';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_replace_same_variable_multiple_times(): void
    {
        $this->provider->method('has')->with('REPEATED')->willReturn(true);
        $this->provider->method('get')->with('REPEATED')->willReturn('value');

        $processor = new VariableReplacementProcessor($this->provider);
        $text = '${REPEATED} and ${REPEATED} again';

        $expected = 'value and value again';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_variable_at_start_of_string(): void
    {
        $this->provider->method('has')->with('START_VAR')->willReturn(true);
        $this->provider->method('get')->with('START_VAR')->willReturn('prefix');

        $processor = new VariableReplacementProcessor($this->provider);
        $text = '${START_VAR}_suffix';

        $expected = 'prefix_suffix';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_variable_at_end_of_string(): void
    {
        $this->provider->method('has')->with('END_VAR')->willReturn(true);
        $this->provider->method('get')->with('END_VAR')->willReturn('suffix');

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'prefix_${END_VAR}';

        $expected = 'prefix_suffix';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_multiline_text(): void
    {
        $this->provider->method('has')->willReturnMap([
            ['LINE1_VAR', true],
            ['LINE2_VAR', true],
        ]);

        $this->provider->method('get')->willReturnMap([
            ['LINE1_VAR', 'first'],
            ['LINE2_VAR', 'second'],
        ]);

        $processor = new VariableReplacementProcessor($this->provider);
        $text = "Line 1: \${LINE1_VAR}\nLine 2: \${LINE2_VAR}";

        $expected = "Line 1: first\nLine 2: second";
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_empty_string_input(): void
    {
        $processor = new VariableReplacementProcessor($this->provider);

        $this->assertSame('', $processor->process(''));
    }

    #[Test]
    public function it_should_keep_unknown_double_brace_variable_with_original_syntax(): void
    {
        $this->provider->method('has')->willReturn(false);

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Unknown var: {{UNKNOWN_VAR}}';

        $this->assertSame($text, $processor->process($text));
    }

    #[Test]
    public function it_should_log_default_value_usage_when_logger_provided(): void
    {
        $this->provider->method('has')->with('MISSING_VAR')->willReturn(false);

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                'Using default value for variable',
                ['name' => 'MISSING_VAR', 'default' => 'fallback'],
            );

        $processor = new VariableReplacementProcessor($this->provider, $this->logger);
        $text = 'Value: ${MISSING_VAR:-fallback}';

        $processor->process($text);
    }

    #[Test]
    public function it_should_handle_variable_only_string(): void
    {
        $this->provider->method('has')->with('ONLY_VAR')->willReturn(true);
        $this->provider->method('get')->with('ONLY_VAR')->willReturn('just_this');

        $processor = new VariableReplacementProcessor($this->provider);

        $this->assertSame('just_this', $processor->process('${ONLY_VAR}'));
    }

    #[Test]
    public function it_should_handle_mixed_existing_and_missing_variables(): void
    {
        $this->provider->method('has')->willReturnMap([
            ['EXISTS', true],
            ['MISSING', false],
        ]);

        $this->provider->method('get')->willReturnMap([
            ['EXISTS', 'found'],
        ]);

        $processor = new VariableReplacementProcessor($this->provider);
        $text = '${EXISTS} and ${MISSING:-default}';

        $expected = 'found and default';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_not_match_invalid_variable_names_with_hyphen(): void
    {
        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Invalid: ${VAR-NAME}';

        // Should not be matched, remains unchanged
        $this->assertSame($text, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_numeric_only_variable_names(): void
    {
        $this->provider->method('has')->with('123')->willReturn(true);
        $this->provider->method('get')->with('123')->willReturn('numeric');

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Value: ${123}';

        $expected = 'Value: numeric';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_single_character_variable_names(): void
    {
        $this->provider->method('has')->with('X')->willReturn(true);
        $this->provider->method('get')->with('X')->willReturn('single');

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Value: ${X}';

        $expected = 'Value: single';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_both_syntaxes_with_defaults_in_same_text(): void
    {
        $this->provider->method('has')->willReturn(false);

        $processor = new VariableReplacementProcessor($this->provider);
        $text = '${VAR1:-dollar} and {{VAR2:-brace}}';

        $expected = 'dollar and brace';
        $this->assertSame($expected, $processor->process($text));
    }

    #[Test]
    public function it_should_handle_variable_replacement_containing_variable_like_syntax(): void
    {
        $this->provider->method('has')->with('VAR')->willReturn(true);
        $this->provider->method('get')->with('VAR')->willReturn('${NOT_A_VAR}');

        $processor = new VariableReplacementProcessor($this->provider);
        $text = 'Value: ${VAR}';

        // The replacement contains ${ but it's the result, not re-processed
        $expected = 'Value: ${NOT_A_VAR}';
        $this->assertSame($expected, $processor->process($text));
    }

    protected function setUp(): void
    {
        $this->provider = $this->createMock(VariableProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }
}
