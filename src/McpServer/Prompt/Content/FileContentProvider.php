<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Content;

use Butschster\ContextGenerator\McpServer\Prompt\Exception\FileMessageContentException;

/**
 * Interface for loading content from various sources.
 */
interface FileContentProvider
{
    /**
     * Checks if this provider can handle the given source.
     */
    public function canHandle(string $source): bool;

    /**
     * Loads content from the given source.
     *
     * @throws FileMessageContentException If content loading fails
     */
    public function load(string $source): string;
}
