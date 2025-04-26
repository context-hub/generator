<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class InitCommandTest extends ConsoleTestCase
{
    public static function configFileFormat(): \Generator
    {
        yield 'yaml' => ['context.yaml', 'context.yaml'];
        yield 'yml' => ['test.yml', 'test.yaml'];
        yield 'json' => ['context.json', 'context.json'];
    }

    #[Test]
    #[DataProvider('configFileFormat')]
    public function config_file_should_be_created(string $filename, $resultFilename): void
    {
        $result = $this->runCommand('init', [
            '--config-file' => $filename,
        ]);

        $this->assertStringContainsString(
            \sprintf('[OK] Config %s created', $this->getConfigPath($resultFilename)),
            $result,
        );

        $this->assertFileExists($this->getConfigPath($resultFilename), 'Config file should exist');

        $content = \file_get_contents($this->getConfigPath($resultFilename));
        $this->assertNotEmpty($content, 'Config file should not be empty');

        $this->assertStringContainsString('$schema', $content);
        $this->assertStringContainsString('Project structure overview', $content);
        $this->assertStringContainsString('project-structure.md', $content);
    }

    #[Test]
    public function invalid_format(): void
    {
        $result = $this->runCommand('init', [
            '--config-file' => 'context.txt',
        ]);

        $this->assertStringContainsString(
            '[ERROR] Unsupported config type: txt',
            $result,
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (self::configFileFormat() as $format) {
            \unlink($this->getConfigPath($format[1]));
        }
    }

    protected function getConfigPath(string $config): string
    {
        return $this->rootDirectory() . '/' . $config;
    }
}
