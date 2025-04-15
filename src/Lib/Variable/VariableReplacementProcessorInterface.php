<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\Variable;

interface VariableReplacementProcessorInterface
{
    /**
     * Process text by replacing variable references
     *
     * @param string $text Text containing variable references
     * @return string Text with variables replaced
     */
    public function process(string $text): string;
}
