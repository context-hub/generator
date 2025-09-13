<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser;

enum ChangeType: string
{
    case ADD = 'add';
    case REMOVE = 'remove';
    case CONTEXT = 'context';
}
