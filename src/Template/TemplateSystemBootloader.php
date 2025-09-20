<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template;

use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\Template\Analysis\Analyzer\ComposerAnalyzer;
use Butschster\ContextGenerator\Template\Analysis\Analyzer\FallbackAnalyzer;
use Butschster\ContextGenerator\Template\Analysis\Analyzer\LaravelAnalyzer;
use Butschster\ContextGenerator\Template\Analysis\Analyzer\PackageJsonAnalyzer;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalysisService;
use Butschster\ContextGenerator\Template\Analysis\Util\ComposerFileReader;
use Butschster\ContextGenerator\Template\Analysis\Util\ProjectStructureDetector;
use Butschster\ContextGenerator\Template\Console\ListCommand;
use Butschster\ContextGenerator\Template\Definition\ExpressTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\GenericPhpTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\LaravelTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\NextJsTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\NuxtTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\ReactTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\SpiralTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\SymfonyTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\TemplateDefinitionRegistry;
use Butschster\ContextGenerator\Template\Definition\VueTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\Yii2TemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\Yii3TemplateDefinition;
use Butschster\ContextGenerator\Template\Provider\BuiltinTemplateProvider;
use Butschster\ContextGenerator\Template\Registry\TemplateRegistry;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;
use Spiral\Files\FilesInterface;

/**
 * Bootloader for the template system
 */
#[Singleton]
final class TemplateSystemBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            TemplateRegistry::class => TemplateRegistry::class,
            TemplateDefinitionRegistry::class => static fn(
            ): TemplateDefinitionRegistry => new TemplateDefinitionRegistry([
                // PHP Frameworks (ordered by priority)
                new LaravelTemplateDefinition(),
                new SpiralTemplateDefinition(),
                new SymfonyTemplateDefinition(),
                new Yii3TemplateDefinition(),
                new Yii2TemplateDefinition(),
                new GenericPhpTemplateDefinition(),

                // JavaScript Frameworks (ordered by priority)
                new NextJsTemplateDefinition(),
                new NuxtTemplateDefinition(),
                new ReactTemplateDefinition(),
                new VueTemplateDefinition(),
                new ExpressTemplateDefinition(),
            ]),
            ProjectAnalysisService::class => static fn(
                FilesInterface $files,
                ComposerFileReader $composerReader,
                ProjectStructureDetector $structureDetector,
            ): ProjectAnalysisService => new ProjectAnalysisService([
                // Register analyzers in priority order (highest first)
                new LaravelAnalyzer($composerReader, $structureDetector),
                new PackageJsonAnalyzer($files, $structureDetector),
                new ComposerAnalyzer($composerReader, $structureDetector),
                new FallbackAnalyzer($structureDetector), // Always register fallback analyzer last
            ]),
        ];
    }

    public function boot(
        TemplateRegistry $templateRegistry,
        ConsoleBootloader $console,
        BuiltinTemplateProvider $builtinTemplateProvider,
    ): void {
        // Register built-in template provider
        $templateRegistry->registerProvider($builtinTemplateProvider);

        // Register console commands
        $console->addCommand(ListCommand::class);
    }
}
