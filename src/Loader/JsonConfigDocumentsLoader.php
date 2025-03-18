<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Loader;

use Butschster\ContextGenerator\DocumentsLoaderInterface;
use Butschster\ContextGenerator\FilesInterface;
use Butschster\ContextGenerator\Loader\ConfigRegistry\DocumentRegistry;
use Butschster\ContextGenerator\Loader\ConfigRegistry\Parser\ConfigParserInterface;
use Butschster\ContextGenerator\Schema\JsonSchema;
use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\Source\GitDiff\CommitDiffSource;
use Butschster\ContextGenerator\Source\Github\GithubSource;
use Butschster\ContextGenerator\Source\Text\TextSource;
use Butschster\ContextGenerator\Source\Url\UrlSource;
use Butschster\ContextGenerator\SourceInterface;
use CuyZ\Valinor\Mapper\Source\JsonSource;
use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use Psr\Log\LoggerInterface;

final readonly class JsonConfigDocumentsLoader implements DocumentsLoaderInterface
{
    private TreeMapper $mapper;

    private const SOURCE_MAPPERS = [
        'file' => FileSource::class,
        'text' => TextSource::class,
        'url' => UrlSource::class,
        'github' => GithubSource::class,
        'git_diff' => CommitDiffSource::class,
    ];

    /**
     * @param string $configPath Path to JSON configuration file (relative to root or absolute)
     * @param LoggerInterface|null $logger PSR Logger instance
     */
    public function __construct(
        private FilesInterface $files,
        private ConfigParserInterface $parser,
        private string $configPath,
        private ?LoggerInterface $logger = null,
        private string $rootClass = JsonSchema::class,
    ) {
        $this->mapper = (new MapperBuilder())
            ->allowPermissiveTypes()
            ->enableFlexibleCasting()
            ->allowSuperfluousKeys()
            ->infer(
                SourceInterface::class,
                /** @return class-string<FileSource|TextSource|UrlSource|GithubSource|CommitDiffSource> */
                fn(string $type) => self::SOURCE_MAPPERS[$type] ?? throw new \DomainException(
                    "Unhandled type `$type`.",
                ),
            )
            ->mapper();
    }

    public function load(): DocumentRegistry
    {
        $configFile = $this->configPath;
        $this->logger?->info('Loading documents from JSON config', [
            'configFile' => $configFile,
        ]);

        $this->logger?->debug('Reading config file');
        $jsonContent = $this->files->read($configFile);

        if ($jsonContent === false) {
            $errorMessage = \sprintf('Unable to read configuration file: %s', $configFile);
            $this->logger?->error($errorMessage);
            throw new \InvalidArgumentException($errorMessage);
        }

        $this->logger?->debug('Parsing JSON content', [
            'contentLength' => \strlen($jsonContent),
        ]);

        try {
            $config = \json_decode($jsonContent, true, flags: JSON_THROW_ON_ERROR);
            $this->logger?->debug('JSON successfully parsed');
        } catch (\JsonException $e) {
            $errorMessage = \sprintf('Invalid JSON configuration file: %s', $configFile);
            $this->logger?->error($errorMessage, [
                'error' => $e->getMessage(),
            ]);
            throw new \InvalidArgumentException($errorMessage, previous: $e);
        }

        $schema = $this->mapper->map($this->rootClass, new JsonSource($jsonContent));

        $this->logger?->debug('Parsing configuration with config parser');
        $configRegistry = $this->parser->parse($config);

        // Get the DocumentRegistry from the ConfigRegistry
        if (!$configRegistry->has('documents')) {
            $errorMessage = 'No documents found in configuration';
            $this->logger?->error($errorMessage);
            throw new \RuntimeException($errorMessage);
        }

        $documentRegistry = new DocumentRegistry($schema->documents);

        $configRegistry->get('documents', DocumentRegistry::class);
        $documentsCount = \count($documentRegistry->getItems());
        $this->logger?->info('Documents loaded successfully', [
            'documentsCount' => $documentsCount,
        ]);

        return $documentRegistry;
    }

    public function isSupported(): bool
    {
        $isSupported = \file_exists($this->configPath) && \pathinfo($this->configPath, PATHINFO_EXTENSION) === 'json';

        $this->logger?->debug('Checking if config file is supported', [
            'configPath' => $this->configPath,
            'exists' => \file_exists($this->configPath),
            'extension' => \pathinfo($this->configPath, PATHINFO_EXTENSION),
            'isSupported' => $isSupported,
        ]);

        return $isSupported;
    }
}
