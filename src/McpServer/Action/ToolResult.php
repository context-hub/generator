<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action;

use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;

/**
 * Helper class to simplify creating CallToolResult instances for MCP tool actions.
 */
final class ToolResult
{
    /**
     * Create a successful tool result with data.
     */
    public static function success(array|\JsonSerializable $data): CallToolResult
    {
        return new CallToolResult([
            new TextContent(
                text: \json_encode($data),
            ),
        ]);
    }

    /**
     * Create an error tool result with an error message.
     */
    public static function error(string $error): CallToolResult
    {
        return new CallToolResult([
            new TextContent(
                text: \json_encode([
                    'success' => false,
                    'error' => $error,
                ]),
            ),
        ], isError: true);
    }

    /**
     * Create an error tool result with validation details.
     */
    public static function validationError(array $validationErrors): CallToolResult
    {
        return new CallToolResult([
            new TextContent(
                text: \json_encode([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validationErrors,
                ]),
            ),
        ], isError: true);
    }

    /**
     * Create a simple text result (for cases where just text is returned).
     */
    public static function text(string $text): CallToolResult
    {
        return new CallToolResult([
            new TextContent(
                text: $text,
            ),
        ]);
    }
}
