<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpException;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'self-update',
    description: 'Update the Context Generator to the latest version',
    aliases: ['update'],
)]
final class SelfUpdateCommand extends Command
{
    /**
     * GitHub API URL for latest release
     */
    private const GITHUB_API_LATEST_RELEASE = 'https://api.github.com/repos/context-hub/generator/releases/latest';

    /**
     * GitHub download URL format for the PHAR file
     */
    private const GITHUB_DOWNLOAD_URL = 'https://github.com/context-hub/generator/releases/download/%s/%s';

    private const PHAR_PATH = '/usr/local/bin/ctx';

    public function __construct(
        private readonly string $version,
        private readonly HttpClientInterface $httpClient,
        private readonly FilesInterface $files,
        private readonly string $binaryType = 'phar',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'phar-path',
                shortcut: 'p',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Path to the PHAR file to update',
                default: \getenv('CONTEXT_GENERATOR_PHAR_PATH') ?: self::PHAR_PATH,
            )
            ->addOption(
                name: 'type',
                shortcut: 't',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Binary type (phar or bin)',
                default: $this->binaryType,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        \assert($output instanceof SymfonyStyle);

        $output->title('Context Generator Self Update');

        $pharPath = \trim((string) $input->getOption('phar-path') ?: self::PHAR_PATH);
        $fileName = match ($input->getOption('type')) {
            'phar' => 'ctx.phar',
            'bin' => 'ctx',
            default => throw new \InvalidArgumentException('Invalid type provided'),
        };

        // Check if running as a PHAR
        if ($pharPath === '') {
            $output->error(
                'Self-update is only available when running the PHAR version of Context Generator.',
            );
            return Command::FAILURE;
        }

        $output->text('Current version: ' . $this->version);
        $output->section('Checking for updates...');

        try {
            // Fetch and compare versions
            $latestVersion = $this->fetchLatestVersion();
            $isUpdateAvailable = $this->isUpdateAvailable($this->version, $latestVersion);

            if (!$isUpdateAvailable) {
                $output->success("You're already using the latest version ({$this->version})");
                return Command::SUCCESS;
            }

            $output->success("A new version is available: {$latestVersion}");

            // Confirm the update
            if (!$output->confirm('Do you want to update now?', true)) {
                return Command::SUCCESS;
            }

            // Start the update process
            $output->section('Downloading the latest version...');
            $tempFile = $this->downloadLatestVersion($fileName, $latestVersion, $output);

            $output->section('Installing the update...');
            $this->installUpdate($tempFile, $pharPath, $output);

            $output->success("Successfully updated to version {$latestVersion}");

            return Command::SUCCESS;
        } catch (HttpException $e) {
            $output->error("HTTP error: {$e->getMessage()}");
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $output->error("Failed to update: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Fetch the latest version from GitHub
     */
    private function fetchLatestVersion(): string
    {
        $response = $this->httpClient->get(
            self::GITHUB_API_LATEST_RELEASE,
            [
                'User-Agent' => 'Context-Generator-Self-Update',
                'Accept' => 'application/vnd.github.v3+json',
            ],
        );

        if (!$response->isSuccess()) {
            throw new \RuntimeException(
                "Failed to fetch latest version. Server returned status code {$response->getStatusCode()}",
            );
        }

        try {
            // Use the new getJsonValue method with a default to handle missing tags
            $tagName = $response->getJsonValue('tag_name');

            if ($tagName === null) {
                throw new \RuntimeException("Invalid response format: 'tag_name' missing");
            }

            // Remove 'v' prefix if present
            return \ltrim((string) $tagName, 'v');
        } catch (HttpException $e) {
            throw new \RuntimeException("Failed to parse GitHub response: {$e->getMessage()}");
        }
    }

    /**
     * Check if an update is available by comparing versions
     */
    private function isUpdateAvailable(string $currentVersion, string $latestVersion): bool
    {
        // Clean up versions for comparison
        $currentVersion = \ltrim($currentVersion, 'v');
        $latestVersion = \ltrim($latestVersion, 'v');

        return \version_compare($currentVersion, $latestVersion, '<');
    }

    /**
     * Download the latest version to a temporary file
     */
    private function downloadLatestVersion(string $fileName, string $version, SymfonyStyle $output): string
    {
        $downloadUrl = \sprintf(self::GITHUB_DOWNLOAD_URL, $version, $fileName);

        $output->text("Requesting from: $downloadUrl");

        $response = $this->httpClient->getWithRedirects(
            $downloadUrl,
            ['User-Agent' => 'Context-Generator-Self-Update'],
        );

        if (!$response->isSuccess()) {
            throw new \RuntimeException(
                "Failed to download PHAR. Server returned status code {$response->getStatusCode()}",
            );
        }

        // Create a temporary file
        $tempFile = \sys_get_temp_dir() . '/context-generator-' . \uniqid() . '.phar';

        // Use FilesInterface to write the content
        $this->files->write($tempFile, $response->getBody());

        // Verify the downloaded file
        if (!$this->files->exists($tempFile)) {
            throw new \RuntimeException("Downloaded PHAR file does not exist");
        }

        $output->text("Downloaded new version to temporary file: $tempFile");

        return $tempFile;
    }

    /**
     * Install the update by replacing the current PHAR
     */
    private function installUpdate(string $tempFile, string $pharPath, SymfonyStyle $output): void
    {
        try {
            $output->text("Replacing current PHAR at: {$pharPath}");

            // On Windows, we need to delete the file first
            if (\PHP_OS_FAMILY === 'Windows' && $this->files->exists($pharPath)) {
                // Since FilesInterface doesn't have a method to delete files,
                // we have to use native PHP function here
                if (!\unlink($pharPath)) {
                    throw new \RuntimeException("Failed to delete current PHAR file");
                }
            }

            // Read the content from temp file
            $newPharContent = $this->files->read($tempFile);
            if ($newPharContent === false) {
                throw new \RuntimeException("Failed to read the downloaded PHAR content");
            }

            // Write the content to the target file
            $this->files->write($pharPath, $newPharContent);

            // Make sure the new PHAR is executable
            if (\PHP_OS_FAMILY !== 'Windows') {
                // FilesInterface doesn't provide chmod functionality,
                // so we still need the native function here
                if (!\chmod($pharPath, 0755)) {
                    $output->warning("Failed to set executable permissions on the new PHAR file");
                }
            }

            $output->text("Successfully replaced the PHAR file");
        } finally {
            // Since FilesInterface doesn't have a delete method,
            // we need to use unlink directly
            if (\file_exists($tempFile)) {
                \unlink($tempFile);
            }
        }
    }
}
