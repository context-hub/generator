<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Filesystem\FileReplaceContent;

/**
 * Line ending styles used in text files.
 */
enum LineEnding: string
{
    case Crlf = "\r\n"; // Windows
    case Lf = "\n";     // Unix/macOS
    case Cr = "\r";     // Legacy Mac
}
