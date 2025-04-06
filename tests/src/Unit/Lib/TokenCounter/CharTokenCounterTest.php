<?php

declare(strict_types=1);

namespace Tests\Unit\Lib\TokenCounter;

use Butschster\ContextGenerator\Lib\TokenCounter\CharTokenCounter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharTokenCounterTest extends TestCase
{
    private CharTokenCounter $counter;
    private string $fixturesDir;

    #[Test]
    public function it_should_return_correct_count_for_empty_file(): void
    {
        $filePath = $this->fixturesDir . '/empty.txt';
        $this->assertFileExists($filePath);

        $count = $this->counter->countFile($filePath);
        $this->assertEquals(1, $count);
    }

    #[Test]
    public function it_should_return_correct_count_for_simple_file(): void
    {
        $filePath = $this->fixturesDir . '/simple.txt';
        $this->assertFileExists($filePath);

        $count = $this->counter->countFile($filePath);
        $this->assertEquals(55, $count);
    }

    #[Test]
    public function it_should_handle_multibyte_characters_correctly(): void
    {
        $filePath = $this->fixturesDir . '/multibyte.txt';
        $this->assertFileExists($filePath);

        $count = $this->counter->countFile($filePath);
        $expectedLength = \mb_strlen(\file_get_contents($filePath));
        $this->assertEquals($expectedLength, $count);
    }

    #[Test]
    public function it_should_return_zero_for_non_existent_file(): void
    {
        $filePath = $this->fixturesDir . '/non-existent-file.txt';
        $this->assertFileDoesNotExist($filePath);

        $count = $this->counter->countFile($filePath);
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_should_return_zero_for_directory(): void
    {
        $dirPath = $this->fixturesDir . '/nested';
        $this->assertDirectoryExists($dirPath);

        $count = $this->counter->countFile($dirPath);
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_should_calculate_directory_count_correctly(): void
    {
        $directory = [
            'file1.txt' => $this->fixturesDir . '/simple.txt',
            'file2.txt' => $this->fixturesDir . '/multibyte.txt',
            'nested' => [
                'file3.txt' => $this->fixturesDir . '/nested/file1.txt',
                'level1' => [
                    'file4.txt' => $this->fixturesDir . '/nested/level1/file2.txt',
                    'level2' => [
                        'file5.txt' => $this->fixturesDir . '/nested/level1/level2/file3.txt',
                    ],
                ],
            ],
        ];

        $count = $this->counter->calculateDirectoryCount($directory);

        // Calculate expected count manually
        $expectedCount = 0;
        $expectedCount += \mb_strlen(\file_get_contents($this->fixturesDir . '/simple.txt'));
        $expectedCount += \mb_strlen(\file_get_contents($this->fixturesDir . '/multibyte.txt'));
        $expectedCount += \mb_strlen(\file_get_contents($this->fixturesDir . '/nested/file1.txt'));
        $expectedCount += \mb_strlen(\file_get_contents($this->fixturesDir . '/nested/level1/file2.txt'));
        $expectedCount += \mb_strlen(\file_get_contents($this->fixturesDir . '/nested/level1/level2/file3.txt'));

        $this->assertEquals($expectedCount, $count);
    }

    #[Test]
    public function it_should_return_zero_for_empty_directory(): void
    {
        $directory = [];
        $count = $this->counter->calculateDirectoryCount($directory);
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_should_handle_non_existent_files_in_directory(): void
    {
        $directory = [
            'file1.txt' => $this->fixturesDir . '/non-existent-file1.txt',
            'file2.txt' => $this->fixturesDir . '/non-existent-file2.txt',
        ];

        $count = $this->counter->calculateDirectoryCount($directory);
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_should_handle_mixed_existent_and_non_existent_files(): void
    {
        $directory = [
            'file1.txt' => $this->fixturesDir . '/simple.txt', // Exists
            'file2.txt' => $this->fixturesDir . '/non-existent-file.txt', // Doesn't exist
        ];

        $count = $this->counter->calculateDirectoryCount($directory);
        $expectedCount = \mb_strlen(\file_get_contents($this->fixturesDir . '/simple.txt'));

        $this->assertEquals($expectedCount, $count);
    }

    protected function setUp(): void
    {
        $this->counter = new CharTokenCounter();
        $this->fixturesDir = $this->getFixturesDir('TokenCounter');
    }
}
