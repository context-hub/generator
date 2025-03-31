<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Config\ConfigType;
use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Document\DocumentRegistry;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\Tree\TreeSource;
use Spiral\Console\Attribute\Option;
use Spiral\Files\FilesInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'init',
    description: 'Initialize a new context configuration file',
)]
final class InitCommand extends BaseCommand
{
    #[Option(
        name: 'config-file',
        shortcut: 'c',
        description: 'The name of the file to create',
    )]
    protected string $configFilename = 'context.yaml';

    public function __invoke(Directories $dirs, FilesInterface $files): int
    {
        $filename = $this->configFilename;
        $ext = \pathinfo($filename, PATHINFO_EXTENSION);

        try {
            $type = ConfigType::fromExtension($ext);
        } catch (\ValueError) {
            $this->output->error(\sprintf('Unsupported config type: %s', $ext));

            return Command::FAILURE;
        }

        $filename = \pathinfo(\strtolower($filename), PATHINFO_FILENAME) . '.' . $type->value;
        $filePath = $dirs->getFilePath($filename);

        if ($files->exists($filePath)) {
            $this->output->error(\sprintf('Config %s already exists', $filePath));

            return Command::FAILURE;
        }

        $content = new DocumentRegistry([
            new Document(
                description: 'Project structure overview',
                outputPath: 'project-structure.md',
                firstSource: new TreeSource(
                    sourcePaths: ['src'],
                    treeView: new TreeViewConfig(
                        showCharCount: true,
                    ),
                ),
            ),
        ]);

        try {
            $content = match ($type) {
                ConfigType::Json => \json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ConfigType::Yaml => Yaml::dump(
                    \json_decode(\json_encode($content), true),
                    10,
                    2,
                    Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
                ),
                default => throw new \InvalidArgumentException(
                    \sprintf('Unsupported config type: %s', $type->value),
                ),
            };
        } catch (\Throwable $e) {
            $this->output->error(\sprintf('Failed to create config: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        if ($files->exists($filePath)) {
            $this->output->error(\sprintf('Config %s already exists', $filePath));

            return Command::FAILURE;
        }

        $files->ensureDirectory(\dirname($filePath));
        $files->write($filePath, $content);

        $this->output->success(\sprintf('Config %s created', $filePath));

        return Command::SUCCESS;
    }
}
