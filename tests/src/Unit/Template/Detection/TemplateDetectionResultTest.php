<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Detection;

use Butschster\ContextGenerator\Template\Detection\TemplateDetectionResult;
use Butschster\ContextGenerator\Template\Template;
use PHPUnit\Framework\TestCase;

class TemplateDetectionResultTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $template = new Template('test-template', 'Test Template');
        $metadata = ['key' => 'value'];

        $result = new TemplateDetectionResult(
            template: $template,
            confidence: 0.95,
            detectionMethod: 'template_criteria',
            metadata: $metadata,
        );

        $this->assertSame($template, $result->template);
        $this->assertSame(0.95, $result->confidence);
        $this->assertSame('template_criteria', $result->detectionMethod);
        $this->assertSame($metadata, $result->metadata);
    }

    public function testConstructorWithNullTemplate(): void
    {
        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.0,
            detectionMethod: 'none',
        );

        $this->assertNull($result->template);
        $this->assertSame(0.0, $result->confidence);
        $this->assertSame('none', $result->detectionMethod);
        $this->assertSame([], $result->metadata);
    }

    public function testHasHighConfidenceReturnsTrueForHighConfidence(): void
    {
        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.8,
            detectionMethod: 'test',
        );

        $this->assertTrue($result->hasHighConfidence());

        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.95,
            detectionMethod: 'test',
        );

        $this->assertTrue($result->hasHighConfidence());
    }

    public function testHasHighConfidenceReturnsFalseForLowConfidence(): void
    {
        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.79,
            detectionMethod: 'test',
        );

        $this->assertFalse($result->hasHighConfidence());

        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.5,
            detectionMethod: 'test',
        );

        $this->assertFalse($result->hasHighConfidence());
    }

    public function testMeetsTemplateDetectionThresholdReturnsTrueForVeryHighConfidence(): void
    {
        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.91,
            detectionMethod: 'test',
        );

        $this->assertTrue($result->meetsTemplateDetectionThreshold());

        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.95,
            detectionMethod: 'test',
        );

        $this->assertTrue($result->meetsTemplateDetectionThreshold());
    }

    public function testMeetsTemplateDetectionThresholdReturnsFalseForLowerConfidence(): void
    {
        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.9,
            detectionMethod: 'test',
        );

        $this->assertFalse($result->meetsTemplateDetectionThreshold());

        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.8,
            detectionMethod: 'test',
        );

        $this->assertFalse($result->meetsTemplateDetectionThreshold());
    }

    public function testHasTemplateReturnsTrueWhenTemplateExists(): void
    {
        $template = new Template('test-template', 'Test Template');
        $result = new TemplateDetectionResult(
            template: $template,
            confidence: 0.95,
            detectionMethod: 'test',
        );

        $this->assertTrue($result->hasTemplate());
    }

    public function testHasTemplateReturnsFalseWhenTemplateIsNull(): void
    {
        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.0,
            detectionMethod: 'test',
        );

        $this->assertFalse($result->hasTemplate());
    }

    public function testGetDetectionMethodDescriptionReturnsCorrectDescriptions(): void
    {
        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.95,
            detectionMethod: 'template_criteria',
        );

        $this->assertSame('Template Detection Criteria', $result->getDetectionMethodDescription());

        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.95,
            detectionMethod: 'analyzer',
        );

        $this->assertSame('Project Analysis', $result->getDetectionMethodDescription());

        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.95,
            detectionMethod: 'unknown_method',
        );

        $this->assertSame('Unknown', $result->getDetectionMethodDescription());
    }

    public function testIsHighConfidenceTemplateDetectionReturnsTrueForValidConditions(): void
    {
        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.95,
            detectionMethod: 'template_criteria',
        );

        $this->assertTrue($result->isHighConfidenceTemplateDetection());
    }

    public function testIsHighConfidenceTemplateDetectionReturnsFalseForWrongMethod(): void
    {
        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.95,
            detectionMethod: 'analyzer',
        );

        $this->assertFalse($result->isHighConfidenceTemplateDetection());
    }

    public function testIsHighConfidenceTemplateDetectionReturnsFalseForLowConfidence(): void
    {
        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.8,
            detectionMethod: 'template_criteria',
        );

        $this->assertFalse($result->isHighConfidenceTemplateDetection());
    }

    public function testIsHighConfidenceTemplateDetectionReturnsFalseForBothConditionsFailing(): void
    {
        $result = new TemplateDetectionResult(
            template: null,
            confidence: 0.8,
            detectionMethod: 'analyzer',
        );

        $this->assertFalse($result->isHighConfidenceTemplateDetection());
    }
}
