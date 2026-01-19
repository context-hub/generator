<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\JsonSchema\Combinator;

use Butschster\ContextGenerator\JsonSchema\Property\PropertyInterface;

/**
 * Contract for schema combinators (oneOf, anyOf, allOf).
 *
 * Combinators extend PropertyInterface because they can be used
 * anywhere a property can be used - they combine multiple schemas
 * into a single schema constraint.
 */
interface CombinatorInterface extends PropertyInterface
{
    // Inherits toArray() and getReferences() from PropertyInterface
    // Combinators ARE properties (can be used in same places)
}
