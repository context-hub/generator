<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Vectorizer;

use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Rag\Config\RagConfig;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory as OpenAiPlatformFactory;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Document\VectorizerInterface;

final readonly class VectorizerFactory
{
    public function __construct(
        private VariableResolver $variableResolver,
    ) {}

    public function create(RagConfig $config): VectorizerInterface
    {
        $platform = match ($config->vectorizer->platform) {
            'openai' => OpenAiPlatformFactory::create(
                apiKey: $this->variableResolver->resolve($config->vectorizer->apiKey),
            ),
            default => throw new \InvalidArgumentException(
                \sprintf('Unknown vectorizer platform: %s', $config->vectorizer->platform),
            ),
        };

        return new Vectorizer($platform, $config->vectorizer->model);
    }
}
