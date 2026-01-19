<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag;

use Butschster\ContextGenerator\Rag\Config\RagConfig;

interface RagRegistryInterface
{
    public function setConfig(RagConfig $config): void;

    public function getConfig(): RagConfig;

    public function isEnabled(): bool;
}
