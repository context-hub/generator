<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Tools\Git\Dto;

enum GitStatusFormat: string
{
    case Short = 'short';
    case Porcelain = 'porcelain';
    case Long = 'long';
}
