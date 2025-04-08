<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP\Operations;

use Butschster\ContextGenerator\Lib\Content\ContentBuilder;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Mcp\Client\ClientSession;

/**
 * Interface for operations that can be executed on an MCP server
 */
interface OperationInterface extends \JsonSerializable
{
    /**
     * Get the type of this operation
     */
    public function getType(): string;

    /**
     * Execute this operation on the given client session
     *
     * @param ClientSession $session MCP client session
     * @param VariableResolver $resolver Variable resolver for resolving variables in configuration
     * @return string Raw content returned by the operation
     */
    public function execute(ClientSession $session, VariableResolver $resolver): string;

    /**
     * Build content for this operation
     *
     * @param ContentBuilder $builder Content builder to add content to
     * @param string $content The processed content to build with
     */
    public function buildContent(ContentBuilder $builder, string $content): void;
}
