<?php

declare(strict_types=1);

namespace Tests\Lib\Variable\Provider;

use Butschster\ContextGenerator\Lib\Variable\Provider\PredefinedVariableProvider;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PredefinedVariableProvider::class)]
class PredefinedVariableProviderTest extends TestCase
{
    private PredefinedVariableProvider $provider;

    #[Test]
    public function it_should_have_predefined_variables(): void
    {
        // Test some key variables that should be predefined
        $this->assertTrue($this->provider->has('DATETIME'));
        $this->assertTrue($this->provider->has('DATE'));
        $this->assertTrue($this->provider->has('TIME'));
        $this->assertTrue($this->provider->has('TIMESTAMP'));
        $this->assertTrue($this->provider->has('USER'));
        $this->assertTrue($this->provider->has('HOME_DIR'));
        $this->assertTrue($this->provider->has('TEMP_DIR'));
        $this->assertTrue($this->provider->has('OS'));
        $this->assertTrue($this->provider->has('HOSTNAME'));
        $this->assertTrue($this->provider->has('PWD'));
    }

    #[Test]
    public function it_should_not_have_arbitrary_variables(): void
    {
        $this->assertFalse($this->provider->has('NON_EXISTENT_VARIABLE'));
        $this->assertNull($this->provider->get('NON_EXISTENT_VARIABLE'));
    }

    #[Test]
    public function it_should_return_timestamp_as_string(): void
    {
        $timestamp = $this->provider->get('TIMESTAMP');

        $this->assertIsString($timestamp);
        // Timestamp should be a numeric string
        $this->assertIsNumeric($timestamp);
        // Timestamp should be close to current time
        $this->assertEqualsWithDelta(\time(), (int) $timestamp, 2);
    }

    #[Test]
    public function it_should_return_date_in_correct_format(): void
    {
        $date = $this->provider->get('DATE');

        $this->assertIsString($date);
        // Validate date format: Y-m-d
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
        // Should match current date
        $this->assertSame(\date('Y-m-d'), $date);
    }

    #[Test]
    public function it_should_return_current_working_directory(): void
    {
        $pwd = $this->provider->get('PWD');

        $this->assertIsString($pwd);
        // Should match current working directory or '.' if unavailable
        $expected = \getcwd() ?: '.';
        $this->assertSame($expected, $pwd);
    }

    #[Test]
    public function it_should_return_temp_directory(): void
    {
        $tempDir = $this->provider->get('TEMP_DIR');

        $this->assertIsString($tempDir);
        $this->assertSame(\sys_get_temp_dir(), $tempDir);
    }

    protected function setUp(): void
    {
        $this->provider = new PredefinedVariableProvider();
    }
}
