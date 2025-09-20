<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template;

use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\Template\Analysis\Analyzer\ComposerAnalyzer;
use Butschster\ContextGenerator\Template\Analysis\Analyzer\FallbackAnalyzer;
use Butschster\ContextGenerator\Template\Analysis\Analyzer\LaravelAnalyzer;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalysisService;
use Butschster\ContextGenerator\Template\Analysis\Util\ComposerFileReader;
use Butschster\ContextGenerator\Template\Analysis\Util\ProjectStructureDetector;
use Butschster\ContextGenerator\Template\Console\ListCommand;
use Butschster\ContextGenerator\Template\Definition\TemplateDefinitionRegistry;
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
            TemplateDefinitionRegistry::class => static fn(): TemplateDefinitionRegistry =>
                TemplateFactory::getDefinitionRegistry(),
            ComposerFileReader::class => ComposerFileReader::class,
            ProjectStructureDetector::class => static fn(): ProjectStructureDetector =>
                new ProjectStructureDetector(),
            ProjectAnalysisService::class => static fn(
                FilesInterface $files,
                ComposerFileReader $composerReader,
                ProjectStructureDetector $structureDetector,
            ): ProjectAnalysisService => new ProjectAnalysisService([
                // Register analyzers in priority order (highest first)
                new LaravelAnalyzer($files, $composerReader, $structureDetector),
                new ComposerAnalyzer($files, $composerReader, $structureDetector),
                new FallbackAnalyzer($structureDetector), // Always register fallback analyzer last
            ]),
        ];
    }

    public function boot(
        TemplateRegistry $templateRegistry,
        ConsoleBootloader $console,
    ): void {
        // Register built-in template provider
        $templateRegistry->registerProvider(new BuiltinTemplateProvider());

        // Register console commands
        $console->addCommand(ListCommand::class);
    }
}
