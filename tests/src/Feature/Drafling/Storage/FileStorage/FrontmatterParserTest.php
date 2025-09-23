<?php

declare(strict_types=1);

namespace Tests\Feature\Drafling\Storage\FileStorage;

use Butschster\ContextGenerator\Drafling\Storage\FileStorage\FrontmatterParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FrontmatterParserTest extends TestCase
{
    private FrontmatterParser $parser;

    #[Test]
    public function it_parses_content_with_frontmatter(): void
    {
        $content = <<<'MARKDOWN'
---
title: "Test Entry"
description: "A test entry"
status: "draft"
tags: ["test", "example"]
created_at: "2023-01-01T10:00:00Z"
---

# Test Entry Content

This is the main content of the entry.

## Section 1

Some more content here.
MARKDOWN;

        $result = $this->parser->parse($content);

        $this->assertArrayHasKey('frontmatter', $result);
        $this->assertArrayHasKey('content', $result);

        $frontmatter = $result['frontmatter'];
        $this->assertEquals('Test Entry', $frontmatter['title']);
        $this->assertEquals('A test entry', $frontmatter['description']);
        $this->assertEquals('draft', $frontmatter['status']);
        $this->assertEquals(['test', 'example'], $frontmatter['tags']);
        $this->assertEquals('2023-01-01T10:00:00Z', $frontmatter['created_at']);

        $expectedContent = "# Test Entry Content\n\nThis is the main content of the entry.\n\n## Section 1\n\nSome more content here.";
        $this->assertEquals($expectedContent, $result['content']);
    }

    #[Test]
    public function it_parses_content_without_frontmatter(): void
    {
        $content = <<<'MARKDOWN'
# Regular Markdown

This is just regular markdown content without frontmatter.

## Section

More content here.
MARKDOWN;

        $result = $this->parser->parse($content);

        $this->assertArrayHasKey('frontmatter', $result);
        $this->assertArrayHasKey('content', $result);

        $this->assertEmpty($result['frontmatter']);
        $this->assertEquals($content, $result['content']);
    }

    #[Test]
    public function it_handles_empty_frontmatter(): void
    {
        $content = <<<'MARKDOWN'
---
---

# Content Only

This has empty frontmatter.
MARKDOWN;

        $result = $this->parser->parse($content);

        $this->assertEmpty($result['frontmatter']);
        $this->assertEquals("# Content Only\n\nThis has empty frontmatter.", $result['content']);
    }

    #[Test]
    public function it_handles_complex_yaml_frontmatter(): void
    {
        $content = <<<'MARKDOWN'
---
entry_info:
  id: "entry_123"
  type: "user_story"
metadata:
  author: "John Doe"
  version: 1.2
  published: true
nested:
  - item1: "value1"
  - item2: "value2"
tags:
  - "complex"
  - "yaml"
  - "nested"
---

# Complex Entry

This entry has complex YAML frontmatter.
MARKDOWN;

        $result = $this->parser->parse($content);

        $frontmatter = $result['frontmatter'];
        $this->assertEquals('entry_123', $frontmatter['entry_info']['id']);
        $this->assertEquals('user_story', $frontmatter['entry_info']['type']);
        $this->assertEquals('John Doe', $frontmatter['metadata']['author']);
        $this->assertEquals(1.2, $frontmatter['metadata']['version']);
        $this->assertTrue($frontmatter['metadata']['published']);
        $this->assertCount(2, $frontmatter['nested']);
        $this->assertEquals(['complex', 'yaml', 'nested'], $frontmatter['tags']);
    }

    #[Test]
    public function it_combines_frontmatter_and_content(): void
    {
        $frontmatter = [
            'title' => 'Combined Entry',
            'description' => 'Testing combine functionality',
            'status' => 'draft',
            'tags' => ['test', 'combine'],
        ];

        $content = "# Combined Entry\n\nThis content was combined with frontmatter.";

        $result = $this->parser->combine($frontmatter, $content);

        $expectedOutput = <<<'MARKDOWN'
---
title: 'Combined Entry'
description: 'Testing combine functionality'
status: draft
tags:
  - test
  - combine
---
# Combined Entry

This content was combined with frontmatter.
MARKDOWN;

        $this->assertEquals($expectedOutput, $result);
    }

    #[Test]
    public function it_combines_empty_frontmatter_with_content(): void
    {
        $frontmatter = [];
        $content = "# Content Only\n\nJust content, no frontmatter.";

        $result = $this->parser->combine($frontmatter, $content);

        $this->assertEquals($content, $result);
    }

    #[Test]
    public function it_extracts_frontmatter_only(): void
    {
        $content = <<<'MARKDOWN'
---
title: "Extract Test"
status: "published"
priority: 5
---

# Content that should be ignored

This content is not extracted.
MARKDOWN;

        $frontmatter = $this->parser->extractFrontmatter($content);

        $this->assertEquals('Extract Test', $frontmatter['title']);
        $this->assertEquals('published', $frontmatter['status']);
        $this->assertEquals(5, $frontmatter['priority']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_yaml(): void
    {
        $content = <<<'MARKDOWN'
---
title: "Invalid YAML"
invalid_yaml: [unclosed array
status: "draft"
---

# Content
MARKDOWN;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse YAML frontmatter');

        $this->parser->parse($content);
    }

    #[Test]
    public function it_handles_frontmatter_with_special_characters(): void
    {
        $content = <<<'MARKDOWN'
---
title: "Entry with 'quotes' and \"double quotes\""
description: "Contains: colons, semicolons; and other punctuation!"
special_chars: "αβγ 中文 العربية 🚀"
---

# Special Characters Test

Content with special characters.
MARKDOWN;

        $result = $this->parser->parse($content);

        $frontmatter = $result['frontmatter'];
        $this->assertEquals("Entry with 'quotes' and \"double quotes\"", $frontmatter['title']);
        $this->assertEquals("Contains: colons, semicolons; and other punctuation!", $frontmatter['description']);
        $this->assertEquals("αβγ 中文 العربية 🚀", $frontmatter['special_chars']);
    }

    #[Test]
    public function it_handles_multiline_yaml_values(): void
    {
        $content = <<<'MARKDOWN'
---
title: "Multiline Test"
description: |
  This is a multiline description
  that spans multiple lines
  and preserves formatting.
notes: >
  This is a folded
  multiline string that
  will be on one line.
---

# Multiline Content

Test content.
MARKDOWN;

        $result = $this->parser->parse($content);

        $frontmatter = $result['frontmatter'];
        $this->assertEquals('Multiline Test', $frontmatter['title']);
        $this->assertStringContainsString("This is a multiline description\nthat spans multiple lines", $frontmatter['description']);
        $this->assertStringContainsString('This is a folded multiline string', $frontmatter['notes']);
    }

    #[Test]
    public function it_preserves_content_formatting(): void
    {
        $content = <<<'MARKDOWN'
---
title: "Formatting Test"
---

# Main Title

This is a paragraph with **bold** and *italic* text.

## Code Block

```php
<?php
function test() {
    return "hello world";
}
```

- List item 1
- List item 2
  - Nested item

> This is a blockquote
> with multiple lines.
MARKDOWN;

        $result = $this->parser->parse($content);

        $expectedContent = <<<'CONTENT'
# Main Title

This is a paragraph with **bold** and *italic* text.

## Code Block

```php
<?php
function test() {
    return "hello world";
}
```

- List item 1
- List item 2
  - Nested item

> This is a blockquote
> with multiple lines.
CONTENT;

        $this->assertEquals($expectedContent, $result['content']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FrontmatterParser();
    }
}
