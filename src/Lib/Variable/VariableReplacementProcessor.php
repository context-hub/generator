<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Variable\Provider\VariableProviderInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;

/**
 * Processor that replaces variable references in text
 *
 * Supports:
 * - ${VAR_NAME} - simple variable reference
 * - ${VAR_NAME:-default} - variable with default value
 * - {{VAR_NAME}} - alternative syntax
 * - {{VAR_NAME:-default}} - alternative syntax with default
 */
final readonly class VariableReplacementProcessor implements VariableReplacementProcessorInterface
{
    public function __construct(
        #[Proxy]
        private VariableProviderInterface $provider,
        #[LoggerPrefix(prefix: 'variable-replacement')]
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Process text by replacing variable references
     *
     * @param string $text Text containing variable references
     * @return string Text with variables replaced
     */
    public function process(string $text): string
    {
        // Replace ${VAR_NAME} and ${VAR_NAME:-default} syntax
        $result = \preg_replace_callback(
            '/\${([a-zA-Z0-9_]+)(?::-([^}]*))?}/',
            fn(array $matches) => $this->replaceVariable(
                name: $matches[1],
                default: $matches[2] ?? null,
                format: '${%s}',
            ),
            $text,
        );

        // Replace {{VAR_NAME}} and {{VAR_NAME:-default}} syntax
        return (string) \preg_replace_callback(
            '/{{([a-zA-Z0-9_]+)(?::-([^}]*))?}}/',
            fn(array $matches) => $this->replaceVariable(
                name: $matches[1],
                default: $matches[2] ?? null,
                format: '{{%s}}',
            ),
            (string) $result,
        );
    }

    /**
     * Replace a single variable reference
     *
     * @param string $name Variable name
     * @param string|null $default Default value if variable not found
     * @param string $format Format for unreplaced variable
     * @return string Variable value, default value, or original reference
     */
    private function replaceVariable(string $name, ?string $default, string $format): string
    {
        if ($this->provider->has($name)) {
            $value = $this->provider->get($name);

            $this->logger?->debug('Replacing variable', [
                'name' => $name,
                'value' => $value,
            ]);

            return $value ?? '';
        }

        // Variable not found - use default if provided
        if ($default !== null) {
            $this->logger?->debug('Using default value for variable', [
                'name' => $name,
                'default' => $default,
            ]);

            return $default;
        }

        // Keep the original reference if not found and no default
        return \sprintf($format, $name);
    }
}
