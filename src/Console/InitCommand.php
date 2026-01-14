<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\DirectoriesInterface;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\Option;
use Spiral\Files\FilesInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'init',
    description: 'Initialize a new context.yaml configuration file',
)]
final class InitCommand extends BaseCommand
{
    #[Argument(
        name: 'template',
        description: 'Template name to use (optional)',
    )]
    protected ?string $template = null;

    #[Option(
        name: 'config-file',
        shortcut: 'c',
        description: 'Custom config filename (default: context.yaml)',
    )]
    protected string $configFile = 'context.yaml';

    #[Option(
        name: 'show-all',
        description: 'Show all detection results',
    )]
    protected bool $showAll = false;

    public function __invoke(
        FilesInterface $files,
        DirectoriesInterface $dirs,
    ): int {
        $configPath = $dirs->getOutputPath()->toString() . '/' . $this->configFile;

        // Check if config file already exists
        if ($files->exists($configPath)) {
            $this->output->warning(\sprintf('Config file already exists: %s', $this->configFile));
            
            if (!$this->output->confirm('Do you want to overwrite it?', false)) {
                return Command::SUCCESS;
            }
        }

        // TODO: When template system is available, try to use it here
        // For now, always create an empty context file
        $this->createEmptyContext($files, $configPath);

        return Command::SUCCESS;
    }

    private function createEmptyContext(FilesInterface $files, string $configPath): void
    {
        $this->output->writeln('');
        
        if ($this->template !== null) {
            $this->output->warning(\sprintf('Template "%s" not found.', $this->template));
        } else {
            $this->output->warning('No specific project type detected.');
        }
        
        $this->output->writeln('');
        $this->output->writeln('Creating an empty context.yaml file...');
        $this->output->writeln('');

        $emptyConfig = <<<'YAML'
$schema: 'https://raw.githubusercontent.com/context-hub/generator/refs/heads/main/json-schema.json'

# Import external configurations or include other files
#import:
#  - path: src/**/context.yaml
#  - path: https://example.com/shared-config.yaml

# Define documents to generate
documents:
  - description: 'Project overview'
    outputPath: overview.md
    overwrite: true
    sources:
      # Add your sources here
      # Examples:
      # - type: file
      #   sourcePaths:
      #     - src
      #   filePattern:
      #     - '*.php'
      #
      # - type: tree
      #   sourcePaths:
      #     - src
      #   showSize: true
      #
      # - type: git_diff
      #   commit: unstaged

# Global exclusion patterns
#exclude:
#  - vendor
#  - node_modules
#  - .git

YAML;

        $files->write($configPath, $emptyConfig);

        $this->output->success(\sprintf('Empty config file created: %s', $this->configFile));
        $this->output->writeln('');
        $this->output->writeln('Next steps:');
        $this->output->writeln('  1. Edit the configuration file to add your sources');
        $this->output->writeln('  2. Run "ctx generate" to create context files');
        $this->output->writeln('');
        $this->output->writeln('For more information:');
        $this->output->writeln('  - Use "ctx template:list" to see available templates');
        $this->output->writeln('  - Use "ctx init <template-name>" to use a specific template');
        $this->output->writeln('  - Visit the documentation for configuration examples');
    }
}
