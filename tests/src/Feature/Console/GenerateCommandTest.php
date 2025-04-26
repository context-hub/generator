<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class GenerateCommandTest extends ConsoleTestCase
{
    private string $outputDir;

    public static function commandsProvider(): iterable
    {
        yield ['generate'];
        yield ['build'];
        yield ['compile'];
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function empty_config_should_be_rendered(string $command): void
    {
        $this->assertConsoleCommandOutputContainsStrings(
            command: $command,
            args: [
                '--config-file' => $this->getFixturesDir('Console/GenerateCommand/empty.yaml'),
            ],
            strings: [
                'No documents found in configuration.',
            ],
        );
    }

    #[Test]
    #[DataProvider('commandsProvider')]
    public function simple_config_should_be_rendered(string $command): void
    {
        $result = $this->buildContext(
            workDir: $this->outputDir,
            configPath: $this->getFixturesDir('Console/GenerateCommand/simple.yaml'),
            command: $command,
        );

        $this->assertContextContains(
            document: 'context.md',
            strings: [
                '# Simple context document',
                'Simple context',
                '<simple>',
                '</simple>',
            ],
            data: $result,
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->outputDir = $this->getFixturesDir('Console/GenerateCommand/.context');
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanupDirectories($this->outputDir);
    }

    protected function assertContextContains(string $document, array $strings, array $data): void
    {
        foreach ($data['result'] as $documentData) {
            if ($documentData['context_path'] === $document) {
                $this->assertFileExists(
                    $contextPath = $documentData['output_path'] . '/' . $documentData['context_path'],
                );

                $content = \file_get_contents($contextPath);
                foreach ($strings as $string) {
                    $this->assertStringContainsString(
                        $string,
                        $content,
                        \sprintf(
                            'Context file [%s] does not contain string [%s]',
                            $documentData['context_path'],
                            $string,
                        ),
                    );
                }

                return;
            }
        }
    }

    protected function buildContext(string $workDir, string $configPath, string $command = 'generate'): array
    {
        $output = $this->runCommand(
            command: $command,
            args: [
                '--config-file' => $configPath,
                '--work-dir' => $workDir,
                '--json' => true,
            ],
        );

        $output = \trim($output);
        $data = \json_decode($output, true);

        if (!$data) {
            throw new \RuntimeException('Failed to decode JSON output: ' . \json_last_error_msg());
        }

        $this->assertEquals('success', $data['status'] ?? null, 'Status should be success');
        $this->assertEquals('Documents compiled successfully', $data['message'] ?? null, 'Message should be success');

        return $data;
    }
}
