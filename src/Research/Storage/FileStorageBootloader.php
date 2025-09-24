<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Storage;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Research\Config\ResearchConfigInterface;
use Butschster\ContextGenerator\Research\Repository\EntryRepositoryInterface;
use Butschster\ContextGenerator\Research\Repository\ResearchRepositoryInterface;
use Butschster\ContextGenerator\Research\Repository\TemplateRepositoryInterface;
use Butschster\ContextGenerator\Research\Storage\FileStorage\FileStorageConfig;
use Butschster\ContextGenerator\Research\Storage\FileStorage\FileEntryRepository;
use Butschster\ContextGenerator\Research\Storage\FileStorage\FileResearchRepository;
use Butschster\ContextGenerator\Research\Storage\FileStorage\FileStorageDriver;
use Butschster\ContextGenerator\Research\Storage\FileStorage\FileTemplateRepository;
use Butschster\ContextGenerator\Research\Storage\FileStorage\FrontmatterParser;
use Cocur\Slugify\Slugify;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Files\FilesInterface;
use Psr\Log\LoggerInterface;

final class FileStorageBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            FrontmatterParser::class => FrontmatterParser::class,
            TemplateRepositoryInterface::class => FileTemplateRepository::class,
            ResearchRepositoryInterface::class => FileResearchRepository::class,
            EntryRepositoryInterface::class => FileEntryRepository::class,

            // Storage driver
            StorageDriverInterface::class => static function (
                ResearchConfigInterface $config,
                FilesInterface $files,
                LoggerInterface $logger,
                ExceptionReporterInterface $reporter,
                DirectoriesInterface $dirs,
                TemplateRepositoryInterface $templateRepository,
                ResearchRepositoryInterface $researchRepository,
                EntryRepositoryInterface $entryRepository,
            ): StorageDriverInterface {
                return new FileStorageDriver(
                    driverConfig: FileStorageConfig::fromArray([
                        'base_path' => $config->getResearchesPath(),
                        'templates_path' => $config->getTemplatesPath(),
                        'default_entry_status' => $config->getDefaultEntryStatus(),
                    ]),
                    templateRepository: $templateRepository,
                    researchRepository: $researchRepository,
                    entryRepository: $entryRepository,
                    slugify: new Slugify(),
                    logger: $logger,
                );
            },
        ];
    }
}
