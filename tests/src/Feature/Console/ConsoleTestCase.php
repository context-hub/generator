<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Spiral\Testing\Traits\InteractsWithConsole;
use Spiral\Testing\Traits\InteractsWithFileSystem;
use Tests\AppTestCase;

abstract class ConsoleTestCase extends AppTestCase
{
    use InteractsWithConsole;
    use InteractsWithFileSystem;
}
