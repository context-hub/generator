<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Console;

use Butschster\ContextGenerator\Config\ConfigType;
use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;
use Butschster\ContextGenerator\Console\BaseCommand;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Template\Detection\TemplateDetectionService;
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

    #[Option(
        name: 'show-all',
        shortcut: 'a',
        description: 'Show all possible templates with confidence scores',
    )]
    protected bool $showAll = false;

    public function __invoke(
        DirectoriesInterface $dirs,
        FilesInterface $files,
        TemplateRegistry $templateRegistry,
        TemplateDetectionService $detectionService,
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

        return $this->initWithDetection($dirs, $files, $detectionService, $type, $filePath);
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

    private function initWithDetection(
        DirectoriesInterface $dirs,
        FilesInterface $files,
        TemplateDetectionService $detectionService,
        ConfigType $type,
        string $filePath,
    ): int {
        if ($this->output->isVerbose()) {
            $this->output->writeln('Analyzing project...');
        }

        if ($this->showAll) {
            return $this->showAllPossibleTemplates($dirs, $files, $detectionService, $type, $filePath);
        }

        $detection = $detectionService->detectBestTemplate($dirs->getRootPath());

        if (!$detection->hasTemplate()) {
            $this->output->warning('No specific project type detected. Please specify a template manually.');
            $this->output->writeln('Use <info>ctx template:list</info> to see available templates.');
            $this->output->writeln('Use <info>ctx init <template-name></info> to use a specific template.');
            return Command::FAILURE;
        }

        // Show detection details only in verbose mode
        if ($this->output->isVerbose()) {
            $this->displayDetectionResult($detection, $detectionService);
        } else {
            // In non-verbose mode, just show what template is being used
            $this->output->success(\sprintf('Using template: %s', $detection->template->description));
        }

        return $this->writeConfig($files, $detection->template->config, $type, $filePath);
    }

    private function displayDetectionResult(
        $detection,
        TemplateDetectionService $detectionService,
    ): void {
        $confidencePercent = $detection->confidence * 100;
        $threshold = $detectionService->getHighConfidenceThreshold() * 100;

        if ($detection->detectionMethod === 'template_criteria') {
            $this->output->success(\sprintf(
                'High-confidence template match: %s (%.0f%% confidence)',
                $detection->template->description,
                $confidencePercent,
            ));
        } else {
            $this->output->writeln(\sprintf(
                'Detected via analysis: %s (%.0f%% confidence, method: %s)',
                $detection->template->description,
                $confidencePercent,
                $detection->getDetectionMethodDescription(),
            ));

            // Show why template detection wasn't used
            if (isset($detection->metadata['templateMatchesConsidered']) && $detection->metadata['templateMatchesConsidered'] > 0) {
                $templateConfidence = $detection->metadata['templateMatchesConsidered'] * 100;
                $this->output->writeln(\sprintf(
                    '<comment>Template match found but below %.0f%% threshold (%.0f%%), using analyzer instead</comment>',
                    $threshold,
                    $templateConfidence,
                ));
            }
        }

        $this->output->writeln(\sprintf('Using template: %s', $detection->template->description));
    }

    private function showAllPossibleTemplates(
        DirectoriesInterface $dirs,
        FilesInterface $files,
        TemplateDetectionService $detectionService,
        ConfigType $type,
        string $filePath,
    ): int {
        // Always show analysis message when showing all templates
        $this->output->writeln('Analyzing project...');

        // Get the actual best detection (this uses the 90% threshold logic)
        $bestDetection = $detectionService->detectBestTemplate($dirs->getRootPath());

        // Get all possible templates for display
        $allDetections = $detectionService->getAllPossibleTemplates($dirs->getRootPath());

        if (empty($allDetections)) {
            $this->output->warning('No templates detected for this project.');
            return Command::FAILURE;
        }

        $this->output->title('All Possible Templates');
        $threshold = $detectionService->getHighConfidenceThreshold() * 100;

        $tableData = [];
        foreach ($allDetections as $detection) {
            $confidencePercent = $detection->confidence * 100;

            // Determine status based on whether this is the actual best detection
            $isSelected = $bestDetection->hasTemplate() &&
                         $detection->template !== null &&
                         $detection->template->name === $bestDetection->template->name;

            $status = match (true) {
                $isSelected && $detection->detectionMethod === 'template_criteria' => '✓ Selected (Template)',
                $isSelected && $detection->detectionMethod === 'analyzer' => '✓ Selected (Analyzer)',
                $detection->detectionMethod === 'template_criteria' && $detection->confidence > $detectionService->getHighConfidenceThreshold() => '✗ High confidence but not best',
                $detection->detectionMethod === 'template_criteria' => '✗ Low confidence',
                default => 'Available',
            };

            $tableData[] = [
                $detection->template->name ?? 'Unknown',
                $detection->template->description ?? 'Unknown',
                \sprintf('%.0f%%', $confidencePercent),
                $detection->getDetectionMethodDescription(),
                $status,
            ];
        }

        $this->output->table(['Template', 'Description', 'Confidence', 'Method', 'Status'], $tableData);

        $this->output->note(\sprintf(
            'Templates with >%.0f%% confidence from template criteria are preferred. Otherwise, analyzer detection is used.',
            $threshold,
        ));

        // Use the actual best detection (which respects the 90% threshold)
        if (!$bestDetection->hasTemplate()) {
            $this->output->error('No suitable template found');
            return Command::FAILURE;
        }

        // Always show details when using --show-all
        $this->displayDetectionResult($bestDetection, $detectionService);

        return $this->writeConfig($files, $bestDetection->template->config, $type, $filePath);
    }

    private function showDetectionOptions(
        DirectoriesInterface $dirs,
        FilesInterface $files,
        TemplateDetectionService $detectionService,
        $detection,
        ConfigType $type,
        string $filePath,
    ): int {
        $this->output->section('Detection Results');

        $this->output->writeln(\sprintf(
            'Best match: %s (%.0f%% confidence)',
            $detection->template->description,
            $detection->confidence * 100,
        ));

        // Show other possible templates
        $allDetections = $detectionService->getAllPossibleTemplates($dirs->getRootPath());
        if (\count($allDetections) > 1) {
            $this->output->writeln('Other possible templates:');
            foreach (\array_slice($allDetections, 1, 3) as $altDetection) {
                $this->output->writeln(\sprintf(
                    '  - %s (%.0f%% confidence, %s)',
                    $altDetection->template->description,
                    $altDetection->confidence * 100,
                    \strtolower($altDetection->getDetectionMethodDescription()),
                ));
            }
        }

        $this->output->note(\sprintf('Using best match: %s', $detection->template->description));
        return $this->writeConfig($files, $detection->template->config, $type, $filePath);
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
