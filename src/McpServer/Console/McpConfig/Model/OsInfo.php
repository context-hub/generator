<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Console\McpConfig\Model;

final readonly class OsInfo
{
    public function __construct(
        public string $osName,
        public bool $isWindows,
        public bool $isLinux,
        public bool $isMacOs,
        public bool $isWsl,
        public string $phpOs,
        public array $additionalInfo = [],
    ) {}

    public function getDisplayName(): string
    {
        if ($this->isWsl) {
            $distro = $this->additionalInfo['wsl_distro'] ?? 'Unknown';
            return "WSL ({$distro})";
        }

        return $this->osName;
    }

    public function requiresSpecialHandling(): bool
    {
        return $this->isWsl || $this->isWindows;
    }

    public function getShellCommand(): string
    {
        if ($this->isWsl) {
            return 'bash.exe';
        }

        if ($this->isWindows) {
            return 'ctx.exe';
        }

        return 'ctx';
    }

    public function getConfigType(): string
    {
        return match (true) {
            $this->isWsl => 'wsl',
            $this->isWindows => 'windows',
            $this->isLinux => 'linux',
            $this->isMacOs => 'macos',
            default => 'generic',
        };
    }
}
