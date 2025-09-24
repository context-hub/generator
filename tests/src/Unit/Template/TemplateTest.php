<?php

declare(strict_types=1);

namespace Tests\Unit\Template;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistry;
use Butschster\ContextGenerator\Template\Template;
use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{
    public function testTemplateConstructionWithDefaults(): void
    {
        $template = new Template(
            name: 'test-template',
            description: 'A test template',
        );

        $this->assertSame('test-template', $template->name);
        $this->assertSame('A test template', $template->description);
        $this->assertSame([], $template->tags);
        $this->assertSame(0, $template->priority);
        $this->assertSame([], $template->detectionCriteria);
        $this->assertNull($template->config);
    }

    public function testTemplateConstructionWithAllProperties(): void
    {
        $config = new ConfigRegistry();
        $tags = ['php', 'framework'];
        $criteria = ['files' => ['composer.json']];

        $template = new Template(
            name: 'laravel',
            description: 'Laravel Framework',
            tags: $tags,
            priority: 10,
            detectionCriteria: $criteria,
            config: $config,
        );

        $this->assertSame('laravel', $template->name);
        $this->assertSame('Laravel Framework', $template->description);
        $this->assertSame($tags, $template->tags);
        $this->assertSame(10, $template->priority);
        $this->assertSame($criteria, $template->detectionCriteria);
        $this->assertSame($config, $template->config);
    }

    public function testMatchesReturnsFalseWhenNoCriteria(): void
    {
        $template = new Template(
            name: 'test-template',
            description: 'A test template',
        );

        $metadata = ['files' => ['composer.json']];

        $this->assertEquals(['confidence' => 0.0], $template->matches($metadata));
    }

    public function testMatchesRequiredFiles(): void
    {
        $template = new Template(
            name: 'test-template',
            description: 'A test template',
            detectionCriteria: ['files' => ['composer.json', 'artisan']],
        );

        $metadata = ['files' => ['composer.json', 'artisan', 'package.json']];
        $this->assertEquals([
            'confidence' => 0.6,
            'files' => ['composer.json', 'artisan'],
        ], $template->matches($metadata));

        $metadata = ['files' => ['composer.json']]; // missing artisan
        $this->assertEquals([
            'confidence' => 0.15,
            'files' => ['composer.json'],
        ], $template->matches($metadata));

        $metadata = []; // no files key
        $this->assertEquals([
            'confidence' => 0.0,
        ], $template->matches($metadata));
    }

    public function testMatchesRequiredDirectories(): void
    {
        $template = new Template(
            name: 'test-template',
            description: 'A test template',
            detectionCriteria: ['directories' => ['app', 'config']],
        );

        $metadata = ['directories' => ['app', 'config', 'public']];
        $this->assertEquals([
            'confidence' => 0.4,
            'directories' => ['app', 'config'],
        ], $template->matches($metadata));

        $metadata = ['directories' => ['app']]; // missing config
        $this->assertEquals([
            'confidence' => 0.1,
            'directories' => ['app'],
        ], $template->matches($metadata));

        $metadata = []; // no directories key
        $this->assertEquals([
            'confidence' => 0.0,
        ], $template->matches($metadata));
    }

    public function testMatchesComposerPatterns(): void
    {
        $template = new Template(
            name: 'test-template',
            description: 'A test template',
            detectionCriteria: ['patterns' => ['laravel/framework', 'phpunit/phpunit']],
        );

        $metadata = [
            'composer' => [
                'require' => ['laravel/framework' => '^9.0'],
                'require-dev' => ['phpunit/phpunit' => '^9.0'],
            ],
        ];
        $this->assertEquals([
            'confidence' => 0.0,
        ], $template->matches($metadata));

        $metadata = [
            'composer' => [
                'require' => ['laravel/framework' => '^9.0'],
                // missing phpunit/phpunit
            ],
        ];
        $this->assertEquals([
            'confidence' => 0.0,
        ], $template->matches($metadata));

        $metadata = []; // no composer key
        $this->assertEquals([
            'confidence' => 0.0,
        ], $template->matches($metadata));
    }

    public function testMatchesComposerPatternsInRequireDev(): void
    {
        $template = new Template(
            name: 'test-template',
            description: 'A test template',
            detectionCriteria: ['patterns' => ['phpunit/phpunit']],
        );

        $metadata = [
            'composer' => [
                'require-dev' => ['phpunit/phpunit' => '^9.0'],
            ],
        ];
        $this->assertEquals([
            'confidence' => 0.0,
        ], $template->matches($metadata));
    }

    public function testMatchesAllCriteria(): void
    {
        $template = new Template(
            name: 'laravel-template',
            description: 'Laravel Framework Template',
            detectionCriteria: [
                'files' => ['composer.json', 'artisan'],
                'directories' => ['app', 'config'],
                'patterns' => ['laravel/framework'],
            ],
        );

        $metadata = [
            'files' => ['composer.json', 'artisan'],
            'directories' => ['app', 'config', 'public'],
            'composer' => [
                'require' => ['laravel/framework' => '^9.0'],
            ],
        ];
        $this->assertEquals([
            'confidence' => 0.8,
            'files' => ['composer.json', 'artisan'],
            'directories' => ['app', 'config'],
        ], $template->matches($metadata));

        // Missing one file
        $metadata['files'] = ['composer.json'];
        $this->assertEquals([
            'confidence' => 0.42,
            'files' => ['composer.json'],
            'directories' => ['app', 'config'],
        ], $template->matches($metadata));
    }

    public function testJsonSerialize(): void
    {
        $config = new ConfigRegistry();
        $template = new Template(
            name: 'test-template',
            description: 'A test template',
            tags: ['php', 'framework'],
            priority: 5,
            detectionCriteria: ['files' => ['composer.json']],
            config: $config,
        );

        $expected = [
            'name' => 'test-template',
            'description' => 'A test template',
            'tags' => ['php', 'framework'],
            'priority' => 5,
            'detectionCriteria' => ['files' => ['composer.json']],
            'config' => $config,
        ];

        $this->assertEquals($expected, $template->jsonSerialize());
    }

    public function testJsonSerializeWithNullConfig(): void
    {
        $template = new Template(
            name: 'test-template',
            description: 'A test template',
        );

        $expected = [
            'name' => 'test-template',
            'description' => 'A test template',
            'tags' => [],
            'priority' => 0,
            'detectionCriteria' => [],
            'config' => null,
        ];

        $this->assertEquals($expected, $template->jsonSerialize());
    }
}
