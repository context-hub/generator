<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\ConfigLoader\Registry\DocumentRegistry;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\Tree\TreeSource;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'init',
    description: 'Initialize a new context configuration file',
)]
final class InitCommand extends Command
{
    private const DEFAULT_CONFIG_NAME = 'context.yaml';

    public function __construct(
        public string $baseDir,
        private readonly FilesInterface $files,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'filename',
                mode: InputArgument::OPTIONAL,
                description: 'The name of the file to create',
                default: self::DEFAULT_CONFIG_NAME,
            )
            ->addOption(
                name: 'type',
                shortcut: 't',
                mode: InputArgument::OPTIONAL,
                description: 'The type of the file to create (json, yaml, etc.)',
                default: 'yaml',
                suggestedValues: ['json', 'yaml'],
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filename = $input->getArgument('filename') ?: self::DEFAULT_CONFIG_NAME;

        $type = $input->getOption('type');
        if (!\in_array($type, ['json', 'yaml'], true)) {
            $output->error('Invalid type specified. Supported types are: json, yaml');

            return Command::FAILURE;
        }

        $filename = \pathinfo(\strtolower((string) $filename), PATHINFO_FILENAME) . '.' . $type;
        $filePath = \sprintf('%s/%s', $this->baseDir, $filename);

        if ($this->files->exists($filePath)) {
            $output->error(\sprintf('Config %s already exists', $filePath));

            return Command::FAILURE;
        }

        $content = new DocumentRegistry([
            new Document(
                description: 'Project structure overview',
                outputPath: 'project-structure.md',
                firstSource: new TreeSource(
                    sourcePath: ['src'],
                    treeView: new TreeViewConfig(
                        showCharCount: true,
                    ),
                ),
            ),
        ]);

        try {
            $content = match ($type) {
                'json' => \json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'yaml' => Yaml::dump(
                    \json_decode(\json_encode($content), true),
                    10,
                    2,
                    Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
                ),
            };
        } catch (\Throwable $e) {
            $output->error(\sprintf('Failed to create config: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        if (\file_exists($filePath)) {
            $output->error(\sprintf('Config %s already exists', $filePath));

            return Command::FAILURE;
        }

        $this->files->ensureDirectory(\dirname($filePath));
        $this->files->write($filePath, $content);

        $output->success(\sprintf('Config %s created', $filePath));

        return Command::SUCCESS;
    }
}
