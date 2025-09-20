<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console\McpConfig\Service;

use Butschster\ContextGenerator\McpServer\Console\McpConfig\Model\OsInfo;

final class OsDetectionService
{
    public function detect(bool $forceWsl = false): OsInfo
    {
        if ($forceWsl) {
            return new OsInfo(
                osName: 'WSL',
                isWindows: true,
                isLinux: false,
                isMacOs: false,
                isWsl: true,
                phpOs: PHP_OS,
                additionalInfo: $this->gatherEnvironmentInfo(),
            );
        }

        $phpOs = PHP_OS;
        $isWindows = $this->isWindows($phpOs);
        $isLinux = $this->isLinux($phpOs);
        $isMacOs = $this->isMacOs($phpOs);
        $isWsl = $this->detectWsl();

        // If we detected WSL, override the OS detection
        if ($isWsl) {
            $osName = 'WSL';
            $isLinux = false; // WSL is technically Windows with Linux compatibility
        } else {
            $osName = match (true) {
                $isWindows => 'Windows',
                $isLinux => 'Linux',
                $isMacOs => 'macOS',
                default => 'Unknown',
            };
        }

        return new OsInfo(
            osName: $osName,
            isWindows: $isWindows,
            isLinux: $isLinux,
            isMacOs: $isMacOs,
            isWsl: $isWsl,
            phpOs: $phpOs,
            additionalInfo: $this->gatherEnvironmentInfo(),
        );
    }

    private function isWindows(string $phpOs): bool
    {
        return \str_starts_with(\strtoupper($phpOs), 'WIN');
    }

    private function isLinux(string $phpOs): bool
    {
        return \strtoupper($phpOs) === 'LINUX';
    }

    private function isMacOs(string $phpOs): bool
    {
        return \strtoupper($phpOs) === 'DARWIN';
    }

    private function detectWsl(): bool
    {
        // Multiple methods to detect WSL
        
        // Method 1: Check for WSL environment variables
        if (\getenv('WSL_DISTRO_NAME') !== false || \getenv('WSLENV') !== false) {
            return true;
        }

        // Method 2: Check for /proc/version (Linux-based detection)
        if (\file_exists('/proc/version')) {
            $version = \file_get_contents('/proc/version');
            if ($version !== false && (\str_contains($version, 'Microsoft') || \str_contains($version, 'WSL'))) {
                return true;
            }
        }

        // Method 3: Check for WSL-specific paths
        if (\file_exists('/mnt/c') || \file_exists('/proc/sys/fs/binfmt_misc/WSLInterop')) {
            return true;
        }

        // Method 4: Check uname output
        if (\function_exists('shell_exec')) {
            $uname = \shell_exec('uname -r 2>/dev/null');
            if ($uname !== null && (\str_contains($uname, 'Microsoft') || \str_contains($uname, 'WSL'))) {
                return true;
            }
        }

        return false;
    }

    private function gatherEnvironmentInfo(): array
    {
        $info = [
            'php_version' => PHP_VERSION,
            'php_os' => PHP_OS,
        ];

        // Add WSL-specific info if available
        if ($wslDistro = \getenv('WSL_DISTRO_NAME')) {
            $info['wsl_distro'] = $wslDistro;
        }

        // Add architecture info
        if (\function_exists('php_uname')) {
            $info['architecture'] = \php_uname('m');
        }

        // Add shell info if available
        if ($shell = \getenv('SHELL')) {
            $info['shell'] = $shell;
        }

        return $info;
    }
}
