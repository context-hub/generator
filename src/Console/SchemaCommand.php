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
        $startTime = \microtime(true);

        // Display command title
        $this->outputService->title('JSON Schema Management');

        // Display command options using key-value format
        $this->outputService->section('Schema Information');
        $this->outputService->keyValue('Schema URL', JsonSchema::SCHEMA_URL);

        $outputPath = (string) $dirs->getRootPath()->join($this->outputPath);

        if ($this->download) {
            $this->outputService->keyValue('Output Path', $outputPath);
        }

        // If no download requested, show info and exit early
        if (!$this->download) {
            $this->outputService->info('Schema Download Not Requested');

            // Provide note about available options
            $this->outputService->note([
                'Available options:',
                '- Use --download (-d) to download the schema to the current directory',
                '- Use --output=PATH (-o) to specify a custom output path',
            ]);

            return Command::SUCCESS;
        }

        // Show downloading status section
        $this->outputService->section('Downloading Schema');
        $this->outputService->info('Connecting to schema server...');

        // Download and save the schema
        try {
            $response = $httpClient->get(JsonSchema::SCHEMA_URL, [
                'User-Agent' => 'Context-Generator-Schema-Download',
                'Accept' => 'application/json',
            ]);

            if (!$response->isSuccess()) {
                $statusCode = $response->getStatusCode();
                $statusCodeFormatted = $this->outputService->highlight((string) $statusCode, 'red', true);

                $this->outputService->error(
                    \sprintf(
                        'Failed to download schema. Server returned status code %s',
                        $statusCodeFormatted,
                    ),
                );
                return Command::FAILURE;
            }

            $schemaContent = $response->getBody();
            $contentSize = \strlen($schemaContent);
            $this->outputService->keyValue(
                'Content Size',
                $this->outputService->formatCount(\round($contentSize / 1024, 2)) . ' KB',
            );

            // Validate JSON and show schema validation section
            $this->outputService->section('Validating Schema');

            try {
                // This will throw an exception if the content is not valid JSON
                $jsonData = $response->getJson();
                $schemaProperties = \count((array) $jsonData->properties ?? []);

                $this->outputService->keyValue(
                    'Schema Version',
                    $jsonData->{'$schema'} ?? 'Unknown',
                );
                $this->outputService->keyValue(
                    'Root Properties',
                    $this->outputService->formatCount($schemaProperties),
                );

                $this->outputService->success('Schema validation successful');
            } catch (HttpException $e) {
                $this->outputService->error('Downloaded content is not valid JSON: ' . $e->getMessage());
                return Command::FAILURE;
            }

            // Save schema to file section
            $this->outputService->section('Saving Schema');

            try {
                // Ensure directory exists
                $directory = \dirname($outputPath);
                $files->ensureDirectory($directory);

                if (!$files->write($this->outputPath, $schemaContent)) {
                    $this->outputService->error(\sprintf('Failed to write schema to %s', $outputPath));
                    return Command::FAILURE;
                }

                // Status list of completed actions
                $statusList = [
                    'Download' => ['status' => 'success', 'message' => 'Complete'],
                    'Validation' => ['status' => 'success', 'message' => 'JSON is valid'],
                    'File creation' => ['status' => 'success', 'message' => $outputPath],
                ];

                $this->outputService->statusList($statusList);

                // Display execution time using SummaryRenderer
                $elapsedTime = \microtime(true) - $startTime;
                $summaryRenderer = $this->outputService->getSummaryRenderer();
                $summaryRenderer->renderTimeSummary($elapsedTime, 'Download time');

                // Show success and usage instructions
                $this->outputService->success(
                    \sprintf(
                        'Schema successfully downloaded to %s',
                        $this->outputService->highlight($outputPath, 'bright-cyan'),
                    ),
                );

                // Provide IDE usage tips
                $listRenderer = $this->outputService->getListRenderer();
                $this->outputService->section('IDE Integration');

                $ideUsage = [
                    'PhpStorm/IntelliJ IDEA' => 'Add the json-schema.json file to your project and associate it with your context.json file',
                    'VS Code' => 'Add the schema to your settings.json in the "json.schemas" section',
                    'Other IDEs' => 'Refer to your IDE documentation for JSON schema integration',
                ];

                $listRenderer->renderDefinitionList($ideUsage);
            } catch (\Throwable $e) {
                $this->outputService->error(\sprintf('Failed to save schema: %s', $e->getMessage()));
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->outputService->error(\sprintf('Error downloading schema: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
