<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer;

use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\Registry\AbstractSourceFactory;
use Butschster\ContextGenerator\Source\SourceInterface;

/**
 * Factory for creating ComposerSource instances
 */
final readonly class ComposerSourceFactory extends AbstractSourceFactory
{
    #[\Override]
    public function getType(): string
    {
        return 'composer';
    }

    #[\Override]
    public function create(array $config): SourceInterface
    {
        $this->logger?->debug('Creating Composer source', [
            'config' => $config,
        ]);

        if (isset($config['modifiers'])) {
            $config['modifiers'] = $this->parseModifiers($config['modifiers']);
        }

        $composerPath = $config['composerPath'] ?? '.';

        // If the path is relative, make it absolute using the root path
        if (!\str_starts_with($composerPath, '/')) {
            $composerPath = $this->dirs->getRootPath()->join($composerPath);
        }

        return new ComposerSource(
            composerPath: (string) $composerPath,
            description: $config['description'] ?? 'Composer Packages',
            packages: $config['packages'] ?? [],
            filePattern: $config['filePattern'] ?? '*.php',
            notPath: $config['notPath'] ?? ['tests', 'vendor', 'examples'],
            path: $config['path'] ?? [],
            contains: $config['contains'] ?? [],
            notContains: $config['notContains'] ?? [],
            includeDevDependencies: $config['includeDevDependencies'] ?? false,
            treeView: TreeViewConfig::fromArray($config),
            modifiers: $config['modifiers'] ?? [],
            tags: $config['tags'] ?? [],
        );
    }
}
