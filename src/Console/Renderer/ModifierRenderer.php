<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Console\Renderer;

use Butschster\ContextGenerator\Modifier\Modifier;

/**
 * @deprecated Should be completely redesigned
 */
final class ModifierRenderer
{
    private const array MODIFIER_TYPES = [
        'php-signature' => 'PHP Signature Modifier',
        'php-content-filter' => 'PHP Content Filter Modifier',
        'sanitizer' => 'Content Sanitizer Modifier',
        'php-docs' => 'PHP Docs Transformer Modifier',
    ];

    public function renderModifier(Modifier $modifier): string
    {
        $name = $modifier->name;
        $displayName = self::MODIFIER_TYPES[$name] ?? 'Modifier: ' . $name;

        $output = Style::subtitle($displayName) . "\n";
        $output .= Style::separator('-', \strlen($displayName)) . "\n\n";

        if (!empty($modifier->context)) {
            $output .= Style::property("Options") . ":\n";
            foreach ($modifier->context as $key => $value) {
                $output .= Style::indent(Style::keyValue($this->formatKey($key), $value)) . "\n";
            }
        }

        return $output;
    }

    private function formatKey(string $key): string
    {
        return \ucfirst(\str_replace('_', ' ', $key));
    }
}
