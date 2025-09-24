<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Template\Console;

use Butschster\ContextGenerator\Application\JsonSchema;
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
            return $this->initWithSpecificTemplate($files, $templateRegistry, $this->template, $type, $filePath);
        }

        return $this->initWithSmartDetection($dirs, $files, $detectionService, $type, $filePath);
    }

    private function initWithSpecificTemplate(
        FilesInterface $files,
        TemplateRegistry $templateRegistry,
        string $templateName,
        ConfigType $type,
        string $filePath,
    ): int {
        $template = $templateRegistry->getTemplate($templateName);

        if ($template === null) {
            $this->output->error(\sprintf('Template "%s" not found', $templateName));
            $this->showAvailableTemplates($templateRegistry);
            return Command::FAILURE;
        }

        $this->output->success(\sprintf('Using template: %s', $template->description));
        return $this->writeConfig($files, $template->config, $type, $filePath);
    }

    private function initWithSmartDetection(
        DirectoriesInterface $dirs,
        FilesInterface $files,
        TemplateDetectionService $detectionService,
        ConfigType $type,
        string $filePath,
    ): int {
        if ($this->output->isVerbose()) {
            $this->output->writeln('Analyzing project structure...');
            $this->showDetectionStrategies($detectionService);
        }

        if ($this->showAll) {
            return $this->showAllPossibleTemplates($dirs, $files, $detectionService, $type, $filePath);
        }

        $detection = $detectionService->detectBestTemplate($dirs->getRootPath());

        if (!$detection->hasTemplate()) {
            $this->output->warning('No specific project type detected.');
            $this->showDetectionFallbackOptions($detectionService);
            return Command::FAILURE;
        }

        $this->displayDetectionResult($detection, $detectionService);
        return $this->writeConfig($files, $detection->template->config, $type, $filePath);
    }

    private function showDetectionStrategies(TemplateDetectionService $detectionService): void
    {
        $strategies = $detectionService->getStrategies();

        $this->output->writeln('Available detection strategies:');
        foreach ($strategies as $strategy) {
            $this->output->writeln(\sprintf(
                '  - %s (priority: %d, threshold: %.0f%%)',
                $strategy->getName(),
                $strategy->getPriority(),
                $strategy->getConfidenceThreshold() * 100.0,
            ));
        }
        $this->output->newLine();
    }

    private function displayDetectionResult(
        $detection,
        TemplateDetectionService $detectionService,
    ): void {
        $confidencePercent = $detection->confidence * 100.0;

        if ($detection->isHighConfidenceTemplateDetection()) {
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
        }

        // Show additional context in verbose mode
        if ($this->output->isVerbose()) {
            if (isset($detection->metadata['reason'])) {
                $this->output->writeln(\sprintf('  Reason: %s', $detection->metadata['reason']));
            }

            // Show strategy used
            if (isset($detection->metadata['selectedStrategy'])) {
                $this->output->writeln(\sprintf('  Strategy: %s', $detection->metadata['selectedStrategy']));
            }

            // Show template match details if available
            if ($detection->detectionMethod === 'template_criteria' && isset($detection->metadata['matchingCriteria'])) {
                $criteria = $detection->metadata['matchingCriteria'];
                if (!empty($criteria)) {
                    $this->output->writeln('  Matched criteria:');
                    foreach ($criteria as $type => $matches) {
                        if (!empty($matches)) {
                            $this->output->writeln(\sprintf('    - %s: %s', $type, \implode(', ', $matches)));
                        }
                    }
                }
            }
        }
    }

    private function showAllPossibleTemplates(
        DirectoriesInterface $dirs,
        FilesInterface $files,
        TemplateDetectionService $detectionService,
        ConfigType $type,
        string $filePath,
    ): int {
        $this->output->writeln('Analyzing all possible templates...');

        $bestDetection = $detectionService->detectBestTemplate($dirs->getRootPath());
        $allDetections = $detectionService->getAllPossibleTemplates($dirs->getRootPath());

        if (empty($allDetections)) {
            $this->output->warning('No templates detected for this project.');
            return Command::FAILURE;
        }

        $this->output->title('All Possible Templates');

        $tableData = [];
        foreach ($allDetections as $detection) {
            $confidencePercent = $detection->confidence * 100.0;

            $isSelected = $bestDetection->hasTemplate() &&
                         $detection->template !== null &&
                         $detection->template->name === $bestDetection->template->name;

            $status = $this->getTemplateStatus($detection, $isSelected, $detectionService);

            $strategyInfo = $detection->getDetectionMethodDescription();
            if (isset($detection->metadata['strategy'])) {
                $strategyInfo = $detection->metadata['strategy'];
            }

            $tableData[] = [
                $detection->template->name ?? 'Unknown',
                $detection->template->description ?? 'Unknown',
                \sprintf('%.0f%%', $confidencePercent),
                $strategyInfo,
                $status,
            ];
        }

        $this->output->table(['Template', 'Description', 'Confidence', 'Strategy', 'Status'], $tableData);

        $this->output->note(\sprintf(
            'Template detection uses %.0f%% confidence threshold. Strategies are tried in priority order.',
            $detectionService->getHighConfidenceThreshold() * 100.0,
        ));

        if ($bestDetection->hasTemplate()) {
            $this->displayDetectionResult($bestDetection, $detectionService);
            return $this->writeConfig($files, $bestDetection->template->config, $type, $filePath);
        }

        $this->output->error('No suitable template found');
        return Command::FAILURE;
    }

    private function getTemplateStatus($detection, bool $isSelected, TemplateDetectionService $detectionService): string
    {
        if ($isSelected) {
            return match ($detection->detectionMethod) {
                'template_criteria' => 'Selected (Template)',
                'analyzer' => 'Selected (Analyzer)',
                default => 'Selected',
            };
        }

        if ($detection->detectionMethod === 'template_criteria') {
            $meetsThreshold = $detection->confidence > $detectionService->getHighConfidenceThreshold();
            return $meetsThreshold ? 'High confidence but not best' : 'Low confidence';
        }

        return 'Available';
    }

    private function showAvailableTemplates(TemplateRegistry $templateRegistry): void
    {
        $this->output->note('Available templates:');
        foreach ($templateRegistry->getAllTemplates() as $template) {
            $this->output->writeln(\sprintf('  - %s: %s', $template->name, $template->description));
        }
        $this->output->newLine();
        $this->output->writeln('Use <info>ctx template:list</info> to see detailed template information.');
    }

    private function showDetectionFallbackOptions(TemplateDetectionService $detectionService): void
    {
        $this->output->writeln('Options:');
        $this->output->writeln('  - Use <info>ctx init <template-name></info> to specify a template manually');
        $this->output->writeln('  - Use <info>ctx template:list</info> to see available templates');
        $this->output->writeln('  - Use <info>ctx init --show-all</info> to see all detection results');

        if ($this->output->isVerbose()) {
            $this->output->newLine();
            $this->output->writeln('Detection strategies in use:');
            foreach ($detectionService->getStrategies() as $strategy) {
                $this->output->writeln(\sprintf(
                    '  - %s (threshold: %.0f%%)',
                    \ucfirst(\str_replace('-', ' ', $strategy->getName())),
                    $strategy->getConfidenceThreshold() * 100.0,
                ));
            }
        }
    }

    private function validateDirectoryForWriting(FilesInterface $files, string $filePath): bool
    {
        $directory = \dirname($filePath);

        if (!$files->exists($directory)) {
            $this->output->error(\sprintf('Directory does not exist: %s', $directory));
            $this->output->note('Please create the directory or use an existing one.');
            return false;
        }

        // Check if directory is writable by checking permissions
        try {
            $permissions = $files->getPermissions($directory);
            // Check if the directory has write permissions for owner, group, or others
            $isWritable = ($permissions & 0o200) || ($permissions & 0o020) || ($permissions & 0o002);
            if (!$isWritable) {
                $this->output->error(\sprintf('Directory is not writable: %s', $directory));
                $this->output->note('Please check directory permissions or run with appropriate privileges.');
                return false;
            }
        } catch (\Throwable) {
            // If we can't check permissions, assume it's not writable
            $this->output->error(\sprintf('Cannot check directory permissions: %s', $directory));
            $this->output->note('Please ensure the directory is writable or run with appropriate privileges.');
            return false;
        }

        return true;
    }

    private function writeConfig(
        FilesInterface $files,
        ConfigRegistry $config,
        ConfigType $type,
        string $filePath,
    ): int {
        // Validate directory exists and is writable
        if (!$this->validateDirectoryForWriting($files, $filePath)) {
            return Command::FAILURE;
        }

        try {
            // Create a new config registry with schema for output
            $outputConfig = new ConfigRegistry(JsonSchema::SCHEMA_URL);

            // Copy all registries from the original config
            foreach ($config->all() as $registry) {
                $outputConfig->register($registry);
            }

            $content = match ($type) {
                ConfigType::Json => \json_encode($outputConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ConfigType::Yaml => Yaml::dump(
                    \json_decode(\json_encode($outputConfig), true),
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

        $this->output->success(\sprintf('Configuration created: %s', $filePath));

        if ($this->output->isVerbose()) {
            $this->output->writeln('Next steps:');
            $this->output->writeln('  - Review and customize the generated configuration');
            $this->output->writeln('  - Run <info>ctx generate</info> to create context documents');
            $this->output->writeln('  - Use <info>ctx server</info> to start MCP server for Claude integration');
        }

        return Command::SUCCESS;
    }
}
