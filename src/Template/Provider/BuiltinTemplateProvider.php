<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Provider;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Template\Definition\TemplateDefinitionRegistry;
use Butschster\ContextGenerator\Template\Registry\TemplateProviderInterface;
use Butschster\ContextGenerator\Template\Template;
use Spiral\Files\FilesInterface;

/**
 * Provides built-in templates using template definitions
 */
final readonly class BuiltinTemplateProvider implements TemplateProviderInterface
{
    public function __construct(
        private TemplateDefinitionRegistry $definitionRegistry,
        private FilesInterface $files,
        private DirectoriesInterface $dirs,
    ) {}

    public function getTemplates(): array
    {
        // For the provider interface, we create templates without project metadata
        // The specific templates will be created with metadata when actually used
        return $this->definitionRegistry->createAllTemplates(
            $this->buildProjectMetaData(),
        );
    }

    public function getTemplate(string $name): ?Template
    {
        return $this->definitionRegistry->createTemplate($name, $this->buildProjectMetaData());
    }

    public function getPriority(): int
    {
        return 100; // High priority for built-in templates
    }

    private function buildProjectMetaData(): array
    {
        $dirs = \glob($this->dirs->getRootPath()->toString() . '/*', \GLOB_ONLYDIR);

        $dirs = \array_map(static fn(string $dir): string => \basename($dir), $dirs);

        return [
            'existingDirectories' => $dirs,
        ];
    }
}
