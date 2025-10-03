<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use SavinMikhail\AddNamedArgumentsRector\AddNamedArgumentsRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    // Register rules for PHP 8.4 migration
    $rectorConfig->sets([
        SetList::PHP_83,
        LevelSetList::UP_TO_PHP_83,
    ]);

    // Skip vendor directories
    $rectorConfig->skip([
        __DIR__ . '/vendor',
    ]);

    $rectorConfig->rules([
        AddNamedArgumentsRector::class,
    ]);
};
