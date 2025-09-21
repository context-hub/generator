<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Resources\Service;

use Butschster\ContextGenerator\DirectoriesInterface;
use Spiral\Files\FilesInterface;

/**
 * Service for handling JSON Schema operations, extracted from JsonSchemaResourceAction.
 */
final readonly class JsonSchemaService
{
    public function __construct(
        private FilesInterface $files,
        private DirectoriesInterface $dirs,
    ) {}

    /**
     * Get the complete JSON schema as an array.
     */
    public function getFullSchema(): array
    {
        return \json_decode(
            $this->files->read($this->dirs->getJsonSchemaPath()),
            associative: true,
        );
    }

    /**
     * Get simplified JSON schema (same as used in the resource action).
     */
    public function getSimplifiedSchema(): array
    {
        $schema = $this->getFullSchema();

        // Clean up the schema for prompt consumption
        unset(
            $schema['properties']['import'],
            $schema['properties']['settings'],
            $schema['definitions']['document']['properties']['modifiers'],
            $schema['definitions']['source']['properties']['modifiers'],
            $schema['definitions']['urlSource'],
            $schema['definitions']['githubSource'],
            $schema['definitions']['textSource'],
            $schema['definitions']['composerSource'],
            $schema['definitions']['php-content-filter'],
            $schema['definitions']['php-docs'],
            $schema['definitions']['sanitizer'],
            $schema['definitions']['modifiers'],
            $schema['definitions']['visibilityOptions'],
        );

        $schema['definitions']['source']['properties']['type']['enum'] = ['file', 'tree'];

        return $schema;
    }
}
