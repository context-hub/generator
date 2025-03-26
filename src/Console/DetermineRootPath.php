<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console;

trait DetermineRootPath
{
    /**
     * Determine the effective root path based on config file path
     */
    protected function determineRootPath(?string $configPath, ?string $inlineConfig): string
    {
        if ($configPath === null || $inlineConfig !== null) {
            return $this->rootPath;
        }

        // If config path is absolute, use its directory as root
        if (\str_starts_with($configPath, '/')) {
            $configDir = \rtrim(\is_dir($configPath) ? $configPath : \dirname($configPath));
        } else {
            // If relative, resolve against the original root path
            $fullConfigPath = \rtrim($this->rootPath, '/') . '/' . $configPath;

            $configDir = \rtrim(\is_dir($fullConfigPath) ? $fullConfigPath : \dirname($fullConfigPath));
        }

        if ($this->files->exists($configDir) && \is_dir($configDir)) {
            $effectiveRootPath = $configDir;
            $this->logger->info('Updated root path based on config file location', [
                'original' => $this->rootPath,
                'effective' => $effectiveRootPath,
            ]);

            return $effectiveRootPath;
        }

        $this->logger->warning('Could not determine directory from config file path', [
            'configPath' => $configPath,
            'using' => $this->rootPath,
        ]);

        return $this->rootPath;
    }
}
