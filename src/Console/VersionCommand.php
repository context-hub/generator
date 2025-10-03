<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

use Butschster\ContextGenerator\Application\Application;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpException;
use Spiral\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'version',
    description: 'Display the current version and check for updates',
)]
final class VersionCommand extends BaseCommand
{
    /**
     * GitHub API URL for latest release
     */
    private const string GITHUB_API_LATEST_RELEASE = 'https://api.github.com/repos/context-hub/generator/releases/latest';

    #[Option(
        name: 'check-updates',
        shortcut: 'c',
        description: 'Check for updates',
    )]
    protected bool $checkUpdates = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    public function __invoke(Application $app): int
    {
        $this->output->title($app->name);
        $this->output->text('Current version: ' . $app->version);

        if ($this->checkUpdates) {
            $this->output->newLine();
            $this->output->section('Checking for updates...');

            try {
                $latestVersion = $this->fetchLatestVersion();
                $isUpdateAvailable = $this->isUpdateAvailable(currentVersion: $app->version, latestVersion: $latestVersion);

                if ($isUpdateAvailable) {
                    $this->output->success("A new version is available: {$latestVersion}");
                    $this->output->text([
                        'You can update by running:',
                        'ctx self-update',
                        '',
                        'Or with these alternative methods:',
                        '- curl -sSL https://raw.githubusercontent.com/context-hub/generator/main/download-latest.sh | sh',
                        '- Download from: https://github.com/context-hub/generator/releases/download/' . $latestVersion . '/context-generator.phar',
                    ]);
                } else {
                    $this->output->success("You're using the latest version ({$app->version})");
                }
            } catch (HttpException $e) {
                $this->output->error("Failed to check for updates: {$e->getMessage()}");
            } catch (\Throwable $e) {
                $this->output->error("Error checking for updates: {$e->getMessage()}");
            }
        } else {
            $this->output->newLine();
            $this->output->text("Run with --check-updates or -c to check for new versions");
        }

        return Command::SUCCESS;
    }

    /**
     * Fetch the latest version from GitHub
     *
     * @throws HttpException If there's an issue with the HTTP request or response
     */
    private function fetchLatestVersion(): string
    {
        $response = $this->httpClient->get(
            self::GITHUB_API_LATEST_RELEASE,
            [
                'User-Agent' => 'Context-Generator-Version-Check',
                'Accept' => 'application/vnd.github.v3+json',
            ],
        );

        if (!$response->isSuccess()) {
            throw new HttpException(
                message: \sprintf('Failed to fetch latest version. Server returned status code %d', $response->getStatusCode()),
            );
        }

        $tagName = $response->getJsonValue(key: 'tag_name');

        if ($tagName === null) {
            throw new HttpException(message: "Invalid response format: 'tag_name' missing");
        }

        // Remove 'v' prefix if present
        return \ltrim(string: (string) $tagName, characters: 'v');
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
        $currentVersion = \ltrim(string: $currentVersion, characters: 'v');
        $latestVersion = \ltrim(string: $latestVersion, characters: 'v');

        return \version_compare($currentVersion, $latestVersion, '<');
    }
}
