<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\Registry;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\Modifier\Alias\ModifierResolver;
use Psr\Log\LoggerInterface;

/**
 * Base class for source factories
 */
abstract readonly class AbstractSourceFactory implements SourceFactoryInterface
{
    public function __construct(
        protected DirectoriesInterface $dirs,
        protected ModifierResolver $modifierResolver = new ModifierResolver(),
        protected ?LoggerInterface $logger = null,
    ) {}

    /**
     * Parse modifiers configuration
     *
     * @param array<string, mixed> $modifiersConfig
     * @return array<\Butschster\ContextGenerator\Modifier\Modifier>
     */
    protected function parseModifiers(array $modifiersConfig): array
    {
        return $this->modifierResolver->resolveAll($modifiersConfig);
    }
}
