<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Spiral\Core\InjectableConfig;

final class McpConfig extends InjectableConfig
{
    public const string CONFIG = 'mcp';

    protected array $config = [
        'document_name_format' => '[{path}] {description}',
        'common_prompts' => [
            'enable' => true,
        ],
        'file_operations' => [
            'enable' => true,
            'write' => true,
            'apply-patch' => false,
            'directories-list' => true,
        ],
        'context_operations' => [
            'enable' => true,
        ],
        'prompt_operations' => [
            'enable' => true,
        ],
        'docs_tools' => [
            'enable' => false,
        ],
        'custom_tools' => [
            'enable' => true,
            'max_runtime' => 30,
        ],
    ];

    public function getDocumentNameFormat(string $path, string $description, string $tags): string
    {
        return \str_replace(
            ['{path}', '{description}', '{tags}'],
            [$path, $description, $tags],
            (string) $this->config['document_name_format'] ?: '',
        );
    }

    public function isFileOperationsEnabled(): bool
    {
        return $this->config['file_operations']['enable'] ?? false;
    }

    public function isFileWriteEnabled(): bool
    {
        return $this->config['file_operations']['write'] ?? false;
    }

    public function isFileApplyPatchEnabled(): bool
    {
        return $this->config['file_operations']['apply-patch'] ?? false;
    }

    public function isFileDirectoriesListEnabled(): bool
    {
        return $this->config['file_operations']['directories-list'] ?? false;
    }

    public function isContextOperationsEnabled(): bool
    {
        return $this->config['context_operations']['enable'] ?? false;
    }

    public function isPromptOperationsEnabled(): bool
    {
        return $this->config['prompt_operations']['enable'] ?? false;
    }

    public function isDocsToolsEnabled(): bool
    {
        return $this->config['docs_tools']['enable'] ?? true;
    }

    public function commonPromptsEnabled(): bool
    {
        return $this->config['common_prompts']['enable'] ?? true;
    }

    public function isCustomToolsEnabled(): bool
    {
        return $this->config['custom_tools']['enable'] ?? true;
    }
}
