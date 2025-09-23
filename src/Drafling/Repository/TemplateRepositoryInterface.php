<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Drafling\Repository;

use Butschster\ContextGenerator\Drafling\Domain\Model\Template;
use Butschster\ContextGenerator\Drafling\Domain\ValueObject\TemplateKey;

/**
 * Template repository contract for accessing template data
 */
interface TemplateRepositoryInterface
{
    /**
     * Find all templates
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
     * Refresh template cache/data from source
     */
    public function refresh(): void;
}
