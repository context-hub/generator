<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Detection\Strategy;

use Butschster\ContextGenerator\Application\FSPath;
use Butschster\ContextGenerator\Template\Detection\Strategy\CompositeDetectionStrategy;
use Butschster\ContextGenerator\Template\Detection\Strategy\TemplateDetectionStrategy;
use Butschster\ContextGenerator\Template\Detection\TemplateDetectionResult;
use Butschster\ContextGenerator\Template\Template;
use PHPUnit\Framework\TestCase;

class CompositeDetectionStrategyTest extends TestCase
{
    private CompositeDetectionStrategy $strategy;

    public function testConstructorWithEmptyStrategies(): void
    {
        $strategy = new CompositeDetectionStrategy();

        $this->assertSame([], $strategy->getStrategies());
    }

    public function testConstructorWithStrategies(): void
    {
        $mockStrategy1 = $this->createMockStrategy('strategy1', priority: 10);
        $mockStrategy2 = $this->createMockStrategy('strategy2', priority: 5);

        $strategy = new CompositeDetectionStrategy([$mockStrategy1, $mockStrategy2]);

        $strategies = $strategy->getStrategies();
        $this->assertCount(2, $strategies);

        // Should be sorted by priority (highest first)
        $this->assertSame('strategy1', $strategies[0]->getName());
        $this->assertSame('strategy2', $strategies[1]->getName());
    }

    public function testAddStrategy(): void
    {
        $mockStrategy = $this->createMockStrategy('test-strategy');

        $this->strategy->addStrategy($mockStrategy);

        $strategies = $this->strategy->getStrategies();
        $this->assertCount(1, $strategies);
        $this->assertSame($mockStrategy, $strategies[0]);
    }

    public function testAddStrategyMaintainsPriorityOrder(): void
    {
        $lowPriorityStrategy = $this->createMockStrategy('low', priority: 1);
        $highPriorityStrategy = $this->createMockStrategy('high', priority: 10);
        $mediumPriorityStrategy = $this->createMockStrategy('medium', priority: 5);

        // Add in random order
        $this->strategy->addStrategy($lowPriorityStrategy);
        $this->strategy->addStrategy($highPriorityStrategy);
        $this->strategy->addStrategy($mediumPriorityStrategy);

        $strategies = $this->strategy->getStrategies();

        // Should be sorted by priority (highest first)
        $this->assertSame('high', $strategies[0]->getName());
        $this->assertSame('medium', $strategies[1]->getName());
        $this->assertSame('low', $strategies[2]->getName());
    }

    public function testDetectReturnsFirstValidResult(): void
    {
        $projectRoot = FSPath::create('/path/to/project');
        $projectMetadata = ['files' => ['composer.json']];

        $template = new Template('test-template', 'Test Template');
        $expectedResult = new TemplateDetectionResult($template, 0.95, 'test-method', []);

        $strategy1 = $this->createMockStrategy('strategy1', priority: 10, threshold: 0.8);
        $strategy1->expects($this->once())
            ->method('detect')
            ->with($projectRoot, $projectMetadata)
            ->willReturn($expectedResult);

        $strategy2 = $this->createMockStrategy('strategy2', priority: 5);
        $strategy2->expects($this->never())->method('detect'); // Should not be called

        $this->strategy->addStrategy($strategy1);
        $this->strategy->addStrategy($strategy2);

        $result = $this->strategy->detect($projectRoot, $projectMetadata);

        $this->assertNotNull($result);
        $this->assertSame($template, $result->template);
        $this->assertSame(0.95, $result->confidence);
        $this->assertSame('test-method', $result->detectionMethod);
        $this->assertArrayHasKey('selectedStrategy', $result->metadata);
        $this->assertArrayHasKey('strategyPriority', $result->metadata);
        $this->assertSame('strategy1', $result->metadata['selectedStrategy']);
        $this->assertSame(10, $result->metadata['strategyPriority']);
    }

    public function testDetectSkipsResultsBelowThreshold(): void
    {
        $projectRoot = FSPath::create('/path/to/project');
        $projectMetadata = ['files' => ['composer.json']];

        $template = new Template('test-template', 'Test Template');
        $lowConfidenceResult = new TemplateDetectionResult($template, 0.5, 'test-method', []);
        $highConfidenceResult = new TemplateDetectionResult($template, 0.95, 'test-method', []);

        $strategy1 = $this->createMockStrategy('strategy1', priority: 10, threshold: 0.8);
        $strategy1->method('detect')->willReturn($lowConfidenceResult); // Below threshold

        $strategy2 = $this->createMockStrategy('strategy2', priority: 5, threshold: 0.8);
        $strategy2->method('detect')->willReturn($highConfidenceResult); // Above threshold

        $this->strategy->addStrategy($strategy1);
        $this->strategy->addStrategy($strategy2);

        $result = $this->strategy->detect($projectRoot, $projectMetadata);

        $this->assertNotNull($result);
        $this->assertSame('strategy2', $result->metadata['selectedStrategy']);
    }

    public function testDetectReturnsNullWhenNoValidResults(): void
    {
        $projectRoot = FSPath::create('/path/to/project');
        $projectMetadata = ['files' => ['composer.json']];

        $strategy1 = $this->createMockStrategy('strategy1');
        $strategy1->method('detect')->willReturn(null);

        $strategy2 = $this->createMockStrategy('strategy2');
        $strategy2->method('detect')->willReturn(null);

        $this->strategy->addStrategy($strategy1);
        $this->strategy->addStrategy($strategy2);

        $result = $this->strategy->detect($projectRoot, $projectMetadata);

        $this->assertNull($result);
    }

    public function testGetAllPossibleResults(): void
    {
        $projectRoot = FSPath::create('/path/to/project');
        $projectMetadata = ['files' => ['composer.json']];

        $template1 = new Template('template1', 'Template 1');
        $template2 = new Template('template2', 'Template 2');

        $result1 = new TemplateDetectionResult($template1, 0.95, 'method1', []);
        $result2 = new TemplateDetectionResult($template2, 0.80, 'method2', []);

        $strategy1 = $this->createMockStrategy('strategy1', priority: 10, threshold: 0.8);
        $strategy1->method('detect')->willReturn($result1);

        $strategy2 = $this->createMockStrategy('strategy2', priority: 5, threshold: 0.7);
        $strategy2->method('detect')->willReturn($result2);

        $strategy3 = $this->createMockStrategy('strategy3', priority: 3, threshold: 0.9);
        $strategy3->method('detect')->willReturn(null);

        $this->strategy->addStrategy($strategy1);
        $this->strategy->addStrategy($strategy2);
        $this->strategy->addStrategy($strategy3);

        $results = $this->strategy->getAllPossibleResults($projectRoot, $projectMetadata);

        $this->assertCount(2, $results);

        // Should be sorted by confidence (highest first)
        $this->assertSame($template1, $results[0]->template);
        $this->assertSame(0.95, $results[0]->confidence);
        $this->assertSame($template2, $results[1]->template);
        $this->assertSame(0.80, $results[1]->confidence);

        // Check metadata
        $this->assertArrayHasKey('strategy', $results[0]->metadata);
        $this->assertArrayHasKey('strategyPriority', $results[0]->metadata);
        $this->assertArrayHasKey('meetsThreshold', $results[0]->metadata);
        $this->assertSame('strategy1', $results[0]->metadata['strategy']);
        $this->assertTrue($results[0]->metadata['meetsThreshold']);
        $this->assertTrue($results[1]->metadata['meetsThreshold']);
    }

    public function testGetConfidenceThresholdWithNoStrategies(): void
    {
        $this->assertSame(0.0, $this->strategy->getConfidenceThreshold());
    }

    public function testGetConfidenceThresholdReturnsMinimum(): void
    {
        $strategy1 = $this->createMockStrategy('strategy1', threshold: 0.8);
        $strategy2 = $this->createMockStrategy('strategy2', threshold: 0.6);
        $strategy3 = $this->createMockStrategy('strategy3', threshold: 0.9);

        $this->strategy->addStrategy($strategy1);
        $this->strategy->addStrategy($strategy2);
        $this->strategy->addStrategy($strategy3);

        $this->assertSame(0.6, $this->strategy->getConfidenceThreshold());
    }

    public function testGetPriority(): void
    {
        $this->assertSame(1000, $this->strategy->getPriority());
    }

    public function testGetName(): void
    {
        $this->assertSame('composite', $this->strategy->getName());
    }

    protected function setUp(): void
    {
        $this->strategy = new CompositeDetectionStrategy();
    }

    private function createMockStrategy(
        string $name,
        int $priority = 0,
        float $threshold = 0.8,
    ): TemplateDetectionStrategy {
        $strategy = $this->createMock(TemplateDetectionStrategy::class);
        $strategy->method('getName')->willReturn($name);
        $strategy->method('getPriority')->willReturn($priority);
        $strategy->method('getConfidenceThreshold')->willReturn($threshold);

        return $strategy;
    }
}
