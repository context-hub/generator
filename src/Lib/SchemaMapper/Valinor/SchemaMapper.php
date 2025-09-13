<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\SchemaMapper\Valinor;

use CuyZ\Valinor\Mapper\TreeMapper;
use Spiral\JsonSchemaGenerator\Generator as JsonSchemaGenerator;
use Butschster\ContextGenerator\Lib\SchemaMapper\SchemaMapperInterface;

final readonly class SchemaMapper implements SchemaMapperInterface
{
    public function __construct(
        private JsonSchemaGenerator $generator,
        private TreeMapper $mapper,
    ) {}

    public function toJsonSchema(string $class): array
    {
        if (\json_validate($class)) {
            return \json_decode($class, associative: true);
        }

        if (\class_exists($class)) {
            return $this->generator->generate($class)->jsonSerialize();
        }

        throw new \InvalidArgumentException(\sprintf('Invalid class or JSON schema provided: %s', $class));
    }

    public function toObject(array $json, string $class): object
    {
        return $this->mapper->map($class, $json);
    }
}
