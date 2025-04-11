<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP\Operations;

use Butschster\ContextGenerator\Lib\Content\ContentBuilder;

/**
 * Abstract base class for operations
 */
abstract readonly class AbstractOperation implements OperationInterface
{
    /**
     * @param string $type Operation type
     */
    public function __construct(
        protected string $type,
    ) {}

    /**
     * @return string Operation type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Default implementation of buildContent
     *
     * @param ContentBuilder $builder Content builder to add content to
     * @param string $content The content to be built
     */
    public function buildContent(ContentBuilder $builder, string $content): void
    {
        $builder->addText($content);
    }

    /**
     * Default implementation of jsonSerialize
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
        ];
    }
}
