<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Storage;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Drafling\Config\DraflingConfigInterface;
use Butschster\ContextGenerator\Drafling\Repository\EntryRepositoryInterface;
use Butschster\ContextGenerator\Drafling\Repository\ProjectRepositoryInterface;
use Butschster\ContextGenerator\Drafling\Repository\TemplateRepositoryInterface;
use Butschster\ContextGenerator\Drafling\Storage\Config\FileStorageConfig;
use Butschster\ContextGenerator\Drafling\Storage\FileStorage\FileStorageDriver;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Files\FilesInterface;
use Psr\Log\LoggerInterface;

/**
 * Bootloader for Drafling storage layer
 */
final class StorageBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            // Storage driver
            StorageDriverInterface::class => static function (
                DraflingConfigInterface $config,
                FilesInterface $files,
                LoggerInterface $logger,
                ExceptionReporterInterface $reporter,
                DirectoriesInterface $dirs,
            ): StorageDriverInterface {
                $driver = new FileStorageDriver($config, $files, $dirs, $reporter, $logger);

                // Initialize with typed configuration
                $storageConfig = FileStorageConfig::fromArray([
                    'base_path' => $config->getProjectsPath(),
                    'templates_path' => $config->getTemplatesPath(),
                    'default_entry_status' => $config->getDefaultEntryStatus(),
                ]);

                $driver->initialize($storageConfig);

                return $driver;
            },

            // Repositories - bind to storage driver repositories
            TemplateRepositoryInterface::class => static function (StorageDriverInterface $driver): TemplateRepositoryInterface {
                if ($driver instanceof FileStorageDriver) {
                    return $driver->getTemplateRepository();
                }

                throw new \RuntimeException('Storage driver does not support template repository');
            },

            ProjectRepositoryInterface::class => static function (StorageDriverInterface $driver): ProjectRepositoryInterface {
                if ($driver instanceof FileStorageDriver) {
                    return $driver->getProjectRepository();
                }

                throw new \RuntimeException('Storage driver does not support project repository');
            },

            EntryRepositoryInterface::class => static function (StorageDriverInterface $driver): EntryRepositoryInterface {
                if ($driver instanceof FileStorageDriver) {
                    return $driver->getEntryRepository();
                }

                throw new \RuntimeException('Storage driver does not support entry repository');
            },
        ];
    }

    public function boot(StorageDriverInterface $storageDriver): void
    {
        // Synchronize storage on boot
        $storageDriver->synchronize();
    }
}
