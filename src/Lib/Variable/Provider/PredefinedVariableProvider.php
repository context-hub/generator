<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable\Provider;

/**
 * Provider with predefined system variables
 */
final readonly class PredefinedVariableProvider implements VariableProviderInterface
{
    /**
     * Get all predefined variables
     *
     * @return array<string, string>
     */
    private function getPredefinedVariables(): array
    {
        return [
            'DATETIME' => date('Y-m-d H:i:s'),
            'DATE' => date('Y-m-d'),
            'TIME' => date('H:i:s'),
            'TIMESTAMP' => (string) time(),
            'USER' => get_current_user(),
            'HOME_DIR' => getenv('HOME') ?: (getenv('USERPROFILE') ?: '/'),
            'TEMP_DIR' => sys_get_temp_dir(),
            'OS' => PHP_OS,
            'HOSTNAME' => gethostname() ?: 'unknown',
            'PWD' => getcwd() ?: '.',
        ];
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->getPredefinedVariables());
    }

    public function get(string $name): ?string
    {
        return $this->getPredefinedVariables()[$name] ?? null;
    }
}
