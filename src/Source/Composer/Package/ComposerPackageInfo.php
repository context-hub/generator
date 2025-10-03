<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Composer\Package;

/**
 * Value object to hold information about a Composer package
 */
final readonly class ComposerPackageInfo
{
    /**
     * @param string $name The package name
     * @param string $path The package path in the filesystem
     * @param string $version The package version
     * @param array<string, mixed> $composerConfig The package's composer.json contents
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $version,
        public array $composerConfig = [],
    ) {}

    /**
     * Get the package description
     */
    public function getDescription(): string
    {
        return $this->composerConfig['description'] ?? '';
    }

    /**
     * Get the package authors
     *
     * @return array<array<string, string>>
     */
    public function getAuthors(): array
    {
        return $this->composerConfig['authors'] ?? [];
    }

    /**
     * Get formatted authors string
     */
    public function getFormattedAuthors(): string
    {
        $authors = $this->getAuthors();
        if (empty($authors)) {
            return '';
        }

        $formattedAuthors = [];
        foreach ($authors as $author) {
            $authorParts = [];
            if (isset($author['name'])) {
                $authorParts[] = $author['name'];
            }
            if (isset($author['email'])) {
                $authorParts[] = "<{$author['email']}>";
            }

            if (!empty($authorParts)) {
                $formattedAuthors[] = \implode(separator: ' ', array: $authorParts);
            }
        }

        return \implode(separator: ', ', array: $formattedAuthors);
    }

    /**
     * Get the package license
     */
    public function getLicense(): string|array
    {
        return $this->composerConfig['license'] ?? '';
    }

    /**
     * Get formatted license string
     */
    public function getFormattedLicense(): string
    {
        $license = $this->getLicense();

        if (\is_array(value: $license)) {
            return \implode(separator: ', ', array: $license);
        }

        return $license;
    }

    /**
     * Get package homepage URL
     */
    public function getHomepage(): string
    {
        return $this->composerConfig['homepage'] ?? '';
    }

    /**
     * Get the package's source directories (usually "src")
     *
     * @return array<string>
     */
    public function getSourceDirectories(): array
    {
        // Check PSR-4 autoload configuration
        $psr4Dirs = [];
        if (isset($this->composerConfig['autoload']['psr-4'])) {
            foreach ($this->composerConfig['autoload']['psr-4'] as $dirs) {
                if (\is_array(value: $dirs)) {
                    foreach ($dirs as $dir) {
                        $psr4Dirs[] = $dir;
                    }
                } else {
                    $psr4Dirs[] = $dirs;
                }
            }
        }

        // If we have PSR-4 directories, return them
        if (!empty($psr4Dirs)) {
            return \array_unique(array: $psr4Dirs);
        }

        // Check PSR-0 autoload configuration as fallback
        $psr0Dirs = [];
        if (isset($this->composerConfig['autoload']['psr-0'])) {
            foreach ($this->composerConfig['autoload']['psr-0'] as $dirs) {
                if (\is_array(value: $dirs)) {
                    foreach ($dirs as $dir) {
                        $psr0Dirs[] = $dir;
                    }
                } else {
                    $psr0Dirs[] = $dirs;
                }
            }
        }

        // If we have PSR-0 directories, return them
        if (!empty($psr0Dirs)) {
            return \array_unique(array: $psr0Dirs);
        }

        // Check classmap autoload configuration as fallback
        $classmapDirs = [];
        if (isset($this->composerConfig['autoload']['classmap'])
            && \is_array(value: $this->composerConfig['autoload']['classmap'])
        ) {
            foreach ($this->composerConfig['autoload']['classmap'] as $dir) {
                if (\is_string(value: $dir)) {
                    $classmapDirs[] = $dir;
                }
            }
        }

        // If we have classmap directories, return them
        if (!empty($classmapDirs)) {
            return \array_unique(array: $classmapDirs);
        }

        // Fallback to common source directories
        $commonDirs = ['src', 'lib', 'library'];
        foreach ($commonDirs as $dir) {
            if (\is_dir(filename: $this->path . '/' . $dir)) {
                return [$dir];
            }
        }

        // If no source directory is found, return the package root
        return ['.'];
    }

    /**
     * Get the package's keywords
     *
     * @return array<string>
     */
    public function getKeywords(): array
    {
        return $this->composerConfig['keywords'] ?? [];
    }

    /**
     * Get formatted keywords string
     */
    public function getFormattedKeywords(): string
    {
        $keywords = $this->getKeywords();
        if (empty($keywords)) {
            return '';
        }

        return \implode(separator: ', ', array: $keywords);
    }

    /**
     * Get the package type
     */
    public function getType(): string
    {
        return $this->composerConfig['type'] ?? 'library';
    }

    /**
     * Check if the package is abandoned
     */
    public function isAbandoned(): bool
    {
        return isset($this->composerConfig['abandoned']) &&
            ($this->composerConfig['abandoned'] === true || \is_string(value: $this->composerConfig['abandoned']));
    }

    /**
     * Get the replacement package if abandoned
     */
    public function getReplacementPackage(): ?string
    {
        if ($this->isAbandoned() && \is_string(value: $this->composerConfig['abandoned'])) {
            return $this->composerConfig['abandoned'];
        }

        return null;
    }

    /**
     * Get the package's support information
     *
     * @return array<string, string>
     */
    public function getSupport(): array
    {
        return $this->composerConfig['support'] ?? [];
    }

    /**
     * Get formatted support information
     */
    public function getFormattedSupport(): string
    {
        $support = $this->getSupport();
        if (empty($support)) {
            return '';
        }

        $result = [];

        if (isset($support['issues'])) {
            $result[] = "Issues: {$support['issues']}";
        }

        if (isset($support['source'])) {
            $result[] = "Source: {$support['source']}";
        }

        if (isset($support['docs'])) {
            $result[] = "Docs: {$support['docs']}";
        }

        if (isset($support['wiki'])) {
            $result[] = "Wiki: {$support['wiki']}";
        }

        if (isset($support['email'])) {
            $result[] = "Email: {$support['email']}";
        }

        if (isset($support['irc'])) {
            $result[] = "IRC: {$support['irc']}";
        }

        return \implode(separator: "\n", array: $result);
    }
}
