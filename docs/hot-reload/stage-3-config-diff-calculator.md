# Stage 3: Config Diff Calculator

## Objective

Implement a system to calculate differences between two configuration states, identifying what was added, removed, or modified.

---

## Problem

When configuration changes, we need to know exactly what changed to:
1. Update only affected registry items (efficient)
2. Avoid unnecessary MCP notifications
3. Log meaningful change information

Comparing entire configs is not enough - we need granular diffs per section.

---

## Solution

Create a `ConfigDiffCalculator` that compares old and new configurations and produces a `ConfigDiff` object describing the changes.

---

## Files to Create

### Directory Structure

```
src/Watcher/
└── Diff/
    ├── ConfigDiff.php
    └── ConfigDiffCalculator.php
```

---

## Class Definitions

### 1. `src/Watcher/Diff/ConfigDiff.php`

Immutable DTO representing changes:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Diff;

/**
 * Represents the difference between two configuration states.
 *
 * @template T of array
 */
final readonly class ConfigDiff
{
    /**
     * @param array<string, T> $added   Items present in new but not in old (keyed by ID)
     * @param array<string, T> $removed Items present in old but not in new (keyed by ID)
     * @param array<string, T> $modified Items present in both but with different values (keyed by ID)
     * @param array<string, T> $unchanged Items identical in both (keyed by ID)
     */
    public function __construct(
        public array $added = [],
        public array $removed = [],
        public array $modified = [],
        public array $unchanged = [],
    ) {}

    /**
     * Check if there are any changes
     */
    public function hasChanges(): bool
    {
        return !empty($this->added)
            || !empty($this->removed)
            || !empty($this->modified);
    }

    /**
     * Get total number of changes
     */
    public function changeCount(): int
    {
        return count($this->added)
            + count($this->removed)
            + count($this->modified);
    }

    /**
     * Get summary for logging
     */
    public function getSummary(): string
    {
        if (!$this->hasChanges()) {
            return 'No changes';
        }

        $parts = [];

        if (!empty($this->added)) {
            $parts[] = sprintf('%d added', count($this->added));
        }
        if (!empty($this->removed)) {
            $parts[] = sprintf('%d removed', count($this->removed));
        }
        if (!empty($this->modified)) {
            $parts[] = sprintf('%d modified', count($this->modified));
        }

        return implode(', ', $parts);
    }

    /**
     * Create empty diff (no changes)
     */
    public static function empty(): self
    {
        return new self();
    }
}
```

### 2. `src/Watcher/Diff/ConfigDiffCalculator.php`

Calculates diffs for configuration sections:

```php
<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Diff;

/**
 * Calculates differences between configuration states.
 */
final readonly class ConfigDiffCalculator
{
    /**
     * Calculate diff for a specific config section (tools, prompts, etc.)
     *
     * @param array $oldItems Old configuration items
     * @param array $newItems New configuration items
     * @param string $idKey Key used to identify items (default: 'id')
     * @return ConfigDiff
     */
    public function calculate(array $oldItems, array $newItems, string $idKey = 'id'): ConfigDiff
    {
        // Index items by ID for efficient lookup
        $oldById = $this->indexById($oldItems, $idKey);
        $newById = $this->indexById($newItems, $idKey);

        $oldIds = array_keys($oldById);
        $newIds = array_keys($newById);

        // Find added (in new, not in old)
        $addedIds = array_diff($newIds, $oldIds);
        $added = array_intersect_key($newById, array_flip($addedIds));

        // Find removed (in old, not in new)
        $removedIds = array_diff($oldIds, $newIds);
        $removed = array_intersect_key($oldById, array_flip($removedIds));

        // Find potentially modified (in both)
        $commonIds = array_intersect($oldIds, $newIds);

        $modified = [];
        $unchanged = [];

        foreach ($commonIds as $id) {
            if ($this->itemsEqual($oldById[$id], $newById[$id])) {
                $unchanged[$id] = $newById[$id];
            } else {
                $modified[$id] = $newById[$id];
            }
        }

        return new ConfigDiff(
            added: $added,
            removed: $removed,
            modified: $modified,
            unchanged: $unchanged,
        );
    }

    /**
     * Calculate diff for entire config (all sections)
     *
     * @return array<string, ConfigDiff> Section name => diff
     */
    public function calculateAll(array $oldConfig, array $newConfig): array
    {
        $sections = [
            'tools' => 'id',
            'prompts' => 'id',
            'documents' => 'description', // Documents use description as identifier
        ];

        $diffs = [];

        foreach ($sections as $section => $idKey) {
            $oldItems = $oldConfig[$section] ?? [];
            $newItems = $newConfig[$section] ?? [];

            $diff = $this->calculate($oldItems, $newItems, $idKey);

            // Only include sections with changes
            if ($diff->hasChanges()) {
                $diffs[$section] = $diff;
            }
        }

        return $diffs;
    }

    /**
     * Index array items by their ID field
     *
     * @return array<string, array>
     */
    private function indexById(array $items, string $idKey): array
    {
        $indexed = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = $item[$idKey] ?? null;

            if ($id === null || $id === '') {
                // Generate ID from hash if not present
                $id = md5(json_encode($item));
            }

            $indexed[(string) $id] = $item;
        }

        return $indexed;
    }

    /**
     * Compare two items for equality
     */
    private function itemsEqual(array $a, array $b): bool
    {
        // Normalize for comparison (sort keys recursively)
        $normalizedA = $this->normalizeForComparison($a);
        $normalizedB = $this->normalizeForComparison($b);

        return $normalizedA === $normalizedB;
    }

    /**
     * Normalize array for consistent comparison
     */
    private function normalizeForComparison(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalizeForComparison($value);
            }
        }

        return $data;
    }
}
```

---

## Usage Examples

### Basic Usage

```php
$calculator = new ConfigDiffCalculator();

$oldTools = [
    ['id' => 'tool-a', 'name' => 'Tool A', 'description' => 'First tool'],
    ['id' => 'tool-b', 'name' => 'Tool B', 'description' => 'Second tool'],
];

$newTools = [
    ['id' => 'tool-a', 'name' => 'Tool A', 'description' => 'Updated first tool'], // Modified
    ['id' => 'tool-c', 'name' => 'Tool C', 'description' => 'Third tool'],          // Added
    // tool-b removed
];

$diff = $calculator->calculate($oldTools, $newTools);

// $diff->added    = ['tool-c' => [...]]
// $diff->removed  = ['tool-b' => [...]]
// $diff->modified = ['tool-a' => [...]]
// $diff->hasChanges() = true
// $diff->getSummary() = "1 added, 1 removed, 1 modified"
```

### Full Config Diff

```php
$diffs = $calculator->calculateAll($oldConfig, $newConfig);

foreach ($diffs as $section => $diff) {
    echo "{$section}: {$diff->getSummary()}\n";
}
// tools: 1 added, 1 removed
// prompts: 2 modified
```

---

## Implementation Details

### ID Resolution

Items are identified by their `id` field by default. If no ID is present, a hash of the item is used:

```php
// Tool with explicit ID
['id' => 'my-tool', 'name' => 'My Tool'] // ID = 'my-tool'

// Item without ID (e.g., some documents)
['description' => 'My Doc', 'sources' => [...]] // ID = md5(json_encode(...))
```

### Comparison Logic

Items are compared after normalization:
1. Keys are sorted alphabetically (recursive)
2. Deep equality check using `===`

This ensures:
- `['a' => 1, 'b' => 2]` equals `['b' => 2, 'a' => 1]`
- Nested arrays are compared correctly

### Performance

For typical config sizes (< 100 items per section):
- O(n) indexing
- O(n) diff calculation
- Memory: 2x config size temporarily

---

## Testing

### Unit Tests for ConfigDiff

```php
public function test_has_changes_when_items_added(): void
{
    $diff = new ConfigDiff(
        added: ['a' => ['id' => 'a']],
    );

    $this->assertTrue($diff->hasChanges());
    $this->assertEquals(1, $diff->changeCount());
}

public function test_no_changes_when_empty(): void
{
    $diff = ConfigDiff::empty();

    $this->assertFalse($diff->hasChanges());
    $this->assertEquals(0, $diff->changeCount());
}

public function test_summary_format(): void
{
    $diff = new ConfigDiff(
        added: ['a' => [], 'b' => []],
        removed: ['c' => []],
        modified: ['d' => [], 'e' => [], 'f' => []],
    );

    $this->assertEquals('2 added, 1 removed, 3 modified', $diff->getSummary());
}
```

### Unit Tests for ConfigDiffCalculator

```php
public function test_detects_added_items(): void
{
    $calc = new ConfigDiffCalculator();

    $old = [];
    $new = [['id' => 'new-item', 'name' => 'New']];

    $diff = $calc->calculate($old, $new);

    $this->assertArrayHasKey('new-item', $diff->added);
    $this->assertEmpty($diff->removed);
    $this->assertEmpty($diff->modified);
}

public function test_detects_removed_items(): void
{
    $calc = new ConfigDiffCalculator();

    $old = [['id' => 'old-item', 'name' => 'Old']];
    $new = [];

    $diff = $calc->calculate($old, $new);

    $this->assertEmpty($diff->added);
    $this->assertArrayHasKey('old-item', $diff->removed);
    $this->assertEmpty($diff->modified);
}

public function test_detects_modified_items(): void
{
    $calc = new ConfigDiffCalculator();

    $old = [['id' => 'item', 'name' => 'Original']];
    $new = [['id' => 'item', 'name' => 'Updated']];

    $diff = $calc->calculate($old, $new);

    $this->assertEmpty($diff->added);
    $this->assertEmpty($diff->removed);
    $this->assertArrayHasKey('item', $diff->modified);
}

public function test_ignores_key_order(): void
{
    $calc = new ConfigDiffCalculator();

    $old = [['id' => 'item', 'a' => 1, 'b' => 2]];
    $new = [['b' => 2, 'id' => 'item', 'a' => 1]]; // Same content, different order

    $diff = $calc->calculate($old, $new);

    $this->assertFalse($diff->hasChanges());
    $this->assertArrayHasKey('item', $diff->unchanged);
}

public function test_handles_items_without_id(): void
{
    $calc = new ConfigDiffCalculator();

    $old = [['name' => 'No ID Item', 'value' => 1]];
    $new = [['name' => 'No ID Item', 'value' => 2]]; // Modified

    $diff = $calc->calculate($old, $new, idKey: 'name');

    $this->assertArrayHasKey('No ID Item', $diff->modified);
}

public function test_calculate_all_sections(): void
{
    $calc = new ConfigDiffCalculator();

    $old = [
        'tools' => [['id' => 'tool-1', 'name' => 'Tool']],
        'prompts' => [['id' => 'prompt-1', 'name' => 'Prompt']],
    ];
    $new = [
        'tools' => [['id' => 'tool-1', 'name' => 'Updated Tool']],
        'prompts' => [['id' => 'prompt-1', 'name' => 'Prompt']], // Unchanged
    ];

    $diffs = $calc->calculateAll($old, $new);

    $this->assertArrayHasKey('tools', $diffs);
    $this->assertArrayNotHasKey('prompts', $diffs); // No changes, not included
}
```

---

## Acceptance Criteria

- [ ] `ConfigDiff` correctly reports `hasChanges()`
- [ ] `ConfigDiff` provides accurate `changeCount()`
- [ ] `ConfigDiff::getSummary()` is human-readable
- [ ] `ConfigDiffCalculator` detects added items
- [ ] `ConfigDiffCalculator` detects removed items
- [ ] `ConfigDiffCalculator` detects modified items
- [ ] `ConfigDiffCalculator` ignores key ordering
- [ ] `ConfigDiffCalculator` handles missing IDs
- [ ] `calculateAll()` processes all sections

---

## Notes

- Keep comparison logic simple - deep equality is sufficient
- Consider adding `getModifiedFields(id)` for detailed change info (future)
- `unchanged` field useful for debugging but not strictly necessary
- Documents section uses `description` as ID since they don't have explicit IDs
