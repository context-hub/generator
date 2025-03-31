<?php

declare(strict_types=1);

namespace Tests\ConfigLoader\Import;

use Butschster\ContextGenerator\Config\Import\CircularImportDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CircularImportDetector::class)]
final class CircularImportDetectorTest extends TestCase
{
    #[Test]
    public function it_should_detect_circular_dependencies(): void
    {
        $detector = new CircularImportDetector();

        // Add a path to the stack
        $detector->beginProcessing('/path/to/config1.json');

        // It should detect a circular dependency if we try to add the same path again
        $this->assertTrue($detector->wouldCreateCircularDependency('/path/to/config1.json'));

        // It should not detect a circular dependency for a different path
        $this->assertFalse($detector->wouldCreateCircularDependency('/path/to/config2.json'));
    }

    #[Test]
    public function it_should_throw_exception_for_circular_imports(): void
    {
        $detector = new CircularImportDetector();

        // Add a path to the stack
        $detector->beginProcessing('/path/to/config1.json');

        // Expect an exception when trying to begin processing the same path
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular import detected');

        $detector->beginProcessing('/path/to/config1.json');
    }

    #[Test]
    public function it_should_maintain_import_stack(): void
    {
        $detector = new CircularImportDetector();

        // Add paths to the stack
        $detector->beginProcessing('/path/to/config1.json');
        $detector->beginProcessing('/path/to/config2.json');
        $detector->beginProcessing('/path/to/config3.json');

        // Verify circular dependency detection works with multiple items in stack
        $this->assertTrue($detector->wouldCreateCircularDependency('/path/to/config1.json'));
        $this->assertTrue($detector->wouldCreateCircularDependency('/path/to/config2.json'));
        $this->assertTrue($detector->wouldCreateCircularDependency('/path/to/config3.json'));
        $this->assertFalse($detector->wouldCreateCircularDependency('/path/to/config4.json'));
    }

    #[Test]
    public function it_should_remove_paths_when_processing_ends(): void
    {
        $detector = new CircularImportDetector();

        // Add paths to the stack
        $detector->beginProcessing('/path/to/config1.json');
        $detector->beginProcessing('/path/to/config2.json');
        $detector->beginProcessing('/path/to/config3.json');

        // End processing for the middle path
        $detector->endProcessing('/path/to/config2.json');

        // config2 and config3 should be removed from the stack
        $this->assertTrue($detector->wouldCreateCircularDependency('/path/to/config1.json'));
        $this->assertFalse($detector->wouldCreateCircularDependency('/path/to/config2.json'));
        $this->assertFalse($detector->wouldCreateCircularDependency('/path/to/config3.json'));
    }

    #[Test]
    public function it_should_handle_nonexistent_paths_in_end_processing(): void
    {
        $detector = new CircularImportDetector();

        // Add a path to the stack
        $detector->beginProcessing('/path/to/config1.json');

        // End processing for a path that doesn't exist in the stack
        // This should not throw an exception
        $detector->endProcessing('/path/to/nonexistent.json');

        // The original path should still be in the stack
        $this->assertTrue($detector->wouldCreateCircularDependency('/path/to/config1.json'));
    }
}
