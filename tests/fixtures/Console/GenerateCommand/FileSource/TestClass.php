<?php

declare(strict_types=1);

namespace Tests\Fixtures;

class TestClass
{
    private string $property = 'test';

    public function testMethod(): string
    {
        return $this->property;
    }

    public function anotherMethod(): void
    {
        // Just a comment
        echo "Hello World";
    }
}
