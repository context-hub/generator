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
        $this->output->title('CTX Self Update');
        $storeLocation = \trim($this->storeLocation ?: $env->get('CTX_BINARY_PATH', (string) $dirs->getRootPath()));
        $type = \trim($this->type ?: ($app->isBinary ? 'bin' : 'phar'));


        // Check if we have a valid store location
        if (empty($storeLocation)) {
            $this->output->error(
                'Self-update is only available for the binary version of CTX.',
            );
            return Command::FAILURE;
        }

        $binaryPath = (string) $dirs->getRootPath()->join($this->binaryName);

        $this->output->title($app->name);
        $this->output->text('Current version: ' . $app->version);
        $this->output->text('Binary will be stored at: ' . $binaryPath);

        $this->output->section('Checking for updates...');

        // Create repository and get standard release manager
        $repository = new GithubRepository($this->repository);
        $baseReleaseManager = $this->githubClient->getReleaseManager($repository);

        try {
            // Fetch the latest release
            $release = $baseReleaseManager->getLatestRelease();

            // Check if an update is available
            if (!$release->isNewerThan($app->version)) {
                $this->output->success("You're already using the latest version ({$app->version})");
                return Command::SUCCESS;
            }

            $this->output->success("A new version is available: {$release->getVersion()}");

            // Confirm the update
            if (!$this->output->confirm('Do you want to update now?', true)) {
                return Command::SUCCESS;
            }

            // Start the update process
            $this->output->section('Downloading the latest version...');

            // Create a temporary file
            $tempFile = $this->files->tempFilename();
            $this->output->text("Downloading to temporary file: $tempFile");

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
                $this->output->text("Attempting to download platform-specific binary...");
                $downloadSuccess = $releaseManager->downloadBinary(
                    $release->getVersion(),
                    $this->binaryName,
                    $type,
                    $tempFile,
                );

                if (!$downloadSuccess) {
                    throw new \RuntimeException("Failed to download binary");
                }
            } catch (\Throwable $e) {
                $this->output->error("Failed to download platform-specific binary: {$e->getMessage()}");
                return Command::FAILURE;
            }

            $this->output->section('Installing the update...');

            // Use our BinaryUpdater to handle the update safely
            $updaterFactory = new UpdaterFactory($this->files, $this->logger);
            $binaryUpdater = new BinaryUpdater($this->files, $updaterFactory->createStrategy(), $this->logger);

            if ($binaryUpdater->update($tempFile, $binaryPath)) {
                $this->output->success("Update process started successfully for version {$release->getVersion()}");

                // Add a note about how the update works
                if ($app->isBinary) {
                    $this->output->note(
                        "The update will complete automatically after this process exits. " .
                        "The next time you run the command, you'll be using the new version.",
                    );
                }
            } else {
                $this->output->error("Failed to start the update process.");
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->output->error("Failed to update: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
