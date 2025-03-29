<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Directories;
use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\ConfigLoader\Registry\DocumentRegistry;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\Tree\TreeSource;
use Spiral\Core\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'init',
    description: 'Initialize a new context configuration file',
)]
final class InitCommand extends BaseCommand
{
    private const string DEFAULT_CONFIG_NAME = 'context.yaml';
    private const string DEFAULT_CONFIG_TYPE = 'yaml';
    private const array SUPPORTED_TYPES = ['json', 'yaml'];

    public function __construct(
        Container $container,
        public Directories $dirs,
        private readonly FilesInterface $files,
    ) {
        parent::__construct($container);
    }

    public function __invoke(): int
    {
        $filename = $this->input->getArgument('filename') ?: self::DEFAULT_CONFIG_NAME;

        $type = $this->input->getOption('type');
        if (!\in_array($type, ['json', 'yaml'], true)) {
            $this->output->error('Invalid type specified. Supported types are: json, yaml');

            return Command::FAILURE;
        }

        $filename = \pathinfo(\strtolower((string) $filename), PATHINFO_FILENAME) . '.' . $type;
        $filePath = $this->dirs->getFilePath($filename);

        if ($this->files->exists($filePath)) {
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
                'json' => \json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'yaml' => Yaml::dump(
                    \json_decode(\json_encode($content), true),
                    10,
                    2,
                    Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
                ),
            };
        } catch (\Throwable $e) {
            $this->output->error(\sprintf('Failed to create config: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        if ($this->files->exists($filePath)) {
            $this->output->error(\sprintf('Config %s already exists', $filePath));

            return Command::FAILURE;
        }

        $this->files->ensureDirectory(\dirname($filePath));
        $this->files->write($filePath, $content);

        $this->output->success(\sprintf('Config %s created', $filePath));

        return Command::SUCCESS;
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
                default: self::DEFAULT_CONFIG_TYPE,
                suggestedValues: self::SUPPORTED_TYPES,
            );
    }
}
