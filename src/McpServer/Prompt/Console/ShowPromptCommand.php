<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Console;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\Console\Renderer\Style;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;
use Butschster\ContextGenerator\McpServer\Prompt\PromptProviderInterface;
use Butschster\ContextGenerator\McpServer\Prompt\PromptType;
use Mcp\Types\Prompt;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;

#[AsCommand(
    name: 'prompt:show',
    description: 'Show detailed information about a specific prompt',
)]
final class ShowPromptCommand extends BaseCommand
{
    #[Argument(
        name: 'id',
        description: 'The ID of the prompt to display',
    )]
    protected string $promptId;

    #[Option(
        name: 'config-file',
        shortcut: 'c',
        description: 'Path to configuration file (absolute or relative to current directory).',
    )]
    protected ?string $configPath = null;

    #[Option(
        name: 'with-messages',
        shortcut: 'm',
        description: 'Show the full message content',
    )]
    protected bool $withMessages = false;

    #[Option(
        name: 'with-extensions',
        shortcut: 'e',
        description: 'Show extension details',
    )]
    protected bool $withExtensions = false;

    public function __invoke(Container $container, DirectoriesInterface $dirs): int
    {
        return $container->runScope(
            bindings: new Scope(
                name: AppScope::Compiler,
                bindings: [
                    DirectoriesInterface::class => $dirs->determineRootPath($this->configPath),
                ],
            ),
            scope: function (
                ConfigurationProvider $configProvider,
                PromptProviderInterface $promptProvider,
            ) {
                try {
                    // Get the appropriate loader based on options provided
                    if ($this->configPath !== null) {
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

                    $this->output->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));

                    return Command::FAILURE;
                }

                // Load configuration to make sure all prompts are properly registered
                $loader->load();

                // Check if prompt exists
                if (!$promptProvider->has($this->promptId)) {
                    $this->output->error(\sprintf('Prompt with ID "%s" not found.', $this->promptId));

                    // Suggest similar prompts
                    $this->suggestSimilarPrompts($promptProvider);

                    return Command::FAILURE;
                }

                // Get the prompt
                $prompt = $promptProvider->get($this->promptId);

                // Display prompt details
                return $this->displayPromptDetails($prompt);
            },
        );
    }

    /**
     * Displays detailed information about a prompt.
     */
    private function displayPromptDetails(PromptDefinition $prompt): int
    {
        $this->output->title(\sprintf('Prompt: %s', $prompt->id));

        // Basic information table
        $this->displayBasicInfo($prompt);

        // Arguments table
        if (!empty($prompt->prompt->arguments)) {
            $this->displayArguments($prompt->prompt);
        }

        // Extensions
        if ($this->withExtensions && !empty($prompt->extensions)) {
            $this->displayExtensions($prompt);
        }

        // Messages
        if ($this->withMessages && !empty($prompt->messages)) {
            $this->displayMessages($prompt);
        }

        return Command::SUCCESS;
    }

    /**
     * Displays basic prompt information.
     */
    private function displayBasicInfo(PromptDefinition $prompt): void
    {
        $this->output->writeln('');
        $this->output->writeln(Style::section('Basic Information'));

        $table = new Table($this->output);
        $table->setStyle('box');

        $rows = [
            ['ID', $prompt->id],
            ['Type', $this->formatPromptType($prompt->type)],
        ];

        if ($prompt->prompt->description !== null) {
            $rows[] = ['Description', $prompt->prompt->description];
        }

        if (!empty($prompt->tags)) {
            $rows[] = ['Tags', \implode(', ', $prompt->tags)];
        }

        $rows[] = ['Messages', \count($prompt->messages)];
        $rows[] = ['Arguments', \count($prompt->prompt->arguments)];
        $rows[] = ['Extensions', \count($prompt->extensions)];

        foreach ($rows as $row) {
            $table->addRow([
                new TableCell($row[0], ['style' => new TableCellStyle(['fg' => 'yellow'])]),
                $row[1],
            ]);
        }

        $table->render();
    }

    /**
     * Displays prompt arguments.
     */
    private function displayArguments(Prompt $prompt): void
    {
        $this->output->writeln('');
        $this->output->writeln(Style::section('Arguments'));

        $table = new Table($this->output);
        $table->setHeaders(['Name', 'Required', 'Description']);
        $table->setStyle('box');

        foreach ($prompt->arguments as $arg) {
            $table->addRow([
                new TableCell($arg->name, ['style' => new TableCellStyle(['fg' => 'cyan'])]),
                $arg->required ? Style::success('Yes') : Style::muted('No'),
                $arg->description ?? Style::muted('(no description)'),
            ]);
        }

        $table->render();
    }

    /**
     * Displays prompt extensions.
     */
    private function displayExtensions(PromptDefinition $prompt): void
    {
        $this->output->writeln('');
        $this->output->writeln(Style::section('Extensions'));

        foreach ($prompt->extensions as $index => $extension) {
            $this->output->writeln(
                \sprintf('  %s. %s', $index + 1, Style::property('Template ID:') . ' ' . $extension->templateId),
            );

            if (!empty($extension->arguments)) {
                $this->output->writeln('     ' . Style::property('Arguments:'));
                foreach ($extension->arguments as $arg) {
                    $this->output->writeln(\sprintf('       • %s: %s', Style::value($arg->name), $arg->value));
                }
            }

            if ($index < \count($prompt->extensions) - 1) {
                $this->output->writeln('');
            }
        }
    }

    /**
     * Displays prompt messages.
     */
    private function displayMessages(PromptDefinition $prompt): void
    {
        $this->output->writeln('');
        $this->output->writeln(Style::section('Messages'));

        foreach ($prompt->messages as $index => $message) {
            $this->output->writeln('');
            $this->output->writeln(
                \sprintf('  %s. %s', $index + 1, Style::property('Role:') . ' ' . Style::value($message->role->value)),
            );

            // Show content preview (first few lines)
            $content = $message->content->text ?? '';
            $lines = \explode("\n", $content);

            $this->output->writeln('     ' . Style::property('Content:'));

            // Show first 10 lines or all if less
            $displayLines = \array_slice($lines, 0, 10);
            foreach ($displayLines as $line) {
                $this->output->writeln('       ' . Style::muted($line));
            }

            // Show truncation indicator if there are more lines
            if (\count($lines) > 10) {
                $this->output->writeln('       ' . Style::muted(\sprintf('... (%d more lines)', \count($lines) - 10)));
            }

            $this->output->writeln('');
            $this->output->writeln('     ' . Style::property('Statistics:'));
            $this->output->writeln(\sprintf('       • Lines: %s', Style::count(\count($lines))));
            $this->output->writeln(\sprintf('       • Characters: %s', Style::count(\strlen($content))));
            $this->output->writeln(\sprintf('       • Words: %s', Style::count(\str_word_count($content))));
        }
    }

    /**
     * Suggests similar prompts when the requested prompt is not found.
     */
    private function suggestSimilarPrompts(PromptProviderInterface $promptProvider): void
    {
        $allPrompts = $promptProvider->all();

        if (empty($allPrompts)) {
            $this->output->writeln('No prompts are available.');
            return;
        }

        // Find prompts with similar IDs (simple string similarity)
        $suggestions = [];
        foreach ($allPrompts as $id => $prompt) {
            $similarity = 0;
            \similar_text($this->promptId, $id, $similarity);
            if ($similarity > 30) { // 30% similarity threshold
                $suggestions[] = ['id' => $id, 'similarity' => $similarity];
            }
        }

        // Sort by similarity
        \usort($suggestions, static fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        if (!empty($suggestions)) {
            $this->output->writeln('');
            $this->output->writeln('Did you mean one of these?');
            foreach (\array_slice($suggestions, 0, 3) as $suggestion) {
                $this->output->writeln(\sprintf('  • %s', Style::value($suggestion['id'])));
            }
        } else {
            $this->output->writeln('');
            $this->output->writeln('Available prompts:');
            $promptIds = \array_keys($allPrompts);
            \sort($promptIds);
            foreach (\array_slice($promptIds, 0, 5) as $id) {
                $this->output->writeln(\sprintf('  • %s', Style::value($id)));
            }

            if (\count($promptIds) > 5) {
                $this->output->writeln(\sprintf('  ... and %d more', \count($promptIds) - 5));
                $this->output->writeln('');
                $this->output->writeln('Use ' . Style::command('prompts:list') . ' to see all available prompts.');
            }
        }
    }

    /**
     * Formats the prompt type for display.
     */
    private function formatPromptType(PromptType $type): string
    {
        return match ($type) {
            PromptType::Prompt => Style::success('Prompt'),
            PromptType::Template => Style::info('Template'),
        };
    }
}
