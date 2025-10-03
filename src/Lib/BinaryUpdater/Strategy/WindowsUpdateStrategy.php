<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\BinaryUpdater\Strategy;

use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Windows-specific strategy for updating binary files.
 * Creates a batch script that runs in the background after the current process exits.
 */
final readonly class WindowsUpdateStrategy implements UpdateStrategyInterface
{
    public function __construct(
        private FilesInterface $files,
        private ?LoggerInterface $logger = null,
    ) {}

    public function update(string $sourcePath, string $targetPath): bool
    {
        $this->logger?->info("Using Windows update strategy for binary: {$targetPath}");

        // Create the update script
        $this->logger?->info("Creating Windows batch script...");
        $scriptPath = $this->createUpdateScript(sourcePath: $sourcePath, targetPath: $targetPath);

        if ($scriptPath === null) {
            $this->logger?->error("Failed to create Windows batch script");
            return false;
        }

        $this->logger?->info("Created batch script at: {$scriptPath}");

        // Run the script in the background
        $command = \sprintf(
            'start /b "" %s',
            \escapeshellarg(arg: $scriptPath),
        );

        $this->logger?->info("Executing batch script in background with command: {$command}");

        // Execute the command
        $output = [];
        $resultCode = 0;
        \exec(command: $command, output: $output, result_code: $resultCode);

        $success = $resultCode === 0;

        if ($success) {
            $this->logger?->info("Successfully started background update process");
        } else {
            $this->logger?->error("Failed to start background update process, exit code: {$resultCode}");
        }

        return $success;
    }

    /**
     * Create a temporary batch script that will perform the update.
     *
     * @return string|null Path to the created script, or null if creation failed
     */
    private function createUpdateScript(string $sourcePath, string $targetPath): ?string
    {
        try {
            $scriptPath = $this->files->tempFilename('.bat');
            $this->logger?->info("Generated temporary script path: {$scriptPath}");

            // Convert paths to Windows-style
            $sourcePath = \str_replace(search: '/', replace: '\\', subject: $sourcePath);
            $targetPath = \str_replace(search: '/', replace: '\\', subject: $targetPath);
            $targetDir = \str_replace(search: '/', replace: '\\', subject: \dirname(path: $targetPath));
            $scriptPathWin = \str_replace(search: '/', replace: '\\', subject: $scriptPath);

            $this->logger?->debug("Windows paths: source={$sourcePath}, target={$targetPath}, target_dir={$targetDir}");

            $scriptContent = <<<BATCH
                @echo off
                rem Wait for parent process to exit
                timeout /t 1 /nobreak > nul

                rem Define paths
                set SOURCE={$sourcePath}
                set TARGET={$targetPath}
                set TARGET_DIR={$targetDir}

                echo Starting update process for %TARGET%

                rem Create the target directory if it doesn't exist
                if not exist "%TARGET_DIR%" mkdir "%TARGET_DIR%"

                rem Try to update the file with multiple attempts
                set MAX_ATTEMPTS=10
                set ATTEMPT=1
                set SUCCESS=0

                :LOOP
                if %ATTEMPT% gtr %MAX_ATTEMPTS% goto FAILED
                if %SUCCESS% equ 1 goto SUCCESS

                echo Attempt %ATTEMPT%: Trying to update %TARGET%

                rem Try to copy the file
                copy /Y "%SOURCE%" "%TARGET%" > nul 2>&1
                if %ERRORLEVEL% equ 0 (
                    set SUCCESS=1
                    goto SUCCESS
                ) else (
                    echo Binary busy or permission denied, waiting 2 seconds...
                    timeout /t 2 /nobreak > nul
                    set /a ATTEMPT+=1
                    goto LOOP
                )

                :FAILED
                echo Update failed after %MAX_ATTEMPTS% attempts.
                goto CLEANUP

                :SUCCESS
                echo Update successful!

                :CLEANUP
                rem Clean up temporary files
                del "%SOURCE%" > nul 2>&1
                del "{$scriptPathWin}" > nul 2>&1

                if %SUCCESS% equ 0 exit /b 1
                exit /b 0
                BATCH;

            $this->logger?->debug("Writing batch script content to temporary file");
            $this->files->write($scriptPath, $scriptContent);

            return $scriptPath;
        } catch (\Throwable $e) {
            $this->logger?->error("Error creating batch script: {$e->getMessage()}");
            return null;
        }
    }
}
