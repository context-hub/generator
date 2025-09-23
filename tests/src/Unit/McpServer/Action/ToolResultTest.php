<?php

declare(strict_types=1);

namespace Tests\Unit\McpServer\Action;

use Butschster\ContextGenerator\McpServer\Action\ToolResult;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ToolResultTest extends TestCase
{
    #[Test]
    public function success_creates_successful_result_with_array_data(): void
    {
        // Arrange
        $data = [
            'success' => true,
            'message' => 'Operation completed',
            'id' => 123,
        ];

        // Act
        $result = ToolResult::success($data);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $decodedContent = \json_decode($content->text, true);
        $this->assertEquals($data, $decodedContent);
    }

    #[Test]
    public function success_creates_successful_result_with_json_serializable_object(): void
    {
        // Arrange
        $data = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return [
                    'id' => 456,
                    'name' => 'Test Object',
                    'active' => true,
                ];
            }
        };

        // Act
        $result = ToolResult::success($data);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $decodedContent = \json_decode($content->text, true);
        $this->assertEquals([
            'id' => 456,
            'name' => 'Test Object',
            'active' => true,
        ], $decodedContent);
    }

    #[Test]
    public function error_creates_error_result_with_message(): void
    {
        // Arrange
        $errorMessage = 'Something went wrong';

        // Act
        $result = ToolResult::error($errorMessage);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $decodedContent = \json_decode($content->text, true);
        $this->assertEquals([
            'success' => false,
            'error' => $errorMessage,
        ], $decodedContent);
    }

    #[Test]
    public function error_handles_empty_error_message(): void
    {
        // Act
        $result = ToolResult::error('');

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $decodedContent = \json_decode($content->text, true);
        $this->assertEquals([
            'success' => false,
            'error' => '',
        ], $decodedContent);
    }

    #[Test]
    public function validation_error_creates_error_result_with_details(): void
    {
        // Arrange
        $validationErrors = [
            'field1' => ['Field is required'],
            'field2' => ['Must be at least 3 characters', 'Contains invalid characters'],
        ];

        // Act
        $result = ToolResult::validationError($validationErrors);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);

        $decodedContent = \json_decode($content->text, true);
        $this->assertEquals([
            'success' => false,
            'error' => 'Validation failed',
            'details' => $validationErrors,
        ], $decodedContent);
    }

    #[Test]
    public function validation_error_handles_empty_validation_errors(): void
    {
        // Act
        $result = ToolResult::validationError([]);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $decodedContent = \json_decode($content->text, true);
        $this->assertEquals([
            'success' => false,
            'error' => 'Validation failed',
            'details' => [],
        ], $decodedContent);
    }

    #[Test]
    public function text_creates_result_with_plain_text(): void
    {
        // Arrange
        $textContent = 'This is plain text content\nwith multiple lines\nand special characters: !@#$%';

        // Act
        $result = ToolResult::text($textContent);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $this->assertCount(1, $result->content);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertEquals($textContent, $content->text);
    }

    #[Test]
    public function text_handles_empty_string(): void
    {
        // Act
        $result = ToolResult::text('');

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);

        $content = $result->content[0];
        $this->assertInstanceOf(TextContent::class, $content);
        $this->assertEquals('', $content->text);
    }

    #[Test]
    public function text_handles_unicode_characters(): void
    {
        // Arrange
        $unicodeText = 'Unicode: 🚀 αβγ 中文 العربية';

        // Act
        $result = ToolResult::text($unicodeText);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);

        $content = $result->content[0];
        $this->assertEquals($unicodeText, $content->text);
    }

    #[Test]
    public function success_handles_nested_array_data(): void
    {
        // Arrange
        $complexData = [
            'user' => [
                'id' => 1,
                'profile' => [
                    'name' => 'John Doe',
                    'settings' => [
                        'theme' => 'dark',
                        'notifications' => true,
                    ],
                ],
            ],
            'metadata' => [
                'created_at' => '2023-01-01T00:00:00Z',
                'tags' => ['admin', 'user'],
            ],
        ];

        // Act
        $result = ToolResult::success($complexData);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertFalse($result->isError);

        $content = $result->content[0];
        $decodedContent = \json_decode($content->text, true);
        $this->assertEquals($complexData, $decodedContent);
    }

    #[Test]
    public function error_handles_special_characters_in_message(): void
    {
        // Arrange
        $errorWithSpecialChars = 'Error: "file.txt" not found at path /home/user\'s folder';

        // Act
        $result = ToolResult::error($errorWithSpecialChars);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertTrue($result->isError);

        $content = $result->content[0];
        $decodedContent = \json_decode($content->text, true);
        $this->assertEquals([
            'success' => false,
            'error' => $errorWithSpecialChars,
        ], $decodedContent);
    }
}