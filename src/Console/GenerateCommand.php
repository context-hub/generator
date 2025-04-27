<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Console\Renderer\GenerateCommandRenderer;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'generate',
    description: 'Generate context files from configuration',
    aliases: ['build', 'compile'],
)]
final class GenerateCommand extends BaseCommand
{
    #[Option(
        name: 'inline',
        shortcut: 'i',
        description: 'Inline JSON configuration string. If provided, file-based configuration will be ignored',
    )]
    protected ?string $inlineJson = null;

    #[Option(
        name: 'config-file',
        shortcut: 'c',
        description: 'Path to configuration file (absolute or relative to current directory).',
    )]
    protected ?string $configPath = null;

    #[Option(
        name: 'work-dir',
        shortcut: 'w',
        description: 'Path to working directory. If not provided, will use the current working directory',
    )]
    protected ?string $workDir = null;

    #[Option(
        name: 'env',
        shortcut: 'e',
        description: 'Path to .env (like .env.local) file. If not provided, will ignore any .env files',
    )]
    protected ?string $envFileName = null;

    #[Option(
        name: 'json',
        description: 'Output JSON instead of context files',
    )]
    protected bool $asJson = false;

    public function __invoke(Container $container, DirectoriesInterface $dirs): int
    {
        // Determine the effective root path based on config file path
        $dirs = $dirs
            ->determineRootPath($this->configPath, $this->inlineJson)
            ->withOutputPath($this->workDir)
            ->withEnvFile($this->envFileName);

        $container->getBinder('root')->bind(
            DirectoriesInterface::class,
            $dirs,
        );

        return $container->runScope(
            bindings: new Scope(
                bindings: [
                    DirectoriesInterface::class => $dirs,
                ],
            ),
            scope: fn(Container $container): int => $container->runScope(
                bindings: new Scope(
                    name: AppScope::Compiler,
                    bindings: [
                        DirectoriesInterface::class => $dirs,
                    ],
                ),
                scope: function (
                    DocumentCompiler $compiler,
                    ConfigurationProvider $configProvider,
                ): int {
                    try {
                        // Get the appropriate loader based on options provided
                        if ($this->inlineJson !== null) {
                            $this->logger->info('Using inline JSON configuration...');
                            $loader = $configProvider->fromString($this->inlineJson);
                        } elseif ($this->configPath !== null) {
                            $this->logger->info(\sprintf('Loading configuration from %s...', $this->configPath));
                            $loader = $configProvider->fromPath($this->configPath);
                        } else {
                            $this->logger->info('Loading configuration from default location...');
                            $loader = $configProvider->fromDefaultLocation();
                        }
                    } catch (ConfigLoaderException $e) {
                        $this->logger->error('Failed to load configuration', [
                            'error' => $e->getMessage(),
                        ]);

                        if ($this->asJson) {
                            $this->output->writeln(\json_encode([
                                'status' => 'error',
                                'message' => 'Failed to load configuration',
                                'error' => $e->getMessage(),
                            ]));
                        } else {
                            $this->output->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));
                        }

                        return Command::FAILURE;
                    }

                    // Create the renderer for consistent output formatting
                    $renderer = new GenerateCommandRenderer($this->output);

                    // Display summary header
                    $this->output->writeln('');

                    $config = new ConfigRegistryAccessor($loader->load());

                    $imports = $config->getImports();
                    if ($imports !== null && !$this->asJson) {
                        $renderer->renderImports($imports);
                    }

                    if ($config->getDocuments() === null || $config->getDocuments()->getItems() === []) {
                        if ($this->asJson) {
                            $this->output->writeln(\json_encode([
                                'status' => 'success',
                                'message' => 'No documents found in configuration.',
                                'imports' => $imports,
                                'prompts' => $config->getPrompts(),
                                'tools' => $config->getTools(),
                            ]));
                        } else {
                            $this->output->warning('No documents found in configuration.');
                        }

                        return Command::SUCCESS;
                    }

                    $result = [];

                    foreach ($config->getDocuments() as $document) {
                        $this->logger->info(\sprintf('Compiling %s...', $document->description));

                        $compiledDocument = $compiler->compile($document);

                        if (!$this->asJson) {
                            $renderer->renderCompilationResult($document, $compiledDocument);
                        } else {
                            $result[] = [
                                'output_path' => $compiledDocument->outputPath,
                                'context_path' => $compiledDocument->contextPath,
                                'errors' => $compiledDocument->errors,
                            ];
                        }
                    }

                    if ($this->asJson) {
                        $this->output->writeln(\json_encode([
                            'status' => 'success',
                            'message' => 'Documents compiled successfully',
                            'result' => $result,
                            'imports' => $imports,
                            'prompts' => $config->getPrompts(),
                            'tools' => $config->getTools(),
                        ]));
                    } else {
                        $this->output->writeln('');
                    }

                    return Command::SUCCESS;
                },
            ),
        );
    }
}
