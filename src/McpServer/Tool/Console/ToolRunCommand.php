<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Console;

use Butschster\ContextGenerator\Application\AppScope;
use Butschster\ContextGenerator\Config\ConfigurationProvider;
use Butschster\ContextGenerator\Config\Exception\ConfigLoaderException;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutor;
use Butschster\ContextGenerator\McpServer\Tool\Command\CommandExecutorInterface;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolSchema;
use Butschster\ContextGenerator\McpServer\Tool\ToolHandlerFactory;
use Butschster\ContextGenerator\McpServer\Tool\ToolProviderInterface;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\Option;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'tool:run',
    description: 'Execute a tool with interactive prompts for arguments',
)]
final class ToolRunCommand extends BaseCommand
{
    #[Argument(
        description: 'The ID of the tool to execute',
    )]
    protected ?string $toolId = null;

    #[Option(
        name: 'config-file',
        shortcut: 'c',
        description: 'Path to configuration file (absolute or relative to current directory).',
    )]
    protected ?string $configPath = null;

    #[Option(
        name: 'arg',
        shortcut: 'a',
        description: 'Tool arguments in format name=value (can be used multiple times)',
    )]
    protected array $argOptions = [];

    #[Option(
        name: 'env',
        shortcut: 'e',
        description: 'Path to .env (like .env.local) file. If not provided, will ignore any .env files',
    )]
    protected ?string $envFileName = null;

    public function __invoke(Container $container, DirectoriesInterface $dirs): int
    {
        return $container->runScope(
            bindings: new Scope(
                bindings: [
                    DirectoriesInterface::class => $dirs
                        ->determineRootPath($this->configPath)
                        ->withEnvFile($this->envFileName),
                ],
            ),
            scope: function (
                Container $container,
                ConfigurationProvider $configProvider,
                DirectoriesInterface $dirs,
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

                    // Load configuration to ensure all tools are properly registered
                    $loader->load();
                } catch (ConfigLoaderException $e) {
                    $this->logger->error('Failed to load configuration', [
                        'error' => $e->getMessage(),
                    ]);

                    $this->output->error(\sprintf('Failed to load configuration: %s', $e->getMessage()));

                    return Command::FAILURE;
                }

                return $container->runScope(
                    bindings: new Scope(
                        name: AppScope::Mcp,
                        bindings: [
                            DirectoriesInterface::class => $dirs,
                            ConfigLoaderInterface::class => $loader,
                            CommandExecutorInterface::class => $container->make(CommandExecutor::class, [
                                'projectRoot' => (string) $dirs->getRootPath(),
                            ]),
                        ],
                    ),
                    scope: function (
                        ToolHandlerFactory $handlerFactory,
                        ToolProviderInterface $toolProvider,
                    ): int {
                        $toolId = $this->toolId;
                        $providedArgs = $this->parseProvidedArguments($this->argOptions);

                        // If no tool ID is provided, list available tools and prompt for selection
                        if (empty($toolId) && $this->input->isInteractive()) {
                            $tool = $this->selectTool($toolProvider);
                            if (!$tool) {
                                return Command::FAILURE;
                            }
                        } elseif (empty($toolId)) {
                            $this->output->error('Tool ID is required in non-interactive mode');
                            return Command::FAILURE;
                        } else {
                            try {
                                $tool = $toolProvider->get($toolId);
                            } catch (\InvalidArgumentException $e) {
                                $this->output->error($e->getMessage());
                                return Command::FAILURE;
                            }
                        }

                        // Get tool handler
                        $handler = $handlerFactory->createHandlerForTool($tool);

                        // Get arguments for tool execution
                        $args = [];

                        if ($tool->schema !== null) {
                            if (!$this->input->isInteractive()) {
                                // In non-interactive mode, validate the provided arguments
                                try {
                                    $args = $this->validateArguments($tool->schema, $providedArgs);
                                } catch (\InvalidArgumentException $e) {
                                    $this->output->error($e->getMessage());
                                    return Command::FAILURE;
                                }
                            } else {
                                // In interactive mode, prompt for arguments
                                $args = $this->promptForArguments($tool, $providedArgs);
                            }
                        }

                        // Execute tool
                        $this->output->writeln(\sprintf('<info>Executing tool "%s"...</info>', $tool->id));

                        try {
                            $startTime = \microtime(true);

                            // Create progress indicator
                            $progressBar = null;
                            if (!$this->output->isVerbose()) {
                                $progressBar = new ProgressBar($this->output);
                                $progressBar->setFormat(' %percent:3s%% [%bar%] %elapsed:6s%');
                                $progressBar->start();
                                $progressBar->display();
                            }

                            // Execute the tool
                            $result = $handler->execute($tool, $args);

                            $executionTime = \microtime(true) - $startTime;

                            // Finish progress bar if it was started
                            if ($progressBar !== null) {
                                $progressBar->finish();
                                $this->output->newLine(2);
                            }

                            // Display results
                            $this->displayResults($tool, $result, $executionTime);

                            return isset($result['success']) && $result['success'] === false ? Command::FAILURE : Command::SUCCESS;
                        } catch (\Throwable $e) {
                            $this->output->error(\sprintf('Error executing tool: %s', $e->getMessage()));
                            $this->logger->error('Tool execution failed', [
                                'id' => $tool->id,
                                'error' => $e->getMessage(),
                                'exception' => $e::class,
                            ]);

                            return Command::FAILURE;
                        }
                    },
                );
            },
        );
    }

    /**
     * Display a list of available tools and prompt for selection.
     */
    private function selectTool(ToolProviderInterface $toolProvider): ?ToolDefinition
    {
        $tools = $toolProvider->all();

        if (empty($tools)) {
            $this->output->error('No tools found');
            return null;
        }

        // Build tool options
        $choices = [];
        $toolMap = [];

        foreach ($tools as $tool) {
            $label = \sprintf('%s (%s)', $tool->id, $tool->description);
            $choices[] = $label;
            $toolMap[$label] = $tool;
        }

        $selectedLabel = $this->choiceQuestion('Select a tool to execute:', $choices);

        return $toolMap[$selectedLabel];
    }

    /**
     * Prompt for tool arguments interactively.
     */
    private function promptForArguments(ToolDefinition $tool, array $providedArgs): array
    {
        $args = $providedArgs;
        $schema = $tool->schema;

        if ($schema === null) {
            return $args;
        }

        $properties = $schema->getProperties();
        $requiredProps = $schema->getRequiredProperties();

        foreach ($properties as $name => $propDef) {
            // Skip if argument is already provided
            if (isset($args[$name])) {
                continue;
            }

            $isRequired = \in_array($name, $requiredProps, true);
            $default = $schema->getDefaultValue($name);
            $type = $propDef['type'] ?? 'string';

            $title = $propDef['title'] ?? $name;


            if (!empty($propDef['description'])) {
                $title = \sprintf(
                    '%s [%s]',
                    $propDef['description'],
                    $title,
                );
            }
            $this->output->section($title);

            $questionText = \sprintf(
                '<info>Provide value</info> (%s%s): ',
                $type,
                $isRequired ? ', required' : '',
            );

            $question = new Question('Provide value', $default);

            // Add validator based on type
            $question->setValidator(static function ($value) use ($name, $type, $isRequired) {
                if ($value === null || $value === '') {
                    if ($isRequired) {
                        throw new \RuntimeException("$name is required");
                    }
                    return null;
                }

                // Validate type
                switch ($type) {
                    case 'number':
                    case 'integer':
                        if (!\is_numeric($value)) {
                            throw new \RuntimeException("$name must be a number");
                        }
                        if ($type === 'integer' && !\filter_var($value, FILTER_VALIDATE_INT)) {
                            throw new \RuntimeException("$name must be an integer");
                        }
                        break;
                    case 'boolean':
                        if (!\in_array(\strtolower((string) $value), ['true', 'false', '1', '0', 'yes', 'no'], true)) {
                            throw new \RuntimeException("$name must be a boolean (true/false, yes/no, 1/0)");
                        }
                        break;
                }

                return $value;
            });

            // For boolean type, use confirmation question
            if ($type === 'boolean') {
                $defaultBool = $default === 'true' || $default === true || $default === 1 || $default === '1';
                $question = new ConfirmationQuestion($questionText, $defaultBool);
            }

            // Prompt for input
            $helper = $this->getHelper('question');
            \assert($helper instanceof QuestionHelper);
            $value = $helper->ask($this->input, $this->output, $question);

            // Handle the value for non-string types
            if ($type === 'boolean' && !\is_string($value)) {
                $value = $value ? 'true' : 'false';
            }

            // Only add non-null values
            if ($value !== null) {
                $args[$name] = $value;
            }
        }

        return $args;
    }

    /**
     * Validate provided arguments against the schema.
     */
    private function validateArguments(ToolSchema $schema, array $args): array
    {
        $required = $schema->getRequiredProperties();
        $properties = $schema->getProperties();

        // Check all required properties are provided
        foreach ($required as $prop) {
            if (!isset($args[$prop])) {
                $defaultValue = $schema->getDefaultValue($prop);
                if ($defaultValue !== null) {
                    $args[$prop] = $defaultValue;
                } else {
                    throw new \InvalidArgumentException(\sprintf('Required argument "%s" is missing', $prop));
                }
            }
        }

        // Validate types
        foreach ($args as $name => $value) {
            if (!isset($properties[$name])) {
                $this->logger->warning(\sprintf('Unknown argument "%s"', $name));
                continue;
            }

            $type = $properties[$name]['type'] ?? 'string';
            switch ($type) {
                case 'integer':
                    if (!\filter_var($value, FILTER_VALIDATE_INT)) {
                        throw new \InvalidArgumentException(
                            \sprintf('Argument "%s" must be an integer, got "%s"', $name, $value),
                        );
                    }
                    break;
                case 'number':
                    if (!\is_numeric($value)) {
                        throw new \InvalidArgumentException(
                            \sprintf('Argument "%s" must be a number, got "%s"', $name, $value),
                        );
                    }
                    break;
                case 'boolean':
                    if (!\in_array(\strtolower((string) $value), ['true', 'false', '1', '0', 'yes', 'no'], true)) {
                        throw new \InvalidArgumentException(
                            \sprintf('Argument "%s" must be a boolean, got "%s"', $name, $value),
                        );
                    }
                    break;
            }
        }

        return $args;
    }

    /**
     * Parse arguments from the command line.
     */
    private function parseProvidedArguments(array $inputArgs): array
    {
        $args = [];

        foreach ($inputArgs as $arg) {
            if (!\str_contains((string) $arg, '=')) {
                $this->output->warning(\sprintf('Invalid argument format: %s (expected name=value)', $arg));
                continue;
            }

            [$name, $value] = \explode('=', (string) $arg, 2);
            $args[\trim($name)] = \trim($value);
        }

        return $args;
    }

    /**
     * Display the results of tool execution.
     */
    private function displayResults(ToolDefinition $tool, array $result, float $executionTime): void
    {
        $this->output->writeln(\sprintf('<info>Tool execution completed in %.2f seconds</info>', $executionTime));

        if ($tool->type === 'run') {
            $this->displayRunResults($result);
        } elseif ($tool->type === 'http') {
            $this->displayHttpResults($result);
        } else {
            // Generic display for any tool type
            $this->output->writeln('<info>Result:</info>');

            if (!empty($result['output'])) {
                $this->output->writeln($result['output']);
            } else {
                $this->output->writeln(\json_encode($result, JSON_PRETTY_PRINT));
            }
        }
    }

    /**
     * Display results for "run" type tools.
     */
    private function displayRunResults(array $result): void
    {
        $success = $result['success'] ?? true;

        if (!$success) {
            $this->output->warning('Status: Failed');
        } else {
            $this->output->success('Status: Success');
        }

        $this->newLine();

        if (isset($result['commands']) && \is_array($result['commands'])) {
            foreach ($result['commands'] as $i => $cmdResult) {
                $cmdSuccess = $cmdResult['success'] ?? true;

                $this->output->title(
                    \sprintf(
                        'Command %s: %s',
                        $i,
                        $cmdResult['command'] ?? 'unknown',
                    ),
                );

                if (!$cmdSuccess) {
                    $this->output->warning('Status: Failed');
                }

                $this->newLine();

                if (!empty($cmdResult['output'])) {
                    $this->output->writeln('Output:');
                    $this->output->writeln($cmdResult['output']);
                }

                $this->output->writeln('');
            }
        } elseif (!empty($result['output'])) {
            $this->output->writeln('Output:');
            $this->output->writeln($result['output']);
        }
    }

    /**
     * Display results for "http" type tools.
     */
    private function displayHttpResults(array $result): void
    {
        if (isset($result['output'])) {
            $outputData = $result['output'];

            // Try to parse JSON output
            $jsonData = \json_decode($outputData, true);

            if (\json_last_error() === JSON_ERROR_NONE && \is_array($jsonData)) {
                foreach ($jsonData as $i => $response) {
                    $success = $response['success'] ?? false;

                    if (!$success) {
                        $this->output->error(
                            \sprintf(
                                'Response %s: %s',
                                $i,
                                'Failed',
                            ),
                        );
                    }

                    if (isset($response['error'])) {
                        $this->output->writeln(\sprintf('<error>Error: %s</error>', $response['error']));
                    }

                    if (isset($response['response'])) {
                        $this->output->writeln('Response data:');
                        $this->output->writeln(\json_encode($response['response'], JSON_PRETTY_PRINT));
                    }

                    $this->output->writeln('');
                }
            } else {
                // Raw output
                $this->output->title('Output:');
                $this->output->writeln($outputData);
            }
        }
    }
}
