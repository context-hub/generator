<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\Dto;

use Spiral\JsonSchemaGenerator\Attribute\Field;

final readonly class TreeViewConfig
{
    public function __construct(
        #[Field(
            description: 'Show file sizes in tree view',
            default: false,
        )]
        public bool $showSize = false,
        #[Field(
            description: 'Show last modified dates in tree view',
            default: false,
        )]
        public bool $showLastModified = false,
        #[Field(
            description: 'Show character counts in tree view',
            default: false,
        )]
        public bool $showCharCount = false,
        #[Field(
            description: 'Include files in tree view (false to show only directories)',
            default: true,
        )]
        public bool $includeFiles = true,
    ) {}
}