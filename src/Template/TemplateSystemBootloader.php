<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template;

use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\Template\Analysis\Analyzer\ComposerAnalyzer;
use Butschster\ContextGenerator\Template\Analysis\Analyzer\FallbackAnalyzer;
use Butschster\ContextGenerator\Template\Analysis\Analyzer\GoAnalyzer;
use Butschster\ContextGenerator\Template\Analysis\Analyzer\PackageJsonAnalyzer;
use Butschster\ContextGenerator\Template\Analysis\Analyzer\PythonAnalyzer;
use Butschster\ContextGenerator\Template\Analysis\AnalyzerChain;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalysisService;
use Butschster\ContextGenerator\Template\Analysis\Util\ComposerFileReader;
use Butschster\ContextGenerator\Template\Analysis\Util\ProjectStructureDetector;
use Butschster\ContextGenerator\Template\Console\InitCommand;
use Butschster\ContextGenerator\Template\Console\ListCommand;
use Butschster\ContextGenerator\Template\Definition\DjangoTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\ExpressTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\FastApiTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\FlaskTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\GenericPhpTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\GinTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\GoTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\LaravelTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\NextJsTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\NuxtTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\PythonTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\ReactTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\SpiralTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\SymfonyTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\TemplateDefinitionRegistry;
use Butschster\ContextGenerator\Template\Definition\VueTemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\Yii2TemplateDefinition;
use Butschster\ContextGenerator\Template\Definition\Yii3TemplateDefinition;
use Butschster\ContextGenerator\Template\Detection\Strategy\AnalyzerBasedDetectionStrategy;
use Butschster\ContextGenerator\Template\Detection\Strategy\CompositeDetectionStrategy;
use Butschster\ContextGenerator\Template\Detection\Strategy\TemplateBasedDetectionStrategy;
use Butschster\ContextGenerator\Template\Provider\BuiltinTemplateProvider;
use Butschster\ContextGenerator\Template\Registry\TemplateRegistry;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;
use Spiral\Files\FilesInterface;

/**
 * Enhanced template system bootloader with Python and Go support
 */
#[Singleton]
final class TemplateSystemBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            // Core registries
            TemplateRegistry::class => TemplateRegistry::class,
            TemplateDefinitionRegistry::class => static fn(): TemplateDefinitionRegistry => new TemplateDefinitionRegistry([
                // PHP Frameworks (ordered by priority)
                new LaravelTemplateDefinition(),
                new SpiralTemplateDefinition(),
                new SymfonyTemplateDefinition(),
                new Yii3TemplateDefinition(),
                new Yii2TemplateDefinition(),
                new GenericPhpTemplateDefinition(),

                // Python Frameworks (ordered by priority)
                new DjangoTemplateDefinition(),
                new FastApiTemplateDefinition(),
                new FlaskTemplateDefinition(),
                new PythonTemplateDefinition(),

                // Go Frameworks (ordered by priority)
                new GinTemplateDefinition(),
                new GoTemplateDefinition(),

                // JavaScript Frameworks (ordered by priority)
                new NextJsTemplateDefinition(),
                new NuxtTemplateDefinition(),
                new ReactTemplateDefinition(),
                new VueTemplateDefinition(),
                new ExpressTemplateDefinition(),
            ]),

            // Enhanced analysis system with Python and Go analyzers
            AnalyzerChain::class => static fn(
                FilesInterface $files,
                ComposerFileReader $composerReader,
                ProjectStructureDetector $structureDetector,
            ): AnalyzerChain => new AnalyzerChain([
                // Register analyzers in priority order (highest first)
                new PackageJsonAnalyzer($files, $structureDetector),     // 80
                new GoAnalyzer($files, $structureDetector),              // 80
                new PythonAnalyzer($files, $structureDetector),          // 75
                new ComposerAnalyzer($composerReader, $structureDetector), // 50
                new FallbackAnalyzer($structureDetector),                // 1 - Always register fallback analyzer last
            ]),

            ProjectAnalysisService::class => static fn(
                AnalyzerChain $analyzerChain,
            ): ProjectAnalysisService => new ProjectAnalysisService($analyzerChain->getAllAnalyzers()),

            CompositeDetectionStrategy::class => static fn(
                TemplateBasedDetectionStrategy $templateStrategy,
                AnalyzerBasedDetectionStrategy $analyzerStrategy,
            ): CompositeDetectionStrategy => new CompositeDetectionStrategy([
                $templateStrategy,
                $analyzerStrategy,
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
        $console->addCommand(InitCommand::class);
        $console->addCommand(ListCommand::class);
    }
}
