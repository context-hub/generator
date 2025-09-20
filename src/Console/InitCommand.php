<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Config\ConfigType;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Template\Analysis\ProjectAnalysisService;
use Butschster\ContextGenerator\Template\Registry\TemplateRegistry;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\Option;
use Spiral\Files\FilesInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'init',
    description: 'Initialize a new context configuration file with smart project analysis',
)]
final class InitCommand extends BaseCommand
{
    #[Argument(
        name: 'template',
        description: 'Specific template to use (optional)',
    )]
    protected ?string $template = null;

    #[Option(
        name: 'config-file',
        shortcut: 'c',
        description: 'The name of the file to create',
    )]
    protected string $configFilename = 'context.yaml';

    public function __invoke(
        DirectoriesInterface $dirs,
        FilesInterface $files,
        TemplateRegistry $templateRegistry,
        ProjectAnalysisService $analysisService,
    ): int {
        $filename = $this->configFilename;
        $ext = \pathinfo($filename, \PATHINFO_EXTENSION);

        try {
            $type = ConfigType::fromExtension($ext);
        } catch (\ValueError) {
            $this->output->error(\sprintf('Unsupported config type: %s', $ext));
            return Command::FAILURE;
        }

        $filename = \pathinfo(\strtolower($filename), PATHINFO_FILENAME) . '.' . $type->value;
        $filePath = (string) $dirs->getRootPath()->join($filename);

        if ($files->exists($filePath)) {
            $this->output->error(\sprintf('Config %s already exists', $filePath));
            return Command::FAILURE;
        }

        if ($this->template !== null) {
            return $this->initWithTemplate($files, $templateRegistry, $this->template, $type, $filePath);
        }

        return $this->initWithAnalysis($dirs, $files, $analysisService, $templateRegistry, $type, $filePath);
    }

    private function initWithTemplate(
        FilesInterface $files,
        TemplateRegistry $templateRegistry,
        string $templateName,
        ConfigType $type,
        string $filePath,
    ): int {
        $template = $templateRegistry->getTemplate($templateName);

        if ($template === null) {
            $this->output->error(\sprintf('Template "%s" not found', $templateName));

            $this->output->note('Available templates:');
            foreach ($templateRegistry->getAllTemplates() as $availableTemplate) {
                $this->output->writeln(\sprintf('  - %s: %s', $availableTemplate->name, $availableTemplate->description));
            }

            $this->output->writeln('');
            $this->output->writeln('Use <info>ctx template:list</info> to see all available templates with details.');

            return Command::FAILURE;
        }

        $this->output->success(\sprintf('Using template: %s', $template->description));

        return $this->writeConfig($files, $template->config, $type, $filePath);
    }

    private function initWithAnalysis(
        DirectoriesInterface $dirs,
        FilesInterface $files,
        ProjectAnalysisService $analysisService,
        TemplateRegistry $templateRegistry,
        ConfigType $type,
        string $filePath,
    ): int {
        $this->output->writeln('Analyzing project...');

        $results = $analysisService->analyzeProject($dirs->getRootPath());
        $bestMatch = $results[0];

        // Check if this is a fallback result
        $isFallback = $bestMatch->metadata['isFallback'] ?? false;

        if ($isFallback) {
            $this->output->warning('No specific project type detected. Using default configuration.');
        } else {
            $this->output->success(\sprintf(
                'Detected: %s (confidence: %.0f%%)',
                $bestMatch->detectedType,
                $bestMatch->confidence * 100,
            ));
        }

        if ($bestMatch->hasHighConfidence()) {
            $primaryTemplate = $bestMatch->getPrimaryTemplate();
            if ($primaryTemplate !== null) {
                $template = $templateRegistry->getTemplate($primaryTemplate);
                if ($template !== null) {
                    $this->output->writeln(\sprintf('Using template: %s', $template->description));
                    return $this->writeConfig($files, $template->config, $type, $filePath);
                }
            }
        }

        // Show analysis results for lower confidence matches
        return $this->showAnalysisResults($results, $templateRegistry, $files, $type, $filePath);
    }

    private function showAnalysisResults(
        array $results,
        TemplateRegistry $templateRegistry,
        FilesInterface $files,
        ConfigType $type,
        string $filePath,
    ): int {
        $this->output->title('Analysis Results');

        foreach ($results as $result) {
            // Skip showing fallback results in detailed analysis
            if ($result->metadata['isFallback'] ?? false) {
                continue;
            }

            $this->output->section(\sprintf(
                '%s: %s (%.0f%% confidence)',
                \ucfirst((string) $result->analyzerName),
                $result->detectedType,
                $result->confidence * 100,
            ));

            if (!empty($result->suggestedTemplates)) {
                $this->output->writeln('Suggested templates:');
                foreach ($result->suggestedTemplates as $templateName) {
                    $template = $templateRegistry->getTemplate($templateName);
                    if ($template !== null) {
                        $this->output->writeln(\sprintf('  - %s: %s', $templateName, $template->description));
                    }
                }
            }
        }

        // Use the best match (could be fallback if no other matches)
        $bestResult = $results[0];
        $primaryTemplate = $bestResult->getPrimaryTemplate();

        if ($primaryTemplate !== null) {
            $template = $templateRegistry->getTemplate($primaryTemplate);
            if ($template !== null) {
                $this->output->note(\sprintf('Using template: %s', $template->description));
                return $this->writeConfig($files, $template->config, $type, $filePath);
            }
        }

        // This should never happen, but provide safety fallback
        $this->output->error('No suitable template found');
        return Command::FAILURE;
    }

    private function writeConfig(
        FilesInterface $files,
        ConfigRegistry $config,
        ConfigType $type,
        string $filePath,
    ): int {
        try {
            $content = match ($type) {
                ConfigType::Json => \json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ConfigType::Yaml => Yaml::dump(
                    \json_decode(\json_encode($config), true),
                    10,
                    2,
                    Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
                ),
                default => throw new \InvalidArgumentException(
                    \sprintf('Unsupported config type: %s', $type->value),
                ),
            };
        } catch (\Throwable $e) {
            $this->output->error(\sprintf('Failed to create config: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $files->ensureDirectory(\dirname($filePath));
        $files->write($filePath, $content);

        $this->output->success(\sprintf('Config %s created', $filePath));

        return Command::SUCCESS;
    }
}
