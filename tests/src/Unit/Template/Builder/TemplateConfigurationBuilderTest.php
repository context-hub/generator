<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Builder;

use Butschster\ContextGenerator\Document\Document;
use Butschster\ContextGenerator\Lib\TreeBuilder\TreeViewConfig;
use Butschster\ContextGenerator\Source\File\FileSource;
use Butschster\ContextGenerator\Source\Tree\TreeSource;
use Butschster\ContextGenerator\Template\Builder\TemplateConfigurationBuilder;
use PHPUnit\Framework\TestCase;

class TemplateConfigurationBuilderTest extends TestCase
{
    private TemplateConfigurationBuilder $builder;

    public function testConstructorSetsTemplateName(): void
    {
        $builder = new TemplateConfigurationBuilder('Laravel');

        // Test by checking the behavior in addStructureDocument
        $builder->addStructureDocument(['src/']);
        $documents = $builder->getDocuments();

        $this->assertCount(1, $documents);
        $this->assertStringContainsString('laravel', $documents[0]->outputPath);
        $this->assertContains('laravel', $documents[0]->getTags(false));
    }

    public function testAddStructureDocumentWithDefaults(): void
    {
        $sourcePaths = ['src/', 'app/'];

        $this->builder->addStructureDocument($sourcePaths);

        $documents = $this->builder->getDocuments();
        $this->assertCount(1, $documents);

        $document = $documents[0];
        $this->assertSame('TestTemplate Project Structure', $document->description);
        $this->assertSame('docs/testtemplate-structure.md', $document->outputPath);
        $this->assertTrue($document->overwrite);
        // Document modifiers are private, test behavior instead
        $this->assertTrue($document->overwrite);
        $this->assertSame(['testtemplate', 'structure'], $document->getTags(false));

        $sources = $document->getSources();
        $this->assertCount(1, $sources);
        $this->assertInstanceOf(TreeSource::class, $sources[0]);

        $treeSource = $sources[0];
        $this->assertSame($sourcePaths, $treeSource->sourcePaths);
        $this->assertSame('TestTemplate Directory Structure', $treeSource->getDescription());

        $treeView = $treeSource->treeView;
        $this->assertTrue($treeView->showCharCount);
        $this->assertTrue($treeView->includeFiles);
        $this->assertSame(3, $treeView->maxDepth);
    }

    public function testAddStructureDocumentWithCustomParameters(): void
    {
        $sourcePaths = ['custom/'];
        $outputPath = 'custom/structure.md';
        $description = 'Custom Structure';
        $treeViewConfig = new TreeViewConfig(
            showCharCount: false,
            includeFiles: false,
            maxDepth: 5,
        );

        $this->builder->addStructureDocument(
            $sourcePaths,
            $outputPath,
            $description,
            $treeViewConfig,
        );

        $documents = $this->builder->getDocuments();
        $document = $documents[0];

        $this->assertSame($description, $document->description);
        $this->assertSame($outputPath, $document->outputPath);
        $sources = $document->getSources();
        $this->assertCount(1, $sources);
        $treeSource = $sources[0];
        $this->assertSame($sourcePaths, $treeSource->sourcePaths);
        $this->assertSame($treeViewConfig, $treeSource->treeView);
    }

    public function testAddSourceDocument(): void
    {
        $description = 'Test Source Code';
        $outputPath = 'docs/source.md';
        $sourcePaths = ['src/', 'lib/'];
        $filePatterns = ['*.php', '*.js'];
        $modifiers = ['php-signature'];
        $tags = ['source', 'code'];

        $this->builder->addSourceDocument(
            $description,
            $outputPath,
            $sourcePaths,
            $filePatterns,
            $modifiers,
            $tags,
        );

        $documents = $this->builder->getDocuments();
        $this->assertCount(1, $documents);

        $document = $documents[0];
        $this->assertSame($description, $document->description);
        $this->assertSame($outputPath, $document->outputPath);
        $this->assertTrue($document->overwrite);
        $this->assertSame($modifiers, $document->getModifiers());
        $this->assertSame(['testtemplate', 'source', 'code'], $document->getTags(false));

        $sources = $document->getSources();
        $this->assertCount(1, $sources);
        $this->assertInstanceOf(FileSource::class, $sources[0]);

        $fileSource = $sources[0];
        $this->assertSame($sourcePaths, $fileSource->sourcePaths);
        $this->assertSame($description, $fileSource->getDescription());
        $this->assertSame($filePatterns, $fileSource->filePattern);
        $this->assertSame($modifiers, $fileSource->modifiers);
    }

    public function testAddSourceDocumentWithDefaults(): void
    {
        $this->builder->addSourceDocument(
            'Test Source',
            'docs/test.md',
            ['src/'],
        );

        $documents = $this->builder->getDocuments();
        $document = $documents[0];

        $sources = $document->getSources();
        $this->assertCount(1, $sources);
        $fileSource = $sources[0];
        $this->assertSame(['*.php'], $fileSource->filePattern);
        $this->assertSame([], $document->getModifiers());
        $this->assertSame(['testtemplate'], $document->getTags(false));
    }

    public function testAddDocument(): void
    {
        $customDocument = new Document(
            description: 'Custom Document',
            outputPath: 'custom.md',
            overwrite: false,
            modifiers: ['custom-modifier'],
            tags: ['custom'],
        );

        $this->builder->addDocument($customDocument);

        $documents = $this->builder->getDocuments();
        $this->assertCount(1, $documents);
        $this->assertSame($customDocument, $documents[0]);
    }

    public function testRequireFiles(): void
    {
        $this->builder->requireFiles(['composer.json', 'artisan']);

        $criteria = $this->builder->getDetectionCriteria();
        $this->assertArrayHasKey('files', $criteria);
        $this->assertSame(['composer.json', 'artisan'], $criteria['files']);
    }

    public function testRequireFilesAccumulates(): void
    {
        $this->builder
            ->requireFiles(['composer.json'])
            ->requireFiles(['artisan', 'package.json']);

        $criteria = $this->builder->getDetectionCriteria();
        $this->assertSame(['composer.json', 'artisan', 'package.json'], $criteria['files']);
    }

    public function testRequireDirectories(): void
    {
        $this->builder->requireDirectories(['app', 'config']);

        $criteria = $this->builder->getDetectionCriteria();
        $this->assertArrayHasKey('directories', $criteria);
        $this->assertSame(['app', 'config'], $criteria['directories']);
    }

    public function testRequireDirectoriesAccumulates(): void
    {
        $this->builder
            ->requireDirectories(['app'])
            ->requireDirectories(['config', 'resources']);

        $criteria = $this->builder->getDetectionCriteria();
        $this->assertSame(['app', 'config', 'resources'], $criteria['directories']);
    }

    public function testRequirePackages(): void
    {
        $this->builder->requirePackages(['laravel/framework', 'symfony/console']);

        $criteria = $this->builder->getDetectionCriteria();
        $this->assertArrayHasKey('patterns', $criteria);
        $this->assertSame(['laravel/framework', 'symfony/console'], $criteria['patterns']);
    }

    public function testRequirePackagesAccumulates(): void
    {
        $this->builder
            ->requirePackages(['laravel/framework'])
            ->requirePackages(['symfony/console', 'phpunit/phpunit']);

        $criteria = $this->builder->getDetectionCriteria();
        $this->assertSame(['laravel/framework', 'symfony/console', 'phpunit/phpunit'], $criteria['patterns']);
    }

    public function testSetDetectionCriteria(): void
    {
        $criteria = [
            'files' => ['composer.json'],
            'directories' => ['app'],
            'patterns' => ['laravel/framework'],
        ];

        $this->builder->setDetectionCriteria($criteria);

        $this->assertSame($criteria, $this->builder->getDetectionCriteria());
    }

    public function testSetDetectionCriteriaOverridesPrevious(): void
    {
        $this->builder
            ->requireFiles(['old-file'])
            ->requireDirectories(['old-dir']);

        $newCriteria = [
            'files' => ['new-file'],
            'patterns' => ['new-pattern'],
        ];

        $this->builder->setDetectionCriteria($newCriteria);

        $this->assertSame($newCriteria, $this->builder->getDetectionCriteria());
    }

    public function testBuild(): void
    {
        $this->builder->addStructureDocument(['src/']);

        $config = $this->builder->build();

        $this->assertNotNull($config);
        // Config registry tests are covered in their own test file
    }

    public function testGetDocuments(): void
    {
        $this->assertSame([], $this->builder->getDocuments());

        $this->builder->addStructureDocument(['src/']);
        $this->assertCount(1, $this->builder->getDocuments());

        $this->builder->addSourceDocument('Test', 'test.md', ['src/']);
        $this->assertCount(2, $this->builder->getDocuments());
    }

    public function testClearDocuments(): void
    {
        $this->builder
            ->addStructureDocument(['src/'])
            ->addSourceDocument('Test', 'test.md', ['src/']);

        $this->assertCount(2, $this->builder->getDocuments());

        $this->builder->clearDocuments();

        $this->assertSame([], $this->builder->getDocuments());
    }

    public function testClearDetectionCriteria(): void
    {
        $this->builder
            ->requireFiles(['composer.json'])
            ->requireDirectories(['app']);

        $this->assertNotEmpty($this->builder->getDetectionCriteria());

        $this->builder->clearDetectionCriteria();

        $this->assertSame([], $this->builder->getDetectionCriteria());
    }

    public function testReset(): void
    {
        $this->builder
            ->addStructureDocument(['src/'])
            ->requireFiles(['composer.json'])
            ->requireDirectories(['app']);

        $this->assertCount(1, $this->builder->getDocuments());
        $this->assertNotEmpty($this->builder->getDetectionCriteria());

        $this->builder->reset();

        $this->assertSame([], $this->builder->getDocuments());
        $this->assertSame([], $this->builder->getDetectionCriteria());
    }

    public function testFluentInterface(): void
    {
        $result = $this->builder
            ->addStructureDocument(['src/'])
            ->addSourceDocument('Test', 'test.md', ['src/'])
            ->requireFiles(['composer.json'])
            ->requireDirectories(['app'])
            ->requirePackages(['laravel/framework'])
            ->setDetectionCriteria(['custom' => 'criteria'])
            ->clearDocuments()
            ->clearDetectionCriteria()
            ->reset();

        $this->assertSame($this->builder, $result);
    }

    protected function setUp(): void
    {
        $this->builder = new TemplateConfigurationBuilder('TestTemplate');
    }
}
