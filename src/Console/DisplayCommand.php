<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Console\Renderer\DocumentRenderer;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\DirectoriesInterface;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'display',
    description: 'Display the context configuration in a human-readable format',
)]
final class DisplayCommand extends BaseCommand
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

    public function __invoke(
        Container $container,
        DirectoriesInterface $dirs,
        DocumentRenderer $renderer,
    ): int {
        $dirs = $dirs
            ->determineRootPath($this->configPath, $this->inlineJson);

        return $container->runScope(
            bindings: new Scope(
                name: AppScope::Compiler,
                bindings: [
                    DirectoriesInterface::class => $dirs,
                ],
            ),
            scope: function (ConfigurationProvider $configProvider) use ($dirs, $renderer) {
                try {
                    // Get the appropriate loader based on options provided
                    if ($this->inlineJson !== null) {
                        $this->output->info('Using inline JSON configuration...');
                        $loader = $configProvider->fromString($this->inlineJson);
                    } elseif ($this->configPath !== null) {
                        $this->output->info(\sprintf('Loading configuration from %s...', $this->configPath));
                        $loader = $configProvider->fromPath($this->configPath);
                    } else {
                        $this->output->info('Loading configuration from default location...');
                        $loader = $configProvider->fromDefaultLocation();
                    }
                } catch (ConfigLoaderException $e) {
                    $this->logger->error('Failed to load configuration', [
                        'error' => $e->getMessage(),
                    ]);

                    $this->output->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));

                    return Command::FAILURE;
                }

                try {
                    // Load the document registry
                    $registry = new ConfigRegistryAccessor($loader->load());

                    $title = "Context: " . (string) $dirs->getRootPath();

                    $this->output->writeln("\n" . Style::header($title));
                    $this->output->writeln(Style::separator('=', \strlen($title)) . "\n");
                    $documents = $registry->getDocuments()->getItems();
                    $this->output->writeln(
                        Style::property("Total documents") . ": " . Style::count(\count($documents)) . "\n\n",
                    );

                    foreach ($documents as $index => $document) {
                        /**
                         * @psalm-suppress InvalidScalarArgument
                         * @psalm-suppress InvalidOperand
                         */
                        $this->output->writeln(
                            Style::header("Document") . " " . Style::itemNumber($index + 1, \count($documents)) . ":",
                        );
                        $this->output->writeln(Style::separator('=', \strlen($title)) . "\n");
                        $this->output->writeln(Style::indent($renderer->renderDocument($document)));
                        $this->output->writeln("");
                    }

                    return Command::SUCCESS;
                } catch (\Exception $e) {
                    $this->output->error(\sprintf("Error displaying configuration: %s", $e->getMessage()));

                    return Command::FAILURE;
                }
            },
        );
    }
}
