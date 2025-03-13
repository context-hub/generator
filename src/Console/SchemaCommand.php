<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

#[AsCommand(name: 'schema', description: 'Get information about or download the JSON schema for IDE integration')]
final class SchemaCommand extends Command
{
    /**
     * The URL where the JSON schema is hosted
     */
    private const SCHEMA_URL = 'https://raw.githubusercontent.com/butschster/context-generator/refs/heads/main/json-schema.json';

    /**
     * Create a new schema command instance
     */
    public function __construct(
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {
        parent::__construct();
    }

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'download',
                shortcut: 'd',
                mode: InputOption::VALUE_NONE,
                description: 'Download the schema to the current directory',
            )
            ->addOption(
                name: 'output',
                shortcut: 'o',
                mode: InputOption::VALUE_REQUIRED,
                description: 'The file path where the schema should be saved',
                default: 'json-schema.json',
            );
    }

    /**
     * Execute the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $shouldDownload = $input->getOption('download');
        $outputPath = $input->getOption('output');

        // Always show the URL where the schema is hosted
        $io->info('JSON schema URL: ' . self::SCHEMA_URL);

        // If no download requested, exit early
        if (!$shouldDownload) {
            $io->note('Use --download option to download the schema to your current directory');
            return Command::SUCCESS;
        }

        // Download and save the schema
        try {
            $request = $this->requestFactory->createRequest('GET', self::SCHEMA_URL);
            $response = $this->httpClient->sendRequest($request);

            if ($response->getStatusCode() !== 200) {
                $io->error(
                    \sprintf(
                        'Failed to download schema. Server returned status code %d',
                        $response->getStatusCode(),
                    ),
                );
                return Command::FAILURE;
            }

            $schemaContent = $response->getBody()->getContents();

            // Ensure the schema is valid JSON
            $decodedSchema = \json_decode($schemaContent);
            if (\json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Downloaded schema is not valid JSON: ' . \json_last_error_msg());
                return Command::FAILURE;
            }

            // Save schema to file
            if (\file_put_contents($outputPath, $schemaContent) === false) {
                $io->error(\sprintf('Failed to write schema to %s', $outputPath));
                return Command::FAILURE;
            }

            $io->success(\sprintf('Schema successfully downloaded to %s', $outputPath));

            // Provide a hint about how to use the schema
            $io->note([
                'To use this schema in your IDE:',
                '- For PhpStorm/IntelliJ IDEA: Add the json-schema.json file to your project and associate it with your context.json file',
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(\sprintf('Error downloading schema: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
