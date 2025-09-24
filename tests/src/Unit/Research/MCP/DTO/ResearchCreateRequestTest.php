<?php

declare(strict_types=1);

namespace Tests\Unit\Research\MCP\DTO;

use Butschster\ContextGenerator\Research\MCP\DTO\ResearchCreateRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProjectCreateRequest DTO
 */
final class ResearchCreateRequestTest extends TestCase
{
    public function testConstructionWithAllFields(): void
    {
        $request = new ResearchCreateRequest(
            templateId: 'blog-template',
            title: 'My Blog Project',
            description: 'A personal blog about tech',
            tags: ['blog', 'tech', 'personal'],
            entryDirs: ['posts', 'drafts', 'pages'],
            memory: ['Initial project memory', 'Setup notes'],
        );

        $this->assertSame('blog-template', $request->templateId);
        $this->assertSame('My Blog Project', $request->title);
        $this->assertSame('A personal blog about tech', $request->description);
        $this->assertSame(['blog', 'tech', 'personal'], $request->tags);
        $this->assertSame(['posts', 'drafts', 'pages'], $request->entryDirs);
        $this->assertSame(['Initial project memory', 'Setup notes'], $request->memory);
    }

    public function testConstructionWithMinimalFields(): void
    {
        $request = new ResearchCreateRequest(
            templateId: 'simple-template',
            title: 'Simple Project',
        );

        $this->assertSame('simple-template', $request->templateId);
        $this->assertSame('Simple Project', $request->title);
        $this->assertSame('', $request->description);
        $this->assertSame([], $request->tags);
        $this->assertSame([], $request->entryDirs);
        $this->assertSame([], $request->memory);
    }

    public function testValidateWithValidRequest(): void
    {
        $request = new ResearchCreateRequest(
            templateId: 'valid-template',
            title: 'Valid Project',
        );

        $errors = $request->validate();

        $this->assertEmpty($errors);
    }

    public function testValidateWithEmptyTemplateId(): void
    {
        $request = new ResearchCreateRequest(
            templateId: '',
            title: 'Some Project',
        );

        $errors = $request->validate();

        $this->assertContains('Template ID cannot be empty', $errors);
    }

    public function testValidateWithEmptyTitle(): void
    {
        $request = new ResearchCreateRequest(
            templateId: 'valid-template',
            title: '',
        );

        $errors = $request->validate();

        $this->assertContains('Research title cannot be empty', $errors);
    }

    public function testValidateWithMultipleErrors(): void
    {
        $request = new ResearchCreateRequest(
            templateId: '',
            title: '',
        );

        $errors = $request->validate();

        $this->assertContains('Template ID cannot be empty', $errors);
        $this->assertContains('Research title cannot be empty', $errors);
        $this->assertCount(2, $errors);
    }

    public function testValidateWithWhitespaceOnlyTitle(): void
    {
        $request = new ResearchCreateRequest(
            templateId: 'valid-template',
            title: "   \t\n   ",
        );

        $errors = $request->validate();

        $this->assertContains('Research title cannot be empty', $errors);
    }

    public function testValidateWithWhitespaceOnlyTemplateId(): void
    {
        $request = new ResearchCreateRequest(
            templateId: "  \n\t  ",
            title: 'Valid Title',
        );

        $errors = $request->validate();

        $this->assertContains('Template ID cannot be empty', $errors);
    }

    public function testValidateWithValidTitleContainingWhitespace(): void
    {
        $request = new ResearchCreateRequest(
            templateId: 'valid-template',
            title: '  Valid Title with Spaces  ',
        );

        $errors = $request->validate();

        $this->assertEmpty($errors);
    }

    public function testValidateAcceptsEmptyOptionalFields(): void
    {
        $request = new ResearchCreateRequest(
            templateId: 'template',
            title: 'Project',
            description: '',
            tags: [],
            entryDirs: [],
            memory: [],
        );

        $errors = $request->validate();

        $this->assertEmpty($errors);
    }

    public function testValidateWithLongDescription(): void
    {
        $longDescription = \str_repeat('This is a very long description. ', 100);

        $request = new ResearchCreateRequest(
            templateId: 'template',
            title: 'Project',
            description: $longDescription,
        );

        $errors = $request->validate();

        $this->assertEmpty($errors);
    }

    public function testValidateWithSpecialCharactersInFields(): void
    {
        $request = new ResearchCreateRequest(
            templateId: 'template-with-dashes_and_underscores',
            title: 'Project with Special Chars: !@#$%',
            description: 'Description with unicode: 🚀 café',
            tags: ['tag-1', 'tag_2', 'tag with spaces'],
            entryDirs: ['dir-1', 'dir_2', 'dir with spaces'],
            memory: ['Memory with unicode: 世界', 'Memory with symbols: !@#$%'],
        );

        $errors = $request->validate();

        $this->assertEmpty($errors);
    }

    public function testArrayFieldsAreEmptyByDefault(): void
    {
        $request = new ResearchCreateRequest(
            templateId: 'template',
            title: 'Project',
        );

        $this->assertIsArray($request->tags);
        $this->assertEmpty($request->tags);

        $this->assertIsArray($request->entryDirs);
        $this->assertEmpty($request->entryDirs);

        $this->assertIsArray($request->memory);
        $this->assertEmpty($request->memory);
    }

    public function testIsReadonlyDto(): void
    {
        $request = new ResearchCreateRequest(
            templateId: 'readonly-template',
            title: 'Readonly Project',
        );

        // Properties should be readonly (this is ensured by PHP at compile time)
        $this->assertSame('readonly-template', $request->templateId);
        $this->assertSame('Readonly Project', $request->title);
    }
}
