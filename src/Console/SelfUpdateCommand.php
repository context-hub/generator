<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\Application;
use Butschster\ContextGenerator\Lib\BinaryUpdater\BinaryUpdater;
use Butschster\ContextGenerator\Lib\BinaryUpdater\UpdaterFactory;
use Butschster\ContextGenerator\Lib\GithubClient\GithubClientInterface;
use Butschster\ContextGenerator\Lib\GithubClient\Model\GithubRepository;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Console\Attribute\Option;
use Spiral\Files\FilesInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'self-update',
    description: 'Update the Context Generator to the latest version',
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
    ) {
        parent::__construct();
    }

    public function __invoke(Application $app, EnvironmentInterface $env): int
    {
        $this->output->title('Context Generator Self Update');

        $storeLocation = \trim($this->storeLocation ?: $env->get('CTX_BINARY_PATH', '/usr/local/bin'));
        $type = \trim($this->type ?: ($app->isBinary ? 'bin' : 'phar'));

        $fileName = match ($type) {
            'phar' => \sprintf('%s.phar', $this->binaryName),
            'bin' => $this->binaryName,
            default => throw new \InvalidArgumentException('Invalid type provided'),
        };

        // Check if we have a valid store location
        if (empty($storeLocation)) {
            $this->output->error(
                'Self-update is only available when running the PHAR version of Context Generator.',
            );
            return Command::FAILURE;
        }

        // Full path to the binary
        $binaryPath = \rtrim($storeLocation, '/') . '/' . $fileName;

        $this->output->title($app->name);
        $this->output->text('Current version: ' . $app->version);
        $this->output->section('Checking for updates...');

        $manager = $this->githubClient->getReleaseManager(new GithubRepository($this->repository));

        try {
            // Fetch the latest release
            $release = $manager->getLatestRelease();

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

            // Get the asset URL
            $assetUrl = $release->getAssetUrl($fileName);

            if ($assetUrl === null) {
                $this->output->error("Could not find asset '$fileName' in the release.");
                return Command::FAILURE;
            }

            // Start the update process
            $this->output->section('Downloading the latest version...');

            // Create a temporary file
            $tempFile = $this->files->tempFilename();
            $this->output->text("Downloading to temporary file: $tempFile");

            // Download the asset
            $manager->downloadAsset($assetUrl, $tempFile);

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
