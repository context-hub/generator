<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application;

enum AppScope: string
{
    case Compiler = 'compiler';
    case Mcp = 'mcp';
    case McpServer = 'mcp-server';
    case McpServerRequest = 'mcp-server-request';
}
