<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\Config\Loader\ConfigLoaderInterface;
use Butschster\ContextGenerator\McpServer\Action\Prompts\FilesystemOperationsAction;
use Butschster\ContextGenerator\McpServer\Action\Prompts\GetPromptAction;
use Butschster\ContextGenerator\McpServer\Action\Prompts\ListPromptsAction;
use Butschster\ContextGenerator\McpServer\Action\Prompts\ProjectStructurePromptAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\GenerateConfigAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\GetDocumentContentResourceAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\JsonSchemaResourceAction;
use Butschster\ContextGenerator\McpServer\Action\Resources\ListResourcesAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Context\ContextAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Context\ContextGetAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Context\ContextRequestAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Docs\FetchLibraryDocsAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Docs\LibrarySearchAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\ExecuteCustomToolAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\DirectoryListAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReadAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent\FileReplaceContentAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileWriteAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Git\GitAddAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Git\GitCommitAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Git\GitStatusAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\ListToolsAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Prompts\GetPromptToolAction;
use Butschster\ContextGenerator\McpServer\Action\Tools\Prompts\ListPromptsToolAction;
use Butschster\ContextGenerator\McpServer\Console\McpConfigCommand;
use Butschster\ContextGenerator\McpServer\Console\MCPServerCommand;
use Butschster\ContextGenerator\McpServer\Projects\Actions\ProjectsListToolAction;
use Butschster\ContextGenerator\McpServer\Projects\Actions\ProjectSwitchToolAction;
use Butschster\ContextGenerator\McpServer\Projects\Console\ProjectAddCommand;
use Butschster\ContextGenerator\McpServer\Projects\Console\ProjectCommand;
use Butschster\ContextGenerator\McpServer\Projects\Console\ProjectListCommand;
use Butschster\ContextGenerator\McpServer\Prompt\Console\ListPromptsCommand;
use Butschster\ContextGenerator\McpServer\Prompt\Console\ShowPromptCommand;
use Butschster\ContextGenerator\McpServer\Tool\Console\ToolListCommand;
use Butschster\ContextGenerator\McpServer\Tool\Console\ToolRunCommand;
use Butschster\ContextGenerator\Research\MCP\Tools\CreateEntryToolAction;
use Butschster\ContextGenerator\Research\MCP\Tools\CreateResearchToolAction;
use Butschster\ContextGenerator\Research\MCP\Tools\GetResearchToolAction;
use Butschster\ContextGenerator\Research\MCP\Tools\ListEntriesToolAction;
use Butschster\ContextGenerator\Research\MCP\Tools\ListResearchesToolAction;
use Butschster\ContextGenerator\Research\MCP\Tools\ListTemplatesToolAction;
use Butschster\ContextGenerator\Research\MCP\Tools\ReadEntryToolAction;
use Butschster\ContextGenerator\Research\MCP\Tools\UpdateEntryToolAction;
use Butschster\ContextGenerator\Research\MCP\Tools\UpdateResearchToolAction;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\ConfiguratorInterface;

final class ActionsBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    #[\Override]
    public function defineDependencies(): array
    {
        return [
            McpServerBootloader::class,
            HttpTransportBootloader::class,
        ];
    }

    public function init(EnvironmentInterface $env): void
    {
        $isCommonProject = (bool) $env->get('MCP_PROJECT_COMMON', false);

        $this->config->setDefaults(
            McpConfig::CONFIG,
            [
                'document_name_format' => $env->get('MCP_DOCUMENT_NAME_FORMAT', '[{path}] {description}'),
                'file_operations' => [
                    'enable' => (bool) $env->get('MCP_FILE_OPERATIONS', !$isCommonProject),
                ],
                'context_operations' => [
                    'enable' => (bool) $env->get('MCP_CONTEXT_OPERATIONS', !$isCommonProject),
                ],
                'docs_tools' => [
                    'enable' => (bool) $env->get('MCP_DOCS_TOOLS_ENABLED', true),
                ],
                'prompt_operations' => [
                    'enable' => (bool) $env->get('MCP_PROMPT_OPERATIONS', false),
                ],
                'custom_tools' => [
                    'enable' => (bool) $env->get('MCP_CUSTOM_TOOLS_ENABLE', true),
                    'max_runtime' => (int) $env->get('MCP_TOOL_MAX_RUNTIME', 30),
                ],
                'common_prompts' => [
                    'enable' => (bool) $env->get('MCP_COMMON_PROMPTS', true),
                ],
                'git_operations' => [
                    'enable' => (bool) $env->get('MCP_GIT_OPERATIONS', !$isCommonProject),
                ],
                'project_operations' => [
                    'enable' => (bool) $env->get('MCP_PROJECT_OPERATIONS', true),
                ],
            ],
        );
    }

    public function boot(ConsoleBootloader $console): void
    {
        $console->addCommand(ToolListCommand::class);
        $console->addCommand(ToolRunCommand::class);

        $console->addCommand(MCPServerCommand::class);
        $console->addCommand(McpConfigCommand::class);

        $console->addCommand(ListPromptsCommand::class);
        $console->addCommand(ShowPromptCommand::class);

        $console->addCommand(
            ProjectCommand::class,
            ProjectAddCommand::class,
            ProjectListCommand::class,
        );
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ServerRunnerInterface::class => function (
                McpConfig $config,
                ServerRunner $factory,
                ConfigLoaderInterface $loader,
            ) {
                $loader->load();

                foreach ($this->actions($config) as $action) {
                    $factory->registerAction($action);
                }

                return $factory;
            },
        ];
    }

    private function actions(McpConfig $config): array
    {
        $actions = [
            // Prompts controllers
            GetPromptAction::class,
            ListPromptsAction::class,

            // Resources controllers
            ListResourcesAction::class,
            GetDocumentContentResourceAction::class,

            // Tools controllers
            ListToolsAction::class,
        ];

        if ($config->commonPromptsEnabled()) {
            $actions = [
                ProjectStructurePromptAction::class,
                FilesystemOperationsAction::class,
                ...$actions,
                JsonSchemaResourceAction::class,
                GenerateConfigAction::class,
            ];
        }

        if ($config->isPromptOperationsEnabled()) {
            $actions = [
                ...$actions,
                GetPromptToolAction::class,
                ListPromptsToolAction::class,
            ];
        }

        if ($config->isContextOperationsEnabled()) {
            $actions = [
                ...$actions,
                ContextRequestAction::class,
                ContextGetAction::class,
                ContextAction::class,
            ];
        }

        if ($config->isDocsToolsEnabled()) {
            $actions = [
                ...$actions,
                LibrarySearchAction::class,
                FetchLibraryDocsAction::class,
            ];
        }

        if ($config->isFileOperationsEnabled()) {
            $actions = [
                ...$actions,
                FileReadAction::class,
                FileReplaceContentAction::class,
                FileWriteAction::class,
                DirectoryListAction::class,
            ];
        }

        if ($config->isProjectOperationsEnabled()) {
            $actions = [
                ...$actions,
                ProjectsListToolAction::class,
                ProjectSwitchToolAction::class,
            ];
        }

        $actions = [
            ...$actions,
            ListTemplatesToolAction::class,
            CreateResearchToolAction::class,
            ListResearchesToolAction::class,
            GetResearchToolAction::class,
            CreateEntryToolAction::class,
            ListEntriesToolAction::class,
            ReadEntryToolAction::class,
            UpdateEntryToolAction::class,
            UpdateResearchToolAction::class,
        ];

        if ($config->isGitOperationsEnabled()) {
            $actions[] = GitStatusAction::class;
            $actions[] = GitAddAction::class;
            $actions[] = GitCommitAction::class;
        }

        // Should be last
        if ($config->isCustomToolsEnabled()) {
            $actions = [
                ...$actions,
                ExecuteCustomToolAction::class,
            ];
        }

        return \array_unique($actions);
    }
}
