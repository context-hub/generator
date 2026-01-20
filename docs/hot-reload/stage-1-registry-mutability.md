# Stage 1: Registry Mutability

## Objective

Add methods to modify and clear registries at runtime, enabling dynamic updates without server restart.

---

## Problem

Current registries (`ToolRegistry`, `PromptRegistry`) only support adding items:

```php
// Current ToolRegistry
public function register(ToolDefinition $tool): void
{
    $this->tools[$tool->id] = $tool;
}
```

There's no way to:
- Remove a tool/prompt when it's deleted from config
- Update an existing tool/prompt when its definition changes
- Clear all items before a full reload

---

## Solution

Extend registry interfaces and implementations with mutation methods.

---

## Files to Modify

### 1. `src/Tool/ToolRegistryInterface.php`

Add new method signatures:

```php
interface ToolRegistryInterface
{
    // Existing
    public function register(ToolDefinition $tool): void;

    // New methods
    public function remove(string $id): bool;
    public function update(ToolDefinition $tool): void;
    public function clear(): void;
    public function has(string $id): bool;
}
```

### 2. `src/Tool/ToolRegistry.php`

Implement new methods:

```php
public function remove(string $id): bool
{
    if (!$this->has($id)) {
        return false;
    }
    unset($this->tools[$id]);
    return true;
}

public function update(ToolDefinition $tool): void
{
    // Update replaces existing or adds new
    $this->tools[$tool->id] = $tool;
}

public function clear(): void
{
    $this->tools = [];
}
```

### 3. `src/Prompt/PromptRegistryInterface.php`

Add same method signatures:

```php
interface PromptRegistryInterface
{
    // Existing
    public function register(PromptDefinition $prompt): void;

    // New methods
    public function remove(string $id): bool;
    public function update(PromptDefinition $prompt): void;
    public function clear(): void;
    public function has(string $name): bool;
}
```

### 4. `src/Prompt/PromptRegistry.php`

Implement new methods (same pattern as ToolRegistry).

---

## Implementation Details

### Method Behavior

| Method | Behavior |
|--------|----------|
| `remove(id)` | Returns `true` if item existed and was removed, `false` otherwise |
| `update(item)` | Replaces existing item or adds new (upsert semantics) |
| `clear()` | Removes all items, resets to empty state |
| `has(id)` | Already exists, ensure it's in interface |

### Thread Safety

Not required - MCP server runs in single thread. No mutex needed.

### Backward Compatibility

All new methods have default implementations or are additive. Existing code continues to work.

---

## Testing

### Unit Tests for ToolRegistry

```php
public function test_remove_existing_tool(): void
{
    $registry = new ToolRegistry();
    $tool = ToolDefinition::fromArray(['id' => 'test', 'name' => 'test', ...]);

    $registry->register($tool);
    $this->assertTrue($registry->has('test'));

    $result = $registry->remove('test');

    $this->assertTrue($result);
    $this->assertFalse($registry->has('test'));
}

public function test_remove_nonexistent_tool(): void
{
    $registry = new ToolRegistry();

    $result = $registry->remove('nonexistent');

    $this->assertFalse($result);
}

public function test_update_existing_tool(): void
{
    $registry = new ToolRegistry();
    $tool1 = ToolDefinition::fromArray(['id' => 'test', 'name' => 'test', 'description' => 'v1']);
    $tool2 = ToolDefinition::fromArray(['id' => 'test', 'name' => 'test', 'description' => 'v2']);

    $registry->register($tool1);
    $registry->update($tool2);

    $this->assertEquals('v2', $registry->get('test')->description);
}

public function test_clear_removes_all(): void
{
    $registry = new ToolRegistry();
    $registry->register(ToolDefinition::fromArray(['id' => 'a', ...]));
    $registry->register(ToolDefinition::fromArray(['id' => 'b', ...]));

    $registry->clear();

    $this->assertEmpty($registry->all());
}
```

### Unit Tests for PromptRegistry

Same pattern as ToolRegistry tests.

---

## Acceptance Criteria

- [ ] `ToolRegistry::remove()` removes tool by ID
- [ ] `ToolRegistry::update()` replaces existing tool
- [ ] `ToolRegistry::clear()` removes all tools
- [ ] `PromptRegistry` has same methods
- [ ] All existing tests still pass
- [ ] New unit tests cover all mutation methods

---

## Notes

- Keep methods simple - no events/callbacks at this stage
- Events for MCP notifications will be added in Stage 4
- Consider adding `count(): int` method for debugging
