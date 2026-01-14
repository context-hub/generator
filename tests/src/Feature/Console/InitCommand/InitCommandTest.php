<?php

declare(strict_types=1);

namespace Tests\Feature\Console\InitCommand;

use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Console\ConsoleTestCase;

final class InitCommandTest extends ConsoleTestCase
{
    private string $outputDir;

    #[Test]
    public function creates_empty_context_when_no_template_specified(): void
    {
        $result = $this->runConsole([
            'init',
            '--work-dir=' . $this->outputDir,
        ]);

        $this->assertSame(0, $result->getCode());
        $this->assertFileExists($this->outputDir . '/context.yaml');

        $content = \file_get_contents($this->outputDir . '/context.yaml');
        $this->assertStringContainsString('$schema:', $content);
        $this->assertStringContainsString('documents:', $content);
    }

    #[Test]
    public function creates_empty_context_with_custom_filename(): void
    {
        $result = $this->runConsole([
            'init',
            '--work-dir=' . $this->outputDir,
            '--config-file=custom-context.yaml',
        ]);

        $this->assertSame(0, $result->getCode());
        $this->assertFileExists($this->outputDir . '/custom-context.yaml');

        $content = \file_get_contents($this->outputDir . '/custom-context.yaml');
        $this->assertStringContainsString('$schema:', $content);
        $this->assertStringContainsString('documents:', $content);
    }

    #[Test]
    public function does_not_overwrite_existing_config_without_confirmation(): void
    {
        // Create existing config
        $configPath = $this->outputDir . '/context.yaml';
        \file_put_contents($configPath, 'existing content');

        $result = $this->runConsole([
            'init',
            '--work-dir=' . $this->outputDir,
        ], 'no');

        $this->assertSame(0, $result->getCode());
        $this->assertSame('existing content', \file_get_contents($configPath));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = $this->createTempDir();
    }
}
