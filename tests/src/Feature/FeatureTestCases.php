<?php

declare(strict_types=1);

namespace Tests\Feature;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use PHPUnit\Framework\Attributes\Test;
use Spiral\Core\Scope;
use Spiral\Files\Files;
use Tests\AppTestCase;

abstract class FeatureTestCases extends AppTestCase
{
    #[\Override]
    public function rootDirectory(): string
    {
        return $this->getFixturesDir('Compiler');
    }

    #[Test]
    public function compile(): void
    {
        $this->getContainer()->runScope(
            bindings: new Scope(
                name: AppScope::Compiler,
            ),
            scope: $this->compileDocuments(...),
        );
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        if (\is_dir(filename: $this->getContextsDir())) {
            $files = new Files();
            $files->deleteDirectory(directory: $this->getContextsDir());
        }
    }

    protected function getContextsDir(string $path = ''): string
    {
        return (string) FSPath::create(path: $this->getFixturesDir('Compiler/.context'))->join($path);
    }

    abstract protected function getConfigPath(): string;

    abstract protected function assertConfigItems(
        DocumentCompiler $compiler,
        ConfigRegistryAccessor $config,
    ): void;

    private function compileDocuments(DocumentCompiler $compiler, ConfigurationProvider $configProvider): void
    {
        $loader = $configProvider->fromPath(configPath: $this->getConfigPath());

        $this->assertConfigItems($compiler, new ConfigRegistryAccessor(registry: $loader->load()));
    }
}
