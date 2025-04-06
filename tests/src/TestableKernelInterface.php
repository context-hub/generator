<?php

declare(strict_types=1);

namespace Tests;

use Spiral\Core\Container;

interface TestableKernelInterface
{
    /**
     * Get application container
     */
    public function getContainer(): Container;
}
