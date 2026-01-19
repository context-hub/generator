<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Loader;

use Butschster\ContextGenerator\Rag\Document\DocumentType;
use Butschster\ContextGenerator\Rag\Document\MetadataFactory;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Uid\Uuid;

final readonly class FileSystemLoader
{
    public function __construct(
        private MetadataFactory $metadataFactory,
    ) {}

    /**
     * @return \Generator<TextDocument>
     */
    public function load(
        string $path,
        string $pattern = '*.md',
        bool $recursive = true,
        DocumentType $type = DocumentType::General,
        ?array $tags = null,
    ): \Generator {
        $finder = new Finder();
        $finder->files()->in($path)->name($pattern);

        if (!$recursive) {
            $finder->depth(0);
        }

        foreach ($finder as $file) {
            $content = $file->getContents();
            if (\trim($content) === '') {
                continue;
            }

            yield new TextDocument(
                id: Uuid::v7(),
                content: $content,
                metadata: $this->metadataFactory->create(
                    type: $type,
                    sourcePath: $file->getRelativePathname(),
                    tags: $tags,
                    extra: [
                        'filename' => $file->getFilename(),
                        'size' => $file->getSize(),
                    ],
                ),
            );
        }
    }

    public function count(string $path, string $pattern = '*.md', bool $recursive = true): int
    {
        $finder = new Finder();
        $finder->files()->in($path)->name($pattern);

        if (!$recursive) {
            $finder->depth(0);
        }

        return $finder->count();
    }
}
