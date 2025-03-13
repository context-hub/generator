<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

#[AsCommand(
    name: 'version',
    description: 'Display the current version and check for updates',
)]
final class VersionCommand extends Command
{
    /**
     * GitHub API URL for latest release
     */
    private const GITHUB_API_LATEST_RELEASE = 'https://api.github.com/repos/butschster/context-generator/releases/latest';

    public function __construct(
        private readonly string $version,
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'check-updates',
            shortcut: 'c',
            mode: InputOption::VALUE_NONE,
            description: 'Check for updates',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Context Generator');
        $io->text('Current version: ' . $this->version);

        $checkUpdates = $input->getOption('check-updates');

        if ($checkUpdates) {
            if ($this->httpClient === null || $this->requestFactory === null) {
                $io->warning(
                    'HTTP client not available. Install psr/http-client implementation (like guzzlehttp/guzzle) to check for updates.',
                );
                return Command::SUCCESS;
            }

            $io->newLine();
            $io->section('Checking for updates...');

            try {
                $latestVersion = $this->fetchLatestVersion();
                $isUpdateAvailable = $this->isUpdateAvailable($this->version, $latestVersion);

                if ($isUpdateAvailable) {
                    $io->success("A new version is available: {$latestVersion}");
                    $io->text([
                        'You can update by running:',
                        'curl -sSL https://raw.githubusercontent.com/butschster/context-generator/main/download-latest.sh | sh',
                        '',
                        'Or download the latest PHAR from:',
                        'https://github.com/butschster/context-generator/releases/download/' . $latestVersion . '/context-generator.phar',
                    ]);
                } else {
                    $io->success("You're using the latest version ({$this->version})");
                }
            } catch (\Throwable $e) {
                $io->error("Failed to check for updates: {$e->getMessage()}");
            }
        } else {
            $io->newLine();
            $io->text("Run with --check-updates or -c to check for new versions");
        }

        return Command::SUCCESS;
    }

    /**
     * Fetch the latest version from GitHub
     */
    private function fetchLatestVersion(): string
    {
        $request = $this->requestFactory->createRequest('GET', self::GITHUB_API_LATEST_RELEASE);
        $request = $request->withHeader('User-Agent', 'Context-Generator-Version-Check');
        $request = $request->withHeader('Accept', 'application/vnd.github.v3+json');

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                "Failed to fetch latest version. Server returned status code {$response->getStatusCode()}",
            );
        }

        $responseBody = $response->getBody()->getContents();
        $data = \json_decode($responseBody, true);

        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to parse response: " . \json_last_error_msg());
        }

        if (!isset($data['tag_name'])) {
            throw new \RuntimeException("Invalid response format: 'tag_name' missing");
        }

        // Remove 'v' prefix if present
        return \ltrim($data['tag_name'], 'v');
    }

    /**
     * Check if an update is available by comparing versions
     */
    private function isUpdateAvailable(string $currentVersion, string $latestVersion): bool
    {
        // If current version is 'dev', always suggest update
        if ($currentVersion === 'dev') {
            return true;
        }

        // Clean up versions for comparison
        $currentVersion = \ltrim($currentVersion, 'v');
        $latestVersion = \ltrim($latestVersion, 'v');

        return \version_compare($currentVersion, $latestVersion, '<');
    }
}
