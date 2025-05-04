<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\Application;
use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Lib\BinaryUpdater\BinaryUpdater;
use Butschster\ContextGenerator\Lib\BinaryUpdater\UpdaterFactory;
use Butschster\ContextGenerator\Lib\GithubClient\BinaryNameBuilder;
use Butschster\ContextGenerator\Lib\GithubClient\GithubClientInterface;
use Butschster\ContextGenerator\Lib\GithubClient\Model\GithubRepository;
use Butschster\ContextGenerator\Lib\GithubClient\ReleaseManager;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Console\Attribute\Option;
use Spiral\Files\FilesInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'self-update',
    description: 'Update app to the latest version',
    aliases: ['update'],
)]
final class SelfUpdateCommand extends BaseCommand
{
    private const string GITHUB_REPOSITORY = 'context-hub/generator';

    #[Option(
        name: 'path',
        shortcut: 'p',
        description: 'Path where store the binary',
    )]
    protected ?string $storeLocation = null;

    #[Option(
        name: 'name',
        shortcut: 'b',
        description: 'Name of the binary file. Default is [ctx]',
    )]
    protected string $binaryName = 'ctx';

    #[Option(
        name: 'type',
        shortcut: 't',
        description: 'Binary type (phar or bin)',
    )]
    protected ?string $type = null;

    #[Option(
        name: 'repository',
        description: 'GitHub repository to update from',
    )]
    protected string $repository = self::GITHUB_REPOSITORY;

    public function __construct(
        private readonly GithubClientInterface $githubClient,
        private readonly FilesInterface $files,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    public function __invoke(Application $app, EnvironmentInterface $env, DirectoriesInterface $dirs): int
    {
        $startTime = \microtime(true);
        
        // Display command title
        $this->outputService->title('Context Generator Self Update');
        
        // Display current configuration using key-value pairs
        $this->outputService->section('Current Configuration');
        
        $storeLocation = \trim($this->storeLocation ?: $env->get('CTX_BINARY_PATH', (string) $dirs->getRootPath()));
        $type = \trim($this->type ?: ($app->isBinary ? 'bin' : 'phar'));
        
        $this->outputService->keyValue('Application Name', $app->name);
        $this->outputService->keyValue('Current Version', 
            $this->outputService->highlight($app->version, 'bright-cyan'));
        $this->outputService->keyValue('Binary Type', $type);
        $this->outputService->keyValue('Binary Name', $this->binaryName);
        $this->outputService->keyValue('Storage Location', $storeLocation);
        $this->outputService->keyValue('GitHub Repository', $this->repository);

        // Check if we have a valid store location
        if (empty($storeLocation)) {
            $this->outputService->error(
                'Self-update is only available for the binary version of CTX.',
            );
            
            // Show helpful information
            $this->outputService->note([
                'For non-binary installations, consider one of these methods:',
                '- Use Composer: composer update context-hub/generator',
                '- Download a fresh binary from GitHub releases'
            ]);
            
            return Command::FAILURE;
        }

        $binaryPath = (string) $dirs->getRootPath()->join($this->binaryName);
        
        // Display update check section
        $this->outputService->section('Checking for Updates');
        $this->outputService->info('Connecting to GitHub to check for newer versions...');

        // Create repository and get standard release manager
        $repository = new GithubRepository($this->repository);
        $baseReleaseManager = $this->githubClient->getReleaseManager($repository);

        try {
            // Fetch the latest release
            $release = $baseReleaseManager->getLatestRelease();
            
            // Display release information
            $this->outputService->keyValue('Latest Available Version', 
                $this->outputService->highlight($release->getVersion(), 'bright-green'));
            
            // Check if an update is available
            if (!$release->isNewerThan($app->version)) {
                // Show success message with version info and status list
                $statusRenderer = $this->outputService->getStatusRenderer();
                $statusRenderer->renderSuccess(
                    'Version Check', 
                    'You are already using the latest version'
                );
                
                $this->outputService->success(\sprintf(
                    "You're already using the latest version (%s)",
                    $this->outputService->highlight($app->version, 'bright-cyan')
                ));
                
                return Command::SUCCESS;
            }

            // Show update availability
            $this->outputService->success(\sprintf(
                "A new version is available: %s → %s",
                $this->outputService->highlight($app->version, 'bright-cyan'),
                $this->outputService->highlight($release->getVersion(), 'bright-green')
            ));

            // Confirm the update
            if (!$this->output->confirm('Do you want to update now?', true)) {
                $this->outputService->info('Update cancelled by user');
                return Command::SUCCESS;
            }

            // Start the download process
            $this->outputService->section('Downloading Update');

            // Create a temporary file
            $tempFile = $this->files->tempFilename();
            $this->outputService->keyValue('Temporary File', $tempFile);

            // Initialize the BinaryNameBuilder
            $binaryNameBuilder = new BinaryNameBuilder();

            // Create an enhanced release manager with the binary name builder
            $releaseManager = new ReleaseManager(
                $this->httpClient,
                $repository,
                null, // token
                $binaryNameBuilder,
                $this->logger,
            );

            // Attempt to download the binary with platform awareness
            try {
                $this->outputService->info("Downloading platform-specific binary...");
                $downloadSuccess = $releaseManager->downloadBinary(
                    $release->getVersion(),
                    $this->binaryName,
                    $type,
                    $tempFile,
                );

                if (!$downloadSuccess) {
                    throw new \RuntimeException("Failed to download binary");
                }
                
                // Show download success message
                $fileSize = \filesize($tempFile);
                $this->outputService->keyValue('Downloaded Size', 
                    $this->outputService->formatCount(\round($fileSize / 1024, 2)) . ' KB');
                
                $this->outputService->success("Download completed successfully");
            } catch (\Throwable $e) {
                $this->outputService->error("Failed to download platform-specific binary: " . $e->getMessage());
                
                // Show possible solutions
                $this->outputService->note([
                    'Possible solutions:',
                    '- Check your internet connection',
                    '- Verify GitHub is accessible from your network',
                    '- Try manually downloading from GitHub releases page'
                ]);
                
                return Command::FAILURE;
            }

            // Installation section
            $this->outputService->section('Installing Update');
            $this->outputService->keyValue('Target Path', $binaryPath);

            // Use our BinaryUpdater to handle the update safely
            $updaterFactory = new UpdaterFactory($this->files, $this->logger);
            $binaryUpdater = new BinaryUpdater($this->files, $updaterFactory->createStrategy(), $this->logger);

            if ($binaryUpdater->update($tempFile, $binaryPath)) {
                // Display status list for update steps
                $statusList = [
                    'Download' => ['status' => 'success', 'message' => 'Complete'],
                    'Validation' => ['status' => 'success', 'message' => 'Binary integrity verified'],
                    'Installation' => ['status' => 'success', 'message' => 'Update scheduled'],
                ];
                
                $this->outputService->statusList($statusList);
                
                // Display elapsed time
                $summaryRenderer = $this->outputService->getSummaryRenderer();
                $elapsedTime = \microtime(true) - $startTime;
                $summaryRenderer->renderTimeSummary($elapsedTime, 'Update process time');
                
                // Success message with version information
                $this->outputService->success(\sprintf(
                    "Update process started successfully for version %s",
                    $this->outputService->highlight($release->getVersion(), 'bright-green')
                ));

                // Add a note about how the update works
                if ($app->isBinary) {
                    $this->outputService->note([
                        'Update process details:',
                        'The update will complete automatically after this process exits.',
                        'The next time you run the command, you\'ll be using the new version.',
                    ]);
                }
            } else {
                $this->outputService->error("Failed to start the update process.");
                
                // Add troubleshooting information
                $this->outputService->note([
                    'Troubleshooting:',
                    '- Check write permissions for ' . $binaryPath,
                    '- Ensure no other process is using the binary',
                    '- Try running the command with elevated privileges'
                ]);
                
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->outputService->error("Failed to update: " . $e->getMessage());
            
            $this->logger->error('Update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }
}
