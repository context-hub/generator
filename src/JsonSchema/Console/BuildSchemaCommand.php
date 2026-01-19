<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Console;

use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\JsonSchema\Exception\SchemaValidationException;
use Butschster\ContextGenerator\JsonSchema\SchemaDefinitionRegistry;
use Spiral\Console\Attribute\Option;
use Spiral\Files\FilesInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'schema:build',
    description: 'Build JSON schema from registered definitions',
)]
final class BuildSchemaCommand extends BaseCommand
{
    #[Option(
        name: 'output',
        shortcut: 'o',
        description: 'Output file path (relative to project root)',
    )]
    protected string $outputPath = 'json-schema.json';

    #[Option(
        name: 'validate',
        description: 'Only validate schema without writing file',
    )]
    protected bool $validateOnly = false;

    #[Option(
        name: 'pretty',
        description: 'Pretty print JSON output',
    )]
    protected bool $pretty = true;

    public function __invoke(
        SchemaDefinitionRegistry $registry,
        FilesInterface $files,
        DirectoriesInterface $dirs,
    ): int {
        $this->output->title('JSON Schema Builder');

        $definitionCount = $registry->count();
        $this->output->writeln(\sprintf(
            'Found <info>%d</info> registered definitions',
            $definitionCount,
        ));

        if ($definitionCount === 0) {
            $this->output->warning('No definitions registered. Schema will be minimal.');
        }

        // Build schema (includes validation)
        try {
            $schema = $registry->build();
        } catch (SchemaValidationException $e) {
            $this->output->error('Schema validation failed');

            foreach ($e->errors as $error) {
                $this->output->writeln("  <error>✗</error> {$error}");
            }

            if ($e->missingReferences !== []) {
                $this->output->newLine();
                $this->output->writeln('<comment>Missing definitions:</comment>');
                foreach ($e->missingReferences as $ref) {
                    $this->output->writeln("  - {$ref}");
                }
            }

            return Command::FAILURE;
        }

        $this->output->success('Schema validated successfully');

        if ($this->validateOnly) {
            $this->output->note('Validate-only mode - no file written');
            return Command::SUCCESS;
        }

        // Encode to JSON
        $flags = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;
        if ($this->pretty) {
            $flags |= \JSON_PRETTY_PRINT;
        }

        $json = \json_encode($schema, $flags);
        if ($json === false) {
            $this->output->error('Failed to encode schema to JSON: ' . \json_last_error_msg());
            return Command::FAILURE;
        }

        $fullPath = $dirs->getRootPath()->join($this->outputPath)->toString();

        if (!$files->write($fullPath, $json . "\n")) {
            $this->output->error("Failed to write schema to {$fullPath}");
            return Command::FAILURE;
        }

        $this->output->success("Schema written to {$this->outputPath}");

        $this->output->table(
            ['Metric', 'Value'],
            [
                ['Definitions', (string) \count($schema['definitions'] ?? [])],
                ['Root properties', (string) \count($schema['properties'] ?? [])],
                ['File size', $this->formatBytes(\strlen($json))],
            ],
        );

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1024 * 1024) {
            return \round($bytes / 1024, 1) . ' KB';
        }
        return \round($bytes / (1024 * 1024), 2) . ' MB';
    }
}
