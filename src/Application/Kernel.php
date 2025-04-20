<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application;

use Butschster\ContextGenerator\Application\Bootloader\ComposerClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConfigurationBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ContentRendererBootloader;
use Butschster\ContextGenerator\Application\Bootloader\CoreBootloader;
use Butschster\ContextGenerator\Application\Bootloader\GithubClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\GitlabClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\HttpClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\LoggerBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ModifierBootloader;
use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Bootloader\VariableBootloader;
use Butschster\ContextGenerator\McpServer\McpServerBootloader;
use Butschster\ContextGenerator\McpServer\Prompt\McpPromptBootloader;
use Butschster\ContextGenerator\Modifier\PhpContentFilter\PhpContentFilterBootloader;
use Butschster\ContextGenerator\Modifier\PhpDocs\PhpDocsModifierBootloader;
use Butschster\ContextGenerator\Modifier\PhpSignature\PhpSignatureModifierBootloader;
use Butschster\ContextGenerator\Modifier\Sanitizer\SanitizerModifierBootloader;
use Butschster\ContextGenerator\Source\Composer\ComposerSourceBootloader;
use Butschster\ContextGenerator\Source\File\FileSourceBootloader;
use Butschster\ContextGenerator\Source\GitDiff\GitDiffSourceBootloader;
use Butschster\ContextGenerator\Source\Github\GithubSourceBootloader;
use Butschster\ContextGenerator\Source\Gitlab\GitlabSourceBootloader;
use Butschster\ContextGenerator\Source\MCP\McpSourceBootloader;
use Butschster\ContextGenerator\Source\Registry\SourceRegistryBootloader;
use Butschster\ContextGenerator\Source\Text\TextSourceBootloader;
use Butschster\ContextGenerator\Source\Tree\TreeSourceBootloader;
use Butschster\ContextGenerator\Source\Url\UrlSourceBootloader;
use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Exception\BootException;

class Kernel extends AbstractKernel
{
    #[\Override]
    protected function defineSystemBootloaders(): array
    {
        return [
            ConfigurationBootloader::class,
            LoggerBootloader::class,
            ConsoleBootloader::class,
        ];
    }

    #[\Override]
    protected function defineBootloaders(): array
    {
        return [
            CoreBootloader::class,
            HttpClientBootloader::class,
            GitlabClientBootloader::class,
            GithubClientBootloader::class,
            ComposerClientBootloader::class,
            ConfigLoaderBootloader::class,
            ModifierBootloader::class,
            ContentRendererBootloader::class,
            VariableBootloader::class,
            SourceFetcherBootloader::class,
            SourceRegistryBootloader::class,

            // Sources
            TextSourceBootloader::class,
            FileSourceBootloader::class,
            ComposerSourceBootloader::class,
            UrlSourceBootloader::class,
            GithubSourceBootloader::class,
            GitlabSourceBootloader::class,
            GitDiffSourceBootloader::class,
            TreeSourceBootloader::class,
            McpSourceBootloader::class,

            // Modifiers
            PhpContentFilterBootloader::class,
            PhpDocsModifierBootloader::class,
            PhpSignatureModifierBootloader::class,
            SanitizerModifierBootloader::class,

            // MCP Server
            McpServerBootloader::class,
            McpPromptBootloader::class,
        ];
    }

    /**
     * Each application can define it's own boot sequence.
     */
    protected function bootstrap(): void {}

    /**
     * Normalizes directory list and adds all required aliases.
     */
    protected function mapDirectories(array $directories): array
    {
        if (!isset($directories['root'])) {
            throw new BootException('Missing required directory `root`');
        }

        return \array_merge(
            [
                // custom directories
            ],
            $directories,
        );
    }
}
