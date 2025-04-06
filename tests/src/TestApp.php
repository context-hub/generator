<?php

declare(strict_types=1);

namespace Tests;

use Butschster\ContextGenerator\Application\Kernel;
use Spiral\Core\Container;

class TestApp extends Kernel implements TestableKernelInterface
{
    public function getContainer(): Container
    {
        return $this->container;
    }
}
