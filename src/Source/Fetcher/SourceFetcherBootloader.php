<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Fetcher;

use Butschster\ContextGenerator\SourceParserInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Container;

#[Singleton]
final class SourceFetcherBootloader extends Bootloader
{
    /** @var class-string<SourceFetcherInterface>[] */
    private array $fetchers = [];

    /**
     * Register a source fetcher
     * @param class-string<SourceFetcherInterface> $fetcher
     */
    public function register(string $fetcher): self
    {
        $this->fetchers[] = $fetcher;

        return $this;
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            SourceParserInterface::class => static function (
                Container $container,
                SourceFetcherBootloader $bootloader,
            ) {
                $fetchers = $bootloader->getFetchers();
                return new SourceFetcherProvider(
                    fetchers: \array_map(
                        callback: static fn(string $fetcher) => $container->get(id: $fetcher),
                        array: $fetchers,
                    ),
                );
            },
        ];
    }

    public function getFetchers(): array
    {
        return $this->fetchers;
    }
}
