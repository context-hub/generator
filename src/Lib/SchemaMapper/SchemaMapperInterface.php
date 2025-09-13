<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\SchemaMapper;

interface SchemaMapperInterface
{
    /**
     * @param class-string $class
     */
    public function toJsonSchema(string $class): array;

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function toObject(array $json, string $class): object;
}
