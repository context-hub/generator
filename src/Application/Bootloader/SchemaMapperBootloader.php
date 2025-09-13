<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DirectoriesInterface;
use Spiral\JsonSchemaGenerator\Generator as JsonSchemaGenerator;
use Butschster\ContextGenerator\Lib\SchemaMapper\SchemaMapperInterface;
use Butschster\ContextGenerator\Lib\SchemaMapper\Valinor\MapperBuilder;
use Butschster\ContextGenerator\Lib\SchemaMapper\Valinor\SchemaMapper;

final class SchemaMapperBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            SchemaMapperInterface::class => static function (
                DirectoriesInterface $dirs,
                JsonSchemaGenerator $generator,
            ): SchemaMapper {
                $mapper = new MapperBuilder();

                $treeMapper = $mapper->build();

                return new SchemaMapper($generator, $treeMapper);
            },
        ];
    }
}
