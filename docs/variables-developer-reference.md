# Variable System - Developer Reference

## Overview

The Variable System provides dynamic value substitution throughout CTX configurations. It resolves `${VAR_NAME}` and
`{{VAR_NAME}}` placeholders from multiple sources with defined priority.

## Architecture

```
Butschster\ContextGenerator\Lib\Variable\
├── VariableResolver                         # Main entry point for resolving variables
├── VariableReplacementProcessor             # Handles ${} and {{}} pattern replacement
├── VariableReplacementProcessorInterface    # Interface for processors
├── CompositeProcessor                       # Chains multiple processors
└── Provider/
    ├── VariableProviderInterface            # Interface for variable sources
    ├── CompositeVariableProvider            # Combines providers with priority
    ├── ConfigVariableProvider               # Variables from context.yaml
    ├── DotEnvVariableProvider               # Variables from .env files
    └── PredefinedVariableProvider           # Built-in system variables
```

## Key Classes

### VariableResolver

**Location**: `src/Lib/Variable/VariableResolver.php`

Main service for resolving variables in strings and arrays. Delegates to `VariableReplacementProcessorInterface`.

```php
final readonly class VariableResolver
{
    public function __construct(
        private VariableReplacementProcessorInterface $processor,
    ) {}

    // Resolve variables in a string
    public function resolve(string $value): string;
    
    // Resolve variables in an array (recursive)
    public function resolve(array $values): array;
}
```

**Usage**:

```php
$resolver = $container->get(VariableResolver::class);
$resolved = $resolver->resolve('Hello ${USER}, today is ${DATE}');
```

### VariableReplacementProcessor

**Location**: `src/Lib/Variable/VariableReplacementProcessor.php`

Handles the actual pattern matching and replacement using regex.

**Supported patterns**:

- `${VAR_NAME}` - Dollar-brace syntax
- `{{VAR_NAME}}` - Double-brace syntax

```php
final readonly class VariableReplacementProcessor implements VariableReplacementProcessorInterface
{
    public function __construct(
        private VariableProviderInterface $provider,
    ) {}

    public function process(string $text): string
    {
        // Replace ${VAR_NAME} syntax
        $result = preg_replace_callback(
            '/\${([a-zA-Z0-9_]+)}/',
            fn(array $matches) => $this->replaceVariable($matches[1]),
            $text,
        );

        // Replace {{VAR_NAME}} syntax
        return preg_replace_callback(
            '/{{([a-zA-Z0-9_]+)}}/',
            fn(array $matches) => $this->replaceVariable($matches[1]),
            $result,
        );
    }
}
```

### CompositeVariableProvider

**Location**: `src/Lib/Variable/Provider/CompositeVariableProvider.php`

Combines multiple providers with priority ordering. First provider to have a variable wins.

```php
final readonly class CompositeVariableProvider implements VariableProviderInterface
{
    /** @var VariableProviderInterface[] */
    private array $providers;

    public function __construct(VariableProviderInterface ...$providers)
    {
        $this->providers = $providers;
    }

    public function has(string $name): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->has($name)) {
                return true;
            }
        }
        return false;
    }

    public function get(string $name): ?string
    {
        foreach ($this->providers as $provider) {
            if ($provider->has($name)) {
                return $provider->get($name);
            }
        }
        return null;
    }
}
```

### DotEnvVariableProvider

**Location**: `src/Lib/Variable/Provider/DotEnvVariableProvider.php`

Loads variables from `.env` files using the `vlucas/phpdotenv` library.

```php
final readonly class DotEnvVariableProvider implements VariableProviderInterface
{
    public function __construct(
        private RepositoryInterface $repository,
        private ?string $rootPath = null,
        private ?string $envFileName = null,
    ) {
        if ($this->rootPath) {
            $dotenv = Dotenv::create($repository, $this->rootPath, $this->envFileName);
            $dotenv->load();
        }
    }
}
```

**Key point**: The `.env` file path comes from `DirectoriesInterface::getEnvFilePath()`.

### PredefinedVariableProvider

**Location**: `src/Lib/Variable/Provider/PredefinedVariableProvider.php`

Provides built-in system variables:

| Variable      | Source                                     |
|---------------|--------------------------------------------|
| `USER`        | `$_SERVER['USER']` or `get_current_user()` |
| `HOME`        | `$_SERVER['HOME']`                         |
| `OS`          | `PHP_OS`                                   |
| `DATETIME`    | `date('Y-m-d H:i:s')`                      |
| `DATE`        | `date('Y-m-d')`                            |
| `TIME`        | `date('H:i:s')`                            |
| `ROOT_PATH`   | `DirectoriesInterface::getRootPath()`      |
| `OUTPUT_PATH` | `DirectoriesInterface::getOutputPath()`    |

## Bootloader & DI

**Location**: `src/Lib/Variable/VariableBootloader.php`

Registers all variable components in the DI container:

```php
final class VariableBootloader extends Bootloader
{
    public function defineSingletons(): array
    {
        return [
            // Provider chain with priority
            VariableProviderInterface::class => static function (
                ConfigVariableProvider $configVariableProvider,
                DirectoriesInterface $dirs,
            ) {
                $envFilePath = null;
                $envFileName = null;

                // Get .env path from DirectoriesInterface
                if ($dirs->getEnvFilePath() !== null) {
                    $envFilePath = (string) ($dirs->getEnvFilePath()->isFile() ?
                        $dirs->getEnvFilePath()->parent() :
                        $dirs->getEnvFilePath());
                    $envFileName = $dirs->getEnvFilePath()->name();
                }

                return new CompositeVariableProvider(
                    // Priority 1: Config variables (highest)
                    $configVariableProvider,

                    // Priority 2: Environment variables
                    new DotEnvVariableProvider(
                        repository: RepositoryBuilder::createWithDefaultAdapters()->make(),
                        rootPath: $envFilePath,
                        envFileName: $envFileName,
                    ),

                    // Priority 3: Predefined variables (lowest)
                    new PredefinedVariableProvider(dirs: $dirs),
                );
            },

            VariableResolver::class => VariableResolver::class,
        ];
    }
}
```

## How .env Loading Works

### The Flow

1. **CLI Option**: User provides `--env=.env.local` option
2. **DirectoriesInterface**: Command calls `$dirs->withEnvFile($this->envFile)`
3. **Scope Binding**: New `DirectoriesInterface` is bound in a scope
4. **Provider Creation**: `VariableBootloader` creates `DotEnvVariableProvider` with the path
5. **Dotenv Loading**: Provider loads the `.env` file into memory
6. **Resolution**: Variables are resolved when needed

### Example Command Pattern

```php
final class MyCommand extends BaseCommand
{
    #[Option(name: 'env', shortcut: 'e')]
    protected ?string $envFile = null;

    public function __invoke(
        Container $container,
        DirectoriesInterface $dirs,
    ): int {
        // Update directories with env file path
        $dirs = $dirs->withEnvFile($this->envFile);

        // Run in scope with updated directories
        return $container->runScope(
            bindings: new Scope(
                bindings: [
                    DirectoriesInterface::class => $dirs,
                ],
            ),
            scope: function (SomeService $service): int {
                // Service will use VariableResolver with .env loaded
                return Command::SUCCESS;
            },
        );
    }
}
```

### DirectoriesInterface::withEnvFile()

**Location**: `src/Directories.php`

```php
public function withEnvFile(?string $envFileName): self
{
    if ($envFileName === null) {
        return $this;
    }

    // Resolve relative to root path
    $envFilePath = $this->rootPathObj->join($envFileName)->toString();

    return new self(
        rootPath: $this->rootPath,
        outputPath: $this->outputPath,
        configPath: $this->configPath,
        jsonSchemaPath: $this->jsonSchemaPath,
        envFilePath: $envFilePath,  // <-- This is the key
        binaryPath: $this->binaryPath,
    );
}
```

## Using Variables in Factories

When creating services that need variable resolution, inject `VariableResolver`:

```php
// Example: rag/Store/StoreFactory.php
final readonly class StoreFactory
{
    public function __construct(
        private VariableResolver $variableResolver,
    ) {}

    public function create(RagConfig $config): StoreInterface
    {
        return new QdrantStore(
            // Resolve ${VAR} placeholders
            endpointUrl: $this->variableResolver->resolve($config->store->endpointUrl),
            apiKey: $this->variableResolver->resolve($config->store->apiKey),
            // ...
        );
    }
}
```

## Config Parser Integration

**Location**: `src/Config/Parser/VariablesParserPlugin.php`

Parses the `variables:` section from configuration and updates `ConfigVariableProvider`:

```php
final readonly class VariablesParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private ConfigVariableProvider $variableProvider,
    ) {}

    public function getConfigKey(): string
    {
        return 'variables';
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        $variables = $config['variables'];
        
        // Update the provider with config variables
        $this->variableProvider->setVariables($variables);

        return null; // No registry, just side effect
    }
}
```

## Priority Resolution Summary

```
┌─────────────────────────────────────────────────────┐
│           Variable Resolution Order                  │
├─────────────────────────────────────────────────────┤
│ 1. ConfigVariableProvider     (context.yaml)        │
│    ↓ if not found                                   │
│ 2. DotEnvVariableProvider     (.env file)           │
│    ↓ if not found                                   │
│ 3. PredefinedVariableProvider (USER, DATE, etc.)    │
│    ↓ if not found                                   │
│ 4. Keep original ${VAR_NAME} unchanged              │
└─────────────────────────────────────────────────────┘
```

## Testing Variables

```php
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\Provider\CompositeVariableProvider;

// Create a test provider
$provider = new class implements VariableProviderInterface {
    public function has(string $name): bool {
        return $name === 'TEST_VAR';
    }
    public function get(string $name): ?string {
        return $name === 'TEST_VAR' ? 'test_value' : null;
    }
};

$processor = new VariableReplacementProcessor($provider);
$resolver = new VariableResolver($processor);

$result = $resolver->resolve('Value is ${TEST_VAR}');
// Result: "Value is test_value"
```

## Common Patterns

### 1. Adding New Predefined Variables

Edit `PredefinedVariableProvider`:

```php
private function getBuiltInVariables(): array
{
    return [
        'USER' => $_SERVER['USER'] ?? get_current_user(),
        'MY_NEW_VAR' => $this->computeMyNewVar(),  // Add here
        // ...
    ];
}
```

### 2. Adding a New Variable Source

Create a new provider and add to `VariableBootloader`:

```php
return new CompositeVariableProvider(
    $configVariableProvider,
    new MyCustomProvider(),  // Add with desired priority
    new DotEnvVariableProvider(...),
    new PredefinedVariableProvider(...),
);
```

### 3. Using Variables in a New Service

```php
final readonly class MyService
{
    public function __construct(
        private VariableResolver $variableResolver,
    ) {}

    public function process(string $template): string
    {
        return $this->variableResolver->resolve($template);
    }
}
```

## Files Reference

| File                                                       | Purpose                        |
|------------------------------------------------------------|--------------------------------|
| `src/Lib/Variable/VariableResolver.php`                    | Main resolver service          |
| `src/Lib/Variable/VariableReplacementProcessor.php`        | Pattern matching & replacement |
| `src/Lib/Variable/Provider/CompositeVariableProvider.php`  | Provider chain                 |
| `src/Lib/Variable/Provider/ConfigVariableProvider.php`     | Config file variables          |
| `src/Lib/Variable/Provider/DotEnvVariableProvider.php`     | .env file loading              |
| `src/Lib/Variable/Provider/PredefinedVariableProvider.php` | System variables               |
| `src/Lib/Variable/VariableBootloader.php`                  | DI registration                |
| `src/Config/Parser/VariablesParserPlugin.php`              | Config parsing                 |
| `src/Directories.php`                                      | `withEnvFile()` method         |
