# Console Output Service Guidelines

This document provides comprehensive guidelines for using the Console Output Service in the Context Generator
application, with examples of how each method renders in the console.

## Table of Contents

- [Introduction](#introduction)
- [Basic Usage](#basic-usage)
- [OutputService Methods](#outputservice-methods)
    - [Basic Output](#basic-output)
    - [Formatting Methods](#formatting-methods)
    - [Layout Methods](#layout-methods)
- [Specialized Renderers](#specialized-renderers)
    - [TableRenderer](#tablerenderer)
    - [StatusRenderer](#statusrenderer)
    - [ListRenderer](#listrenderer)
    - [SummaryRenderer](#summaryrenderer)
- [Usage Examples](#usage-examples)
- [Best Practices](#best-practices)

## Introduction

The OutputService is a comprehensive system for standardizing CLI output across all commands in the Context Generator
application. It provides consistent formatting, styling, and organization of console output to improve user experience
and maintainability.

### Components

The system consists of several components:

- **OutputService**: Primary implementation that handles basic output operations
- **OutputServiceWithRenderers**: Extended service with access to specialized renderers
- **Specialized Renderers**: Table, Status, List, and Summary renderers for specific output needs
- **Style System**: Defines styling constants and methods for consistent visual appearance

## Basic Usage

The easiest way to use the `OutputService` is to extend `BaseCommand`:

```php
use Butschster\ContextGenerator\Console\BaseCommand;

final class MyCommand extends BaseCommand
{
    public function __invoke(): int
    {
        // Display a title
        $this->outputService->title('My Command');
        
        // Display messages
        $this->outputService->success('Operation completed successfully');
        $this->outputService->error('Something went wrong');
        
        // Display key-value pairs
        $this->outputService->keyValue('Files Processed', '42');
        
        return Command::SUCCESS;
    }
}
```

## OutputService Methods

### Basic Output

#### `title(string $title): void`

Displays a prominent title block at the top of the command output.

**Parameters:**

- `$title`: The title text to display

**Example:**

```php
$outputService->title('Generate Context');
```

**Output:**

```
 [Generate Context]                                                      
 ==============================================================
```

#### `section(string $title): void`

Displays a section header to organize command output into logical sections.

**Parameters:**

- `$title`: The section title text to display

**Example:**

```php
$outputService->section('Configuration Files');
```

**Output:**

```
 [Configuration Files]                                    
 ----------------------------------------
```

#### `success(string $message): void`

Displays a success message with appropriate styling.

**Parameters:**

- `$message`: The success message to display

**Example:**

```php
$outputService->success('Context generated successfully');
```

**Output:**

```
 [OK] Context generated successfully
```

#### `error(string $message): void`

Displays an error message with appropriate styling.

**Parameters:**

- `$message`: The error message to display

**Example:**

```php
$outputService->error('Failed to generate context: invalid configuration');
```

**Output:**

```
 [ERROR] Failed to generate context: invalid configuration
```

#### `warning(string $message): void`

Displays a warning message with appropriate styling.

**Parameters:**

- `$message`: The warning message to display

**Example:**

```php
$outputService->warning('Some rules were skipped due to missing dependencies');
```

**Output:**

```
 [WARNING] Some rules were skipped due to missing dependencies
```

#### `info(string $message): void`

Displays an informational message with appropriate styling.

**Parameters:**

- `$message`: The info message to display

**Example:**

```php
$outputService->info('Processing 10 files...');
```

**Output:**

```
 [INFO] Processing 10 files...
```

#### `note(string|array $messages): void`

Displays a note message or list of note messages with appropriate styling.

**Parameters:**

- `$messages`: A single message string or array of message strings

**Example with single message:**

```php
$outputService->note('Configuration loaded from .contextrc file');
```

**Output:**

```
 [NOTE] Configuration loaded from .contextrc file
```

**Example with multiple messages:**

```php
$outputService->note([
    'Configuration loaded from .contextrc file',
    'Using default template directory',
    'Debug mode is enabled'
]);
```

**Output:**

```
 [NOTE]
  - Configuration loaded from .contextrc file
  - Using default template directory
  - Debug mode is enabled
```

### Formatting Methods

#### `highlight(string $text, string $color = 'bright-green', bool $bold = false): string`

Formats text with colored highlighting. Returns the formatted text rather than outputting directly.

**Parameters:**

- `$text`: The text to highlight
- `$color`: (Optional) The color to use for highlighting (default: 'bright-green')
- `$bold`: (Optional) Whether to make the text bold (default: false)

**Returns:**

- The formatted string with console color tags

**Example:**

```php
$highlightedText = $outputService->highlight('important', 'bright-red', true);
$outputService->info("This is an {$highlightedText} message");
```

**Output:**

```
 [INFO] This is an important message
        (where "important" appears in bright red and bold)
```

#### `formatProperty(string $text): string`

Formats text as a property name with consistent styling. Returns the formatted text.

**Parameters:**

- `$text`: The property name to format

**Returns:**

- The formatted string with console color tags

**Example:**

```php
$property = $outputService->formatProperty('filename');
$outputService->info("Processing {$property}: example.php");
```

**Output:**

```
 [INFO] Processing filename: example.php
        (where "filename" appears in bright cyan)
```

#### `formatLabel(string $text): string`

Formats text as a label with consistent styling. Returns the formatted text.

**Parameters:**

- `$text`: The label to format

**Returns:**

- The formatted string with console color tags

**Example:**

```php
$label = $outputService->formatLabel('Status');
$outputService->info("{$label}: Processing");
```

**Output:**

```
 [INFO] Status: Processing
        (where "Status" appears in yellow)
```

#### `formatCount(int $count): string`

Formats a numeric count with consistent styling. Returns the formatted text.

**Parameters:**

- `$count`: The numeric count to format

**Returns:**

- The formatted string with console color tags

**Example:**

```php
$count = $outputService->formatCount(42);
$outputService->info("Found {$count} files");
```

**Output:**

```
 [INFO] Found 42 files
        (where "42" appears in bright magenta)
```

### Layout Methods

#### `keyValue(string $key, string $value, int $width = 90): void`

Displays a key-value pair with aligned dots between them.

**Parameters:**

- `$key`: The key name
- `$value`: The value
- `$width`: (Optional) The width to use for alignment (default: 90)

**Example:**

```php
$outputService->keyValue('Files Processed', '42');
$outputService->keyValue('Time Elapsed', '3.5s');
$outputService->keyValue('Status', 'Completed');
```

**Output:**

```
Files Processed:...........................42
Time Elapsed:.............................3.5s
Status:..................................Completed
```

#### `table(array $headers, array $rows): void`

Displays data in a formatted table with headers and rows.

**Parameters:**

- `$headers`: Array of column headers
- `$rows`: Array of rows, each an array of column values

**Example:**

```php
$headers = ['ID', 'Filename', 'Status'];
$rows = [
    [1, 'config.php', 'Processed'],
    [2, 'schema.php', 'Skipped'],
    [3, 'routes.php', 'Processed']
];
$outputService->table($headers, $rows);
```

**Output:**

```
+----+-----------+-----------+
| ID | Filename  | Status    |
+----+-----------+-----------+
| 1  | config.php| Processed |
| 2  | schema.php| Skipped   |
| 3  | routes.php| Processed |
+----+-----------+-----------+
```

#### `statusList(array $items): void`

Displays a list of items with their status indicators.

**Parameters:**

- `$items`: Associative array where keys are descriptions and values are arrays with 'status' and 'message' keys

**Example:**

```php
$outputService->statusList([
    'Validating configuration' => ['status' => 'success', 'message' => 'Valid'],
    'Checking dependencies' => ['status' => 'warning', 'message' => 'Some optional dependencies missing'],
    'Compiling templates' => ['status' => 'error', 'message' => 'Syntax error in template']
]);
```

**Output:**

```
 ✓ Validating configuration.................Valid
 ! Checking dependencies....................Some optional dependencies missing
 ✗ Compiling templates.....................Syntax error in template
```

#### `separator(int $length = 80): void`

Displays a horizontal separator line.

**Parameters:**

- `$length`: (Optional) The length of the separator (default: 80)

**Example:**

```php
$outputService->separator();
```

**Output:**

```
--------------------------------------------------------------------------------
```

## Specialized Renderers

When using `OutputServiceWithRenderers`, you have access to specialized renderers that provide more formatting options.

### TableRenderer

#### `render(array $headers, array $rows, ?array $columnWidths = null, bool $addSeparatorAfterEachRow = false): void`

Renders a table with optional column width control and row separators.

**Parameters:**

- `$headers`: Array of column headers
- `$rows`: Array of rows, each an array of column values
- `$columnWidths`: (Optional) Array of column widths (null for auto-width)
- `$addSeparatorAfterEachRow`: (Optional) Whether to add separators between rows (default: false)

**Example:**

```php
$tableRenderer = $outputService->getTableRenderer();
$headers = ['ID', 'Name', 'Status'];
$rows = [
    [1, 'User Service', 'Running'],
    [2, 'Database Service', 'Stopped'],
    [3, 'Cache Service', 'Running']
];
$columnWidths = [5, 20, 10];
$tableRenderer->render($headers, $rows, $columnWidths, true);
```

**Output:**

```
+-----+--------------------+----------+
| ID  | Name               | Status   |
+-----+--------------------+----------+
| 1   | User Service       | Running  |
+-----+--------------------+----------+
| 2   | Database Service   | Stopped  |
+-----+--------------------+----------+
| 3   | Cache Service      | Running  |
+-----+--------------------+----------+
```

#### `createStyledCell(string $content, string $color, bool $bold = false): TableCell`

Creates a table cell with custom styling.

**Parameters:**

- `$content`: The cell content
- `$color`: The text color
- `$bold`: (Optional) Whether to make the text bold (default: false)

**Returns:**

- A styled TableCell object

**Example:**

```php
$tableRenderer = $outputService->getTableRenderer();
$headers = ['Type', 'Count', 'Status'];
$rows = [
    [
        'Files',
        $tableRenderer->createStyledCell('42', 'bright-magenta'),
        $tableRenderer->createStyledCell('Complete', 'green')
    ],
    [
        'Errors',
        $tableRenderer->createStyledCell('2', 'bright-magenta'),
        $tableRenderer->createStyledCell('Failed', 'red', true)
    ]
];
$tableRenderer->render($headers, $rows);
```

#### `createStyledHeaderRow(array $headers): array`

Creates a styled header row for tables.

**Parameters:**

- `$headers`: Array of header text values

**Returns:**

- Array of styled TableCell objects

**Example:**

```php
$tableRenderer = $outputService->getTableRenderer();
$styledHeaders = $tableRenderer->createStyledHeaderRow(['ID', 'Name', 'Status']);
$rows = [
    [1, 'User Service', 'Running'],
    [2, 'Database Service', 'Stopped']
];
$tableRenderer->render($styledHeaders, $rows);
```

#### `createPropertyCell(string $content): TableCell`

Creates a table cell styled as a property.

**Parameters:**

- `$content`: The cell content

**Returns:**

- A styled TableCell object

**Example:**

```php
$tableRenderer = $outputService->getTableRenderer();
$headers = ['Property', 'Value'];
$rows = [
    [
        $tableRenderer->createPropertyCell('filename'),
        'config.php'
    ],
    [
        $tableRenderer->createPropertyCell('size'),
        '2.5KB'
    ]
];
$tableRenderer->render($headers, $rows);
```

#### `createStatusCell(string $status): TableCell`

Creates a table cell styled according to status value.

**Parameters:**

- `$status`: The status text ('success', 'warning', 'error', etc.)

**Returns:**

- A styled TableCell object

**Example:**

```php
$tableRenderer = $outputService->getTableRenderer();
$headers = ['Component', 'Status'];
$rows = [
    [
        'Database',
        $tableRenderer->createStatusCell('success')
    ],
    [
        'Cache',
        $tableRenderer->createStatusCell('warning')
    ],
    [
        'Queue',
        $tableRenderer->createStatusCell('error')
    ]
];
$tableRenderer->render($headers, $rows);
```

### StatusRenderer

#### `renderSuccess(string $description, string $message = '', int $width = 100): void`

Renders a success status item.

**Parameters:**

- `$description`: The item description
- `$message`: (Optional) Additional message
- `$width`: (Optional) Width for alignment (default: 100)

**Example:**

```php
$statusRenderer = $outputService->getStatusRenderer();
$statusRenderer->renderSuccess('Database connection', 'Connected');
```

**Output:**

```
 ✓ Database connection.......................Connected
```

#### `renderWarning(string $description, string $message = '', int $width = 100): void`

Renders a warning status item.

**Parameters:**

- `$description`: The item description
- `$message`: (Optional) Additional message
- `$width`: (Optional) Width for alignment (default: 100)

**Example:**

```php
$statusRenderer = $outputService->getStatusRenderer();
$statusRenderer->renderWarning('Cache system', 'Using fallback');
```

**Output:**

```
 ! Cache system.............................Using fallback
```

#### `renderError(string $description, string $message = '', int $width = 100): void`

Renders an error status item.

**Parameters:**

- `$description`: The item description
- `$message`: (Optional) Additional message
- `$width`: (Optional) Width for alignment (default: 100)

**Example:**

```php
$statusRenderer = $outputService->getStatusRenderer();
$statusRenderer->renderError('Queue service', 'Not responding');
```

**Output:**

```
 ✗ Queue service............................Not responding
```

#### `renderInfo(string $description, string $message = '', int $width = 100): void`

Renders an info status item.

**Parameters:**

- `$description`: The item description
- `$message`: (Optional) Additional message
- `$width`: (Optional) Width for alignment (default: 100)

**Example:**

```php
$statusRenderer = $outputService->getStatusRenderer();
$statusRenderer->renderInfo('Config loaded', 'From .env file');
```

**Output:**

```
 ℹ Config loaded............................From .env file
```

####

`renderStatusItem(string $description, string $message, string $symbol, string $symbolColor, int $width = 100): void`

Renders a custom status item.

**Parameters:**

- `$description`: The item description
- `$message`: Additional message
- `$symbol`: The symbol to use
- `$symbolColor`: The color for the symbol
- `$width`: (Optional) Width for alignment (default: 100)

**Example:**

```php
$statusRenderer = $outputService->getStatusRenderer();
$statusRenderer->renderStatusItem(
    'Custom status',
    'Custom message',
    '»',
    'bright-cyan',
    100
);
```

**Output:**

```
 » Custom status............................Custom message
```

#### `renderStatusList(array $items, int $width = 100): void`

Renders a list of status items.

**Parameters:**

- `$items`: Associative array where keys are descriptions and values are arrays with 'status' and 'message' keys
- `$width`: (Optional) Width for alignment (default: 100)

**Example:**

```php
$statusRenderer = $outputService->getStatusRenderer();
$statusRenderer->renderStatusList([
    'Database' => ['status' => 'success', 'message' => 'Connected'],
    'Cache' => ['status' => 'warning', 'message' => 'Using fallback'],
    'Queue' => ['status' => 'error', 'message' => 'Not responding'],
    'Config' => ['status' => 'info', 'message' => 'Loaded']
]);
```

**Output:**

```
 ✓ Database................................Connected
 ! Cache...................................Using fallback
 ✗ Queue...................................Not responding
 ℹ Config..................................Loaded
```

### ListRenderer

#### `renderBulletList(array $items, string $bullet = '•', int $indentation = 2): void`

Renders a simple bulleted list.

**Parameters:**

- `$items`: Array of list items
- `$bullet`: (Optional) Bullet character (default: '•')
- `$indentation`: (Optional) Indentation level (default: 2)

**Example:**

```php
$listRenderer = $outputService->getListRenderer();
$listRenderer->renderBulletList([
    'Item one',
    'Item two',
    'Item three'
]);
```

**Output:**

```
  • Item one
  • Item two
  • Item three
```

#### `renderNumberedList(array $items, int $indentation = 2): void`

Renders a numbered list.

**Parameters:**

- `$items`: Array of list items
- `$indentation`: (Optional) Indentation level (default: 2)

**Example:**

```php
$listRenderer = $outputService->getListRenderer();
$listRenderer->renderNumberedList([
    'First step',
    'Second step',
    'Third step'
]);
```

**Output:**

```
  1. First step
  2. Second step
  3. Third step
```

#### `renderDefinitionList(array $items, int $indentation = 2, bool $alignDefinitions = true, int $width = 90): void`

Renders a definition list (term: definition).

**Parameters:**

- `$items`: Associative array where keys are terms and values are definitions
- `$indentation`: (Optional) Indentation level (default: 2)
- `$alignDefinitions`: (Optional) Whether to align definitions (default: true)
- `$width`: (Optional) Width for alignment with dots (default: 90)

**Example with alignment:**

```php
$listRenderer = $outputService->getListRenderer();
$listRenderer->renderDefinitionList([
    'Term 1' => 'Definition 1',
    'Longer Term 2' => 'Definition 2',
    'T3' => 'Definition 3'
], 2, true);
```

**Output:**

```
  Term 1:       Definition 1
  Longer Term 2: Definition 2
  T3:           Definition 3
```

**Example with dots:**

```php
$listRenderer = $outputService->getListRenderer();
$listRenderer->renderDefinitionList([
    'Term 1' => 'Definition 1',
    'Longer Term 2' => 'Definition 2',
    'T3' => 'Definition 3'
], 2, false);
```

**Output:**

```
  Term 1:..........................Definition 1
  Longer Term 2:...................Definition 2
  T3:...............................Definition 3
```

#### `renderGroupedProperties(array $groups, int $indentation = 2): void`

Renders grouped properties with section headers.

**Parameters:**

- `$groups`: Nested associative array where keys are group names and values are property arrays
- `$indentation`: (Optional) Indentation level (default: 2)

**Example:**

```php
$listRenderer = $outputService->getListRenderer();
$listRenderer->renderGroupedProperties([
    'Database Settings' => [
        'Host' => 'localhost',
        'User' => 'root',
        'Password' => '****'
    ],
    'Cache Settings' => [
        'Driver' => 'redis',
        'Timeout' => '60'
    ]
]);
```

**Output:**

```
Database Settings
----------------

  Host:     localhost
  User:     root
  Password: ****

Cache Settings
-------------

  Driver:  redis
  Timeout: 60
```

### SummaryRenderer

#### `renderStatsSummary(string $title, array $stats): void`

Renders a summary section with statistics.

**Parameters:**

- `$title`: Summary title
- `$stats`: Associative array of statistics

**Example:**

```php
$summaryRenderer = $outputService->getSummaryRenderer();
$summaryRenderer->renderStatsSummary('Project Statistics', [
    'Files' => 42,
    'Classes' => 15,
    'Methods' => 78,
    'Lines of Code' => 1250
]);
```

**Output:**

```
Project Statistics
-----------------

  Files:        42
  Classes:      15
  Methods:      78
  Lines of Code: 1250
```

#### `renderCompletionSummary(array $counts, ?int $total = null): void`

Renders a completion summary with counts and percentages.

**Parameters:**

- `$counts`: Associative array of category counts
- `$total`: (Optional) Total count (calculated from sum of counts if not provided)

**Example:**

```php
$summaryRenderer = $outputService->getSummaryRenderer();
$summaryRenderer->renderCompletionSummary([
    'Completed' => 42,
    'Skipped' => 8,
    'Failed' => 3
]);
```

**Output:**

```
  Completed: 42 (79%)
  Skipped: 8 (15%)
  Failed: 3 (6%)
  Total: 53
```

#### `renderProgressSummary(int $completed, int $total, string $label = 'Completed'): void`

Renders a progress summary with completion percentage.

**Parameters:**

- `$completed`: Number of completed items
- `$total`: Total number of items
- `$label`: (Optional) Label for the summary (default: 'Completed')

**Example:**

```php
$summaryRenderer = $outputService->getSummaryRenderer();
$summaryRenderer->renderProgressSummary(75, 100, 'Files processed');
```

**Output:**

```
Files processed: 75/100 (75%)
```

#### `renderTimeSummary(float $timeInSeconds, string $label = 'Execution time'): void`

Renders a time summary.

**Parameters:**

- `$timeInSeconds`: Time in seconds
- `$label`: (Optional) Label for the summary (default: 'Execution time')

**Example:**

```php
$summaryRenderer = $outputService->getSummaryRenderer();
$summaryRenderer->renderTimeSummary(3.5, 'Total processing time');
```

**Output:**

```
Total processing time: 3.50 sec
```

## Usage Examples

### Basic Command Output

```php
use Butschster\ContextGenerator\Console\BaseCommand;

final class GenerateCommand extends BaseCommand
{
    public function __invoke(): int
    {
        // Display a title
        $this->outputService->title('Generate Context');
        
        // Display a section
        $this->outputService->section('Configuration');
        
        // Display key-value pairs
        $this->outputService->keyValue('Source directory', '/var/www/project/src');
        $this->outputService->keyValue('Output directory', '/var/www/project/context');
        
        // Display a separator
        $this->outputService->separator();
        
        // Display progress
        $this->outputService->info('Processing files...');
        
        // Process files...
        
        // Display a status list
        $this->outputService->statusList([
            'Models' => ['status' => 'success', 'message' => '10 files'],
            'Controllers' => ['status' => 'success', 'message' => '15 files'],
            'Services' => ['status' => 'warning', 'message' => '5 of 8 files']
        ]);
        
        // Display a success message
        $this->outputService->success('Context generated successfully');
        
        return Command::SUCCESS;
    }
}
```

### Using Specialized Renderers

```php
use Butschster\ContextGenerator\Console\BaseCommand;

final class AnalyzeCommand extends BaseCommand
{
    public function __invoke(): int
    {
        // Get the output service with renderers
        $outputService = $this->outputService;
        
        // Display a title
        $outputService->title('Code Analysis');
        
        // Get the list renderer
        $listRenderer = $outputService->getListRenderer();
        
        // Display a section with a bullet list
        $outputService->section('Files Analyzed');
        $listRenderer->renderBulletList([
            'src/Models/*.php',
            'src/Controllers/*.php',
            'src/Services/*.php'
        ]);
        
        // Get the table renderer
        $tableRenderer = $outputService->getTableRenderer();
        
        // Display a section with a table
        $outputService->section('Results by Component');
        $headers = ['Component', 'Files', 'Issues', 'Status'];
        $rows = [
            [
                'Models',
                $tableRenderer->createStyledCell('10', 'bright-magenta'),
                $tableRenderer->createStyledCell('0', 'bright-magenta'),
                $tableRenderer->createStatusCell('success')
            ],
            [
                'Controllers',
                $tableRenderer->createStyledCell('15', 'bright-magenta'),
                $tableRenderer->createStyledCell('3', 'bright-magenta'),
                $tableRenderer->createStatusCell('warning')
            ],
            [
                'Services',
                $tableRenderer->createStyledCell('8', 'bright-magenta'),
                $tableRenderer->createStyledCell('1', 'bright-magenta'),
                $tableRenderer->createStatusCell('warning')
            ]
        ];
        $tableRenderer->render($headers, $rows);
        
        // Get the status renderer
        $statusRenderer = $outputService->getStatusRenderer();
        
        // Display a section with status items
        $outputService->section('Detailed Status');
        $statusRenderer->renderSuccess('Model validation', 'All models are valid');
        $statusRenderer->renderWarning('Controller validation', '3 controllers have issues');
        $statusRenderer->renderWarning('Service validation', '1 service has issues');
        
        // Get the summary renderer
        $summaryRenderer = $outputService->getSummaryRenderer();
        
        // Display a summary
        $summaryRenderer->renderStatsSummary('Analysis Summary', [
            'Total Files' => 33,
            'Files with Issues' => 4,
            'Total Issues' => 4,
            'Lines of Code' => 2500
        ]);
        
        $summaryRenderer->renderProgressSummary(29, 33, 'Files passing validation');
        $summaryRenderer->renderTimeSummary(1.25, 'Analysis time');
        
        return Command::SUCCESS;
    }
}
```

## Best Practices

1. **Use Consistent Styling**: Always use the OutputService methods for styling instead of direct string manipulation to
   ensure consistent appearance.
2. **Organize Output Logically**: Use sections to organize command output into logical groups for better readability.
3. **Use Specialized Renderers**: For complex output, use the specialized renderers rather than trying to build custom
   formatting.
4. **Provide Context**: Always include descriptive labels and context for data to make the output self-explanatory.
5. **Be Concise**: Keep output messages clear and concise, focusing on the most important information.
6. **Use Appropriate Status Indicators**: Use the correct status methods (success, warning, error, info) to convey the
   right level of importance.
7. **Consider Terminal Width**: Be mindful of terminal width constraints when formatting output, especially for tables
   and long messages.
8. **Use Color Sparingly**: While colors help highlight important information, overusing them can make output harder to
   read.
9. **Provide Summary Information**: End complex commands with a summary of what was done, using the SummaryRenderer when
   appropriate.
10. **Test in Different Environments**: Ensure your output looks good in different terminal environments and color
    schemes.
