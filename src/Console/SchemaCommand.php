<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\JsonSchema;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpException;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Spiral\Console\Attribute\Option;
use Spiral\Files\FilesInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'schema',
    description: 'Get information about or download the JSON schema for IDE integration',
    aliases: ['json-schema'],
)]
final class SchemaCommand extends BaseCommand
{
    #[Option(
        name: 'download',
        shortcut: 'd',
        description: 'Download the schema to the current directory',
    )]
    protected bool $download = false;

    #[Option(
        name: 'output',
        shortcut: 'o',
        description: 'The file path where the schema should be saved',
    )]
    protected string $outputPath = 'json-schema.json';

    /**
     * Execute the command
     */
    public function __invoke(
        HttpClientInterface $httpClient,
        FilesInterface $files,
        DirectoriesInterface $dirs,
    ): int {
        $outputPath = $dirs->getFilePath($this->outputPath);

        // Always show the URL where the schema is hosted
        $this->output->info('JSON schema URL: ' . JsonSchema::SCHEMA_URL);

        // If no download requested, exit early
        if (!$this->download) {
            $this->output->note('Use --download option to download the schema to your current directory');
            return Command::SUCCESS;
        }

        // Download and save the schema
        try {
            $response = $httpClient->get(JsonSchema::SCHEMA_URL, [
                'User-Agent' => 'Context-Generator-Schema-Download',
                'Accept' => 'application/json',
            ]);

            if (!$response->isSuccess()) {
                $this->output->error(
                    \sprintf(
                        'Failed to download schema. Server returned status code %d',
                        $response->getStatusCode(),
                    ),
                );
                return Command::FAILURE;
            }

            $schemaContent = $response->getBody();

            // Validate that the schema is proper JSON
            try {
                // This will throw an exception if the content is not valid JSON
                $response->getJson();
            } catch (HttpException $e) {
                $this->output->error('Downloaded schema is not valid JSON: ' . $e->getMessage());
                return Command::FAILURE;
            }

            // Save schema to file
            if (!$files->write($this->outputPath, $schemaContent)) {
                $this->output->error(\sprintf('Failed to write schema to %s', $outputPath));
                return Command::FAILURE;
            }

            $this->output->success(\sprintf('Schema successfully downloaded to %s', $outputPath));

            // Provide a hint about how to use the schema
            $this->output->note([
                'To use this schema in your IDE:',
                '- For PhpStorm/IntelliJ IDEA: Add the json-schema.json file to your project and associate it with your context.json file',
                '- For VS Code: Add the schema to your settings.json in the "json.schemas" section',
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->output->error(\sprintf('Error downloading schema: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
