<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\Variable\Provider;

use Butschster\ContextGenerator\Lib\Variable\Provider\PredefinedVariableProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PredefinedVariableProvider::class)]
class PredefinedVariableProviderTest extends TestCase
{
    private PredefinedVariableProvider $provider;

    #[Test]
    public function it_should_have_predefined_variables(): void
    {
        // Test some key variables that should be predefined
        $this->assertTrue($this->provider->has(name: 'DATETIME'));
        $this->assertTrue($this->provider->has(name: 'DATE'));
        $this->assertTrue($this->provider->has(name: 'TIME'));
        $this->assertTrue($this->provider->has(name: 'TIMESTAMP'));
        $this->assertTrue($this->provider->has(name: 'USER'));
        $this->assertTrue($this->provider->has(name: 'HOME_DIR'));
        $this->assertTrue($this->provider->has(name: 'TEMP_DIR'));
        $this->assertTrue($this->provider->has(name: 'OS'));
        $this->assertTrue($this->provider->has(name: 'HOSTNAME'));
        $this->assertTrue($this->provider->has(name: 'PWD'));
    }

    #[Test]
    public function it_should_not_have_arbitrary_variables(): void
    {
        $this->assertFalse($this->provider->has(name: 'NON_EXISTENT_VARIABLE'));
        $this->assertNull($this->provider->get(name: 'NON_EXISTENT_VARIABLE'));
    }

    #[Test]
    public function it_should_return_timestamp_as_string(): void
    {
        $timestamp = $this->provider->get(name: 'TIMESTAMP');

        $this->assertIsString($timestamp);
        // Timestamp should be a numeric string
        $this->assertIsNumeric($timestamp);
        // Timestamp should be close to current time
        $this->assertEqualsWithDelta(\time(), (int) $timestamp, 2);
    }

    #[Test]
    public function it_should_return_date_in_correct_format(): void
    {
        $date = $this->provider->get(name: 'DATE');

        $this->assertIsString($date);
        // Validate date format: Y-m-d
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
        // Should match current date
        $this->assertSame(\date(format: 'Y-m-d'), $date);
    }

    #[Test]
    public function it_should_return_current_working_directory(): void
    {
        $pwd = $this->provider->get(name: 'PWD');

        $this->assertIsString($pwd);
        // Should match current working directory or '.' if unavailable
        $expected = \getcwd() ?: '.';
        $this->assertSame($expected, $pwd);
    }

    #[Test]
    public function it_should_return_temp_directory(): void
    {
        $tempDir = $this->provider->get(name: 'TEMP_DIR');

        $this->assertIsString($tempDir);
        $this->assertSame(\sys_get_temp_dir(), $tempDir);
    }

    protected function setUp(): void
    {
        $this->provider = new PredefinedVariableProvider();
    }
}
