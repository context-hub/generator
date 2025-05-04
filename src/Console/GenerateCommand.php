<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
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
        $startTime = \microtime(true);

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
                ) use ($startTime): int {
                    try {
                        // Display configuration type using key-value
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
                            $this->outputService->error('Failed to load configuration: ' . $e->getMessage());
                        }

                        return Command::FAILURE;
                    }

                    $config = new ConfigRegistryAccessor($loader->load());
                    $imports = $config->getImports();

                    // Imports section using StatusRenderer
                    if ($imports !== null && !$this->asJson) {
                        $this->outputService->section('Imported Resources');
                        $statusRenderer = $this->outputService->getStatusRenderer();

                        foreach ($imports as $item) {
                            $statusRenderer->renderSuccess(
                                \sprintf('Import %s', $item->getType()),
                                \strlen($item->getPath()) > 60 ? \substr(
                                        $item->getPath(),
                                        0,
                                        60,
                                    ) . '...' : $item->getPath(),
                            );
                        }
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
                            $this->outputService->warning('No documents found in configuration.');
                        }

                        return Command::SUCCESS;
                    }

                    // Document compilation section
                    if (!$this->asJson) {
                        $this->outputService->section('Document Compilation');
                    }

                    $result = [];
                    $documentStats = [
                        'success' => 0,
                        'warning' => 0,
                        'error' => 0,
                    ];

                    $statusRenderer = $this->outputService->getStatusRenderer();

                    foreach ($config->getDocuments() as $document) {
                        $this->logger->info(\sprintf('Compiling %s...', $document->description));

                        $compiledDocument = $compiler->compile($document);
                        $hasErrors = $compiledDocument->errors->hasErrors();

                        if (!$this->asJson) {
                            if ($hasErrors) {
                                $statusRenderer->renderWarning(
                                    $document->description,
                                    $document->outputPath,
                                );

                                // Render errors with indentation
                                foreach ($compiledDocument->errors as $error) {
                                    $statusRenderer->renderError('  ' . $error, '');
                                }

                                $documentStats['warning']++;
                            } else {
                                $statusRenderer->renderSuccess(
                                    $document->description,
                                    $document->outputPath,
                                );
                                $documentStats['success']++;
                            }
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
                        // Summary section
                        $this->outputService->section('Compilation Summary');

                        $summaryRenderer = $this->outputService->getSummaryRenderer();
                        $total = \array_sum($documentStats);

                        $summaryRenderer->renderCompletionSummary([
                            'Successful' => $documentStats['success'],
                            'With Warnings' => $documentStats['warning'],
                            'Failed' => $documentStats['error'],
                        ], $total);
                    }

                    return Command::SUCCESS;
                },
            ),
        );
    }
}
