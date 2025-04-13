<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Variable\Provider\PredefinedVariableProvider;
use Butschster\ContextGenerator\Lib\Variable\Provider\VariableProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Processor that replaces variable references in text
 */
final readonly class VariableReplacementProcessor implements VariableReplacementProcessorInterface
{
    public function __construct(
        private VariableProviderInterface $provider = new PredefinedVariableProvider(),
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
        // Replace ${VAR_NAME} syntax
        $result = \preg_replace_callback(
            '/\${([a-zA-Z0-9_]+)}/',
            fn(array $matches) => $this->replaceVariable($matches[1], '${%s}'),
            $text,
        );

        // Replace {{VAR_NAME}} syntax
        return (string) \preg_replace_callback(
            '/{{([a-zA-Z0-9_]+)}}/',
            fn(array $matches) => $this->replaceVariable($matches[1], '{{%s}}'),
            (string) $result,
        );
    }

    /**
     * Replace a single variable reference
     *
     * @param string $name Variable name
     * @return string Variable value or original reference if not found
     */
    private function replaceVariable(string $name, string $format): string
    {
        if (!$this->provider->has($name)) {
            // Keep the original reference if not found and not failing
            return \sprintf($format, $name);
        }

        // Get the variable value
        $value = $this->provider->get($name);

        $this->logger?->debug('Replacing variable', [
            'name' => $name,
            'value' => $value,
        ]);

        // If value is null (should not happen due to has() check), return empty string
        return $value ?? '';
    }
}
