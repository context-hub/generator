<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Spiral\Core\InjectableConfig;

final class McpConfig extends InjectableConfig
{
    public const string CONFIG = 'mcp';

    protected array $config = [
        'ctx_document_name_format' => '[{path}] {description}',

        'file_operations' => [
            'enable' => true,
        ],
    ];

    public function getDocumentNameFormat(string $path, string $description, string $tags): string
    {
        return \str_replace(
            ['{path}', '{description}', '{tags}'],
            [$path, $description, $tags],
            $this->config['ctx_document_name_format'],
        );
    }

    public function isFileOperationsEnabled(): bool
    {
        return $this->config['file_operations']['enable'] ?? false;
    }
}
