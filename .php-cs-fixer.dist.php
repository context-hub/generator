<?php

declare(strict_types=1);

$finder = (new \PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
    ])
    ->exclude('database/Migration')
    ->append([
        __FILE__,
    ]);

$config = (new \PhpCsFixer\Config())
    ->setFinder($finder);

(new \Internal\CodingStandard\PhpCsFixerCodingStandard())->applyTo($config);

return $config;
