<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Research\Repository;

use Butschster\ContextGenerator\Research\Domain\Model\Template;
use Butschster\ContextGenerator\Research\Domain\ValueObject\TemplateKey;

/**
 * Repository interface for managing templates
 */
interface TemplateRepositoryInterface
{
    /**
     * Find all available templates
     *
     * @return Template[]
     */
    public function findAll(): array;

    /**
     * Find template by key
     */
    public function findByKey(TemplateKey $key): ?Template;

    /**
     * Check if template exists
     */
    public function exists(TemplateKey $key): bool;

    /**
     * Refresh template cache/data
     */
    public function refresh(): void;
}
