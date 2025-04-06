<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Tool extends McpItem
{
    // Tool doesn't have additional properties as InputSchema is a separate attribute
}
