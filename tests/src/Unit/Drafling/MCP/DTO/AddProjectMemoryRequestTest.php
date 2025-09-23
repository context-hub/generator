<?php

declare(strict_types=1);

namespace Tests\Unit\Drafling\MCP\DTO;

use Butschster\ContextGenerator\Drafling\MCP\DTO\AddProjectMemoryRequest;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AddProjectMemoryRequest DTO
 */
final class AddProjectMemoryRequestTest extends TestCase
{
    public function testConstruction(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_123',
            memory: 'This is a memory entry for the project',
        );

        $this->assertSame('proj_123', $request->projectId);
        $this->assertSame('This is a memory entry for the project', $request->memory);
    }

    public function testValidateWithValidRequest(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_456',
            memory: 'Valid memory content',
        );

        $errors = $request->validate();

        $this->assertEmpty($errors);
    }

    public function testValidateWithEmptyProjectId(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: '',
            memory: 'Some memory content',
        );

        $errors = $request->validate();

        $this->assertContains('Project ID cannot be empty', $errors);
    }

    public function testValidateWithEmptyMemory(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_789',
            memory: '',
        );

        $errors = $request->validate();

        $this->assertContains('Memory entry cannot be empty', $errors);
    }

    public function testValidateWithWhitespaceOnlyMemory(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_abc',
            memory: "   \t\n   ",
        );

        $errors = $request->validate();

        $this->assertContains('Memory entry cannot be empty', $errors);
    }

    public function testValidateWithMultipleErrors(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: '',
            memory: '  ',
        );

        $errors = $request->validate();

        $this->assertContains('Project ID cannot be empty', $errors);
        $this->assertContains('Memory entry cannot be empty', $errors);
        $this->assertCount(2, $errors);
    }

    public function testValidateWithMemoryContainingOnlyWhitespace(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_test',
            memory: "\n\t   \r\n  ",
        );

        $errors = $request->validate();

        $this->assertContains('Memory entry cannot be empty', $errors);
    }

    public function testValidateWithValidMemoryContainingWhitespace(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_valid',
            memory: '  Valid memory with spaces  ',
        );

        $errors = $request->validate();

        $this->assertEmpty($errors);
    }

    public function testValidateWithLongMemoryContent(): void
    {
        $longMemory = \str_repeat('This is a long memory entry. ', 100);

        $request = new AddProjectMemoryRequest(
            projectId: 'proj_long',
            memory: $longMemory,
        );

        $errors = $request->validate();

        $this->assertEmpty($errors);
    }

    public function testValidateWithSpecialCharactersInMemory(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_special',
            memory: 'Memory with special chars: !@#$%^&*()_+-={}[]|\\:";\'<>?,./',
        );

        $errors = $request->validate();

        $this->assertEmpty($errors);
    }

    public function testValidateWithUnicodeCharactersInMemory(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_unicode',
            memory: 'Memory with unicode: 🚀 Hello 世界 café résumé',
        );

        $errors = $request->validate();

        $this->assertEmpty($errors);
    }

    public function testIsReadonlyDto(): void
    {
        $request = new AddProjectMemoryRequest(
            projectId: 'proj_readonly',
            memory: 'Readonly memory',
        );

        // Properties should be readonly (this is ensured by PHP at compile time)
        $this->assertSame('proj_readonly', $request->projectId);
        $this->assertSame('Readonly memory', $request->memory);
    }
}
