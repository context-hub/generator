<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application;

use Butschster\ContextGenerator\Application\Bootloader\ComposerClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConfigLoaderBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConfigurationBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ContentRendererBootloader;
use Butschster\ContextGenerator\Application\Bootloader\Context7ClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\CoreBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ExcludeBootloader;
use Butschster\ContextGenerator\Application\Bootloader\GithubClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\GitlabClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\HttpClientBootloader;
use Butschster\ContextGenerator\Application\Bootloader\LoggerBootloader;
use Butschster\ContextGenerator\Application\Bootloader\ModifierBootloader;
use Butschster\ContextGenerator\Application\Bootloader\SchemaMapperBootloader;
use Butschster\ContextGenerator\Application\Bootloader\SourceFetcherBootloader;
use Butschster\ContextGenerator\Application\Bootloader\VariableBootloader;
use Butschster\ContextGenerator\Research\ResearchBootloader;
use Butschster\ContextGenerator\McpServer\McpServerBootloader;
use Butschster\ContextGenerator\Template\TemplateSystemBootloader;
use Butschster\ContextGenerator\Modifier\PhpContentFilter\PhpContentFilterBootloader;
use Butschster\ContextGenerator\Modifier\PhpDocs\PhpDocsModifierBootloader;
use Butschster\ContextGenerator\Modifier\PhpSignature\PhpSignatureModifierBootloader;
use Butschster\ContextGenerator\Modifier\Sanitizer\SanitizerModifierBootloader;
use Butschster\ContextGenerator\Source\Composer\ComposerSourceBootloader;
use Butschster\ContextGenerator\Source\Docs\DocsSourceBootloader;
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
use Spiral\Boot\BootloadManagerInterface;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\Exception\BootException;
use Spiral\Core\Container;
use Spiral\Exceptions\ExceptionHandlerInterface;

class Kernel extends AbstractKernel
{
    protected function __construct(
        Container $container,
        ExceptionHandlerInterface $exceptionHandler,
        BootloadManagerInterface $bootloader,
        array $directories,
    ) {
        parent::__construct($container, $exceptionHandler, $bootloader, $directories);

        $container->bindSingleton(
            DirectoriesInterface::class,
            new Directories($this->mapDirectories($directories)),
        );
    }

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
            Context7ClientBootloader::class,
            ComposerClientBootloader::class,
            ConfigLoaderBootloader::class,
            VariableBootloader::class,
            ExcludeBootloader::class,
            ModifierBootloader::class,
            ContentRendererBootloader::class,
            SourceFetcherBootloader::class,
            SourceRegistryBootloader::class,
            SchemaMapperBootloader::class,

            // Template System
            TemplateSystemBootloader::class,

            // Research
            ResearchBootloader::class,

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
            DocsSourceBootloader::class,

            // Modifiers
            PhpContentFilterBootloader::class,
            PhpDocsModifierBootloader::class,
            PhpSignatureModifierBootloader::class,
            SanitizerModifierBootloader::class,

            // MCP Server
            McpServerBootloader::class,
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
                'runtime' => $directories['root'] . '/runtime',
            ],
            $directories,
        );
    }
}
