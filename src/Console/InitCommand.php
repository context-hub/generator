<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\JsonSchema;
use Butschster\ContextGenerator\Config\ConfigType;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;
use Butschster\ContextGenerator\DirectoriesInterface;
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

    public function __invoke(DirectoriesInterface $dirs, FilesInterface $files): int
    {
        // Display the command title
        $this->outputService->title('Initialize Context Configuration');

        $filename = $this->configFilename;
        $ext = \pathinfo($filename, PATHINFO_EXTENSION);

        try {
            $type = ConfigType::fromExtension($ext);
        } catch (\ValueError) {
            $filePath = (string) $dirs->getRootPath()->join($filename);
            $this->outputService->keyValue('Target Path', $filePath);
            $this->outputService->error(\sprintf('Unsupported config type: %s', $ext));

            // Add helpful note about supported formats
            $this->outputService->note([
                $this->outputService->getStyle()->colorize(
                    'Supported configuration formats:',
                    $this->outputService->getStyle()->getLabelColor(),
                ),
                '- JSON (.json)',
                '- YAML (.yml, .yaml)',
            ]);

            return Command::FAILURE;
        }

        // Use section for input parameters
        $filename = \pathinfo(\strtolower($filename), PATHINFO_FILENAME) . '.' . $type->value;
        $filePath = (string) $dirs->getRootPath()->join($filename);
        $this->outputService->keyValue('Target Path', $filePath);

        if ($files->exists($filePath)) {
            $this->outputService->error(
                \sprintf(
                    'Config %s already exists',
                    $this->outputService->getStyle()->colorize(
                        $filePath,
                        $this->outputService->getStyle()->getInfoColor(),
                    ),
                ),
            );
            return Command::FAILURE;
        }

        $config = new ConfigRegistry(
            schema: JsonSchema::SCHEMA_URL,
        );

        $config->register(new DocumentRegistry([
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
        ]));

        try {
            $content = match ($type) {
                ConfigType::Json => \json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ConfigType::Yaml => Yaml::dump(
                    \json_decode(\json_encode($config), true),
                    10,
                    2,
                    Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
                ),
                default => throw new \InvalidArgumentException(
                    \sprintf('Unsupported config type: %s', $type->value),
                ),
            };
        } catch (\Throwable $e) {
            $this->outputService->error(\sprintf('Failed to create config: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        try {
            $files->ensureDirectory(\dirname($filePath));
            $files->write($filePath, $content);

            // Success message
            $this->outputService->success('Configuration file has been created successfully');

            // Add helpful next steps note
            $this->outputService->note([
                $this->outputService->getStyle()->colorize(
                    'Next steps:',
                    $this->outputService->getStyle()->getLabelColor(),
                    true,
                ),
                '- Edit the configuration file to customize sources and outputs',
                \sprintf(
                    '- Run %s to generate context files based on this configuration',
                    $this->outputService->getStyle()->colorize(
                        'ctx generate',
                        color: $this->outputService->getStyle()->getInfoColor(),
                        bold: true,
                    ),
                ),
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->outputService->error(\sprintf('Failed to write config file: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
