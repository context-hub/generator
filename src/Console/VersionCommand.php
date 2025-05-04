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
        // Display application title and version information
        $this->outputService->title($app->name);
        
        // Use key-value format for version info
        $this->outputService->keyValue(
            'Current Version',
            $this->outputService->highlight($app->version, 'bright-cyan')
        );
        
        $this->outputService->keyValue(
            'Binary Status',
            $app->isBinary ? 
                $this->outputService->highlight('Binary Install', 'bright-green') : 
                $this->outputService->highlight('Development Mode', 'bright-blue')
        );

        if ($this->checkUpdates) {
            // Section for update checking
            $this->outputService->section('Checking for Updates');
            $this->outputService->info('Connecting to GitHub to check for newer versions...');

            try {
                $latestVersion = $this->fetchLatestVersion();
                $isUpdateAvailable = $this->isUpdateAvailable($app->version, $latestVersion);
                
                // Display latest version info
                $this->outputService->keyValue(
                    'Latest Available Version',
                    $this->outputService->highlight($latestVersion, 'bright-green')
                );

                if ($isUpdateAvailable) {
                    // Display update status with successful check
                    $statusRenderer = $this->outputService->getStatusRenderer();
                    $statusRenderer->renderSuccess(
                        'Version Check',
                        'New version available'
                    );
                    
                    // Display update message with version comparison
                    $this->outputService->success(\sprintf(
                        "A new version is available: %s → %s",
                        $this->outputService->highlight($app->version, 'bright-cyan'),
                        $this->outputService->highlight($latestVersion, 'bright-green')
                    ));
                    
                    // Display update methods section
                    $this->outputService->section('Update Methods');
                    
                    // Use list renderer for update options
                    $listRenderer = $this->outputService->getListRenderer();
                    $listRenderer->renderBulletList([
                        \sprintf(
                            'Run %s to update automatically',
                            $this->outputService->highlight('ctx self-update', 'bright-cyan')
                        ),
                        \sprintf(
                            'Use curl: %s',
                            $this->outputService->highlight(
                                'curl -sSL https://raw.githubusercontent.com/context-hub/generator/main/download-latest.sh | sh',
                                'bright-cyan'
                            )
                        ),
                        \sprintf(
                            'Download from GitHub: %s',
                            $this->outputService->highlight(
                                'https://github.com/context-hub/generator/releases/download/' . $latestVersion . '/context-generator.phar',
                                'bright-cyan'
                            )
                        ),
                    ]);
                } else {
                    // Display status for up-to-date system
                    $statusRenderer = $this->outputService->getStatusRenderer();
                    $statusRenderer->renderSuccess(
                        'Version Check',
                        'You are using the latest version'
                    );
                    
                    $this->outputService->success(\sprintf(
                        "You're using the latest version (%s)",
                        $this->outputService->highlight($app->version, 'bright-cyan')
                    ));
                }
            } catch (HttpException $e) {
                // Handle HTTP errors
                $this->outputService->error("Failed to check for updates: " . $e->getMessage());
                
                $this->outputService->keyValue(
                    'Error Type',
                    'HTTP Communication Error'
                );
                
                // Add troubleshooting tips
                $this->outputService->note([
                    'Troubleshooting:',
                    '- Check your internet connection',
                    '- Verify GitHub is accessible from your network',
                    '- GitHub rate limits might apply for frequent requests'
                ]);
            } catch (\Throwable $e) {
                // Handle general errors
                $this->outputService->error("Error checking for updates: " . $e->getMessage());
                
                $this->logger->error('Version check failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } else {
            // Show hint for update checking
            $this->outputService->info(\sprintf(
                "Run with %s to check for new versions",
                $this->outputService->highlight('--check-updates', 'bright-cyan')
            ));
            
            // Add additional usage information
            $this->outputService->note([
                'Available options:',
                '- Use --check-updates (-c) to check for newer versions',
                '- Use self-update command to automatically update to the latest version'
            ]);
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
        $this->outputService->info("Fetching latest version information from GitHub...");
        
        $response = $this->httpClient->get(
            self::GITHUB_API_LATEST_RELEASE,
            [
                'User-Agent' => 'Context-Generator-Version-Check',
                'Accept' => 'application/vnd.github.v3+json',
            ],
        );

        if (!$response->isSuccess()) {
            $statusCode = $response->getStatusCode();
            throw new HttpException(
                \sprintf(
                    'Failed to fetch latest version. Server returned status code %s',
                    $this->outputService->highlight((string) $statusCode, 'red', true)
                ),
            );
        }

        $tagName = $response->getJsonValue('tag_name');

        if ($tagName === null) {
            throw new HttpException("Invalid response format: 'tag_name' missing");
        }

        // Remove 'v' prefix if present
        return \ltrim((string) $tagName, 'v');
    }

    /**
     * Check if an update is available by comparing versions
     */
    private function isUpdateAvailable(string $currentVersion, string $latestVersion): bool
    {
        // If current version is 'dev', always suggest update
        if ($currentVersion === 'dev') {
            $this->outputService->info(
                "Development version detected, update is recommended"
            );
            return true;
        }

        // Clean up versions for comparison
        $currentVersion = \ltrim($currentVersion, 'v');
        $latestVersion = \ltrim($latestVersion, 'v');

        return \version_compare($currentVersion, $latestVersion, '<');
    }
}
