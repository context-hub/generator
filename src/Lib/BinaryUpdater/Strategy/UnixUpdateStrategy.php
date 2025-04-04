<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\BinaryUpdater\Strategy;

use Psr\Log\LoggerInterface;
use Spiral\Files\FilesInterface;

/**
 * Unix-specific strategy for updating binary files.
 * Creates a bash script that runs in the background after the current process exits.
 */
final readonly class UnixUpdateStrategy implements UpdateStrategyInterface
{
    public function __construct(
        private FilesInterface $files,
        private ?LoggerInterface $logger = null,
    ) {}

    public function update(string $sourcePath, string $targetPath): bool
    {
        // Create the update script
        $scriptPath = $this->createUpdateScript($sourcePath, $targetPath);

        if ($scriptPath === null) {
            return false;
        }

        // Make the script executable
        if (!\chmod($scriptPath, 0755)) {
            return false;
        }

        // Run the script in the background and continue after PHP exits
        $command = \sprintf(
            'nohup %s > /dev/null 2>&1 & echo $!',
            \escapeshellarg($scriptPath),
        );

        // Execute the command and capture the process ID
        $output = [];
        $resultCode = 0;
        \exec($command, $output, $resultCode);

        // If we got a process ID and the command executed successfully, consider it a success
        return $resultCode === 0 && !empty($output[0]) && \is_numeric($output[0]);
    }

    /**
     * Create a temporary bash script that will perform the update.
     *
     * @return string|null Path to the created script, or null if creation failed
     */
    private function createUpdateScript(string $sourcePath, string $targetPath): ?string
    {
        $scriptPath = $this->files->tempFilename('.sh');

        try {
            $scriptContent = <<<BASH
                #!/bin/bash
                
                # Wait for the parent process to exit
                sleep 1
                
                # Define paths
                SOURCE="{$sourcePath}"
                TARGET="{$targetPath}"
                TARGET_DIR="\$(dirname "\$TARGET")"
                
                # Set up retry logic
                MAX_ATTEMPTS=10
                ATTEMPT=1
                SUCCESS=0
                
                echo "Starting update process for \$TARGET"
                
                # Create the target directory if it doesn't exist
                mkdir -p "\$TARGET_DIR"
                
                # Try to update the file with multiple attempts
                while [ \$ATTEMPT -le \$MAX_ATTEMPTS ] && [ \$SUCCESS -eq 0 ]; do
                    echo "Attempt \$ATTEMPT: Trying to update \$TARGET"
                
                    # Try to copy the file
                    if cp "\$SOURCE" "\$TARGET" 2>/dev/null; then
                        # Make the file executable
                        chmod 755 "\$TARGET"
                        echo "Update successful!"
                        SUCCESS=1
                    else
                        echo "Binary busy or permission denied, waiting 2 seconds..."
                        sleep 2
                        ATTEMPT=\$((ATTEMPT+1))
                    fi
                done
                
                # Clean up the temporary source file
                rm -f "\$SOURCE"
                rm -f "$scriptPath"
                
                # Exit with the appropriate status
                if [ \$SUCCESS -eq 0 ]; then
                    echo "Update failed after \$MAX_ATTEMPTS attempts."
                    exit 1
                fi
                
                exit 0
                BASH;

            $this->files->write($scriptPath, $scriptContent);
            return $scriptPath;
        } catch (\Throwable) {
            return null;
        }
    }
}
