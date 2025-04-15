<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Config\Parser\ConfigParserPluginInterface;
use Butschster\ContextGenerator\Config\Registry\RegistryInterface;
use Butschster\ContextGenerator\McpServer\Prompt\Exception\PromptParsingException;
use Butschster\ContextGenerator\McpServer\Prompt\Exception\TemplateResolutionException;
use Butschster\ContextGenerator\McpServer\Prompt\Extension\TemplateResolver;
use Psr\Log\LoggerInterface;

/**
 * Plugin for parsing 'prompts' section in configuration files.
 */
#[LoggerPrefix(prefix: 'prompt.parser')]
final readonly class PromptParserPlugin implements ConfigParserPluginInterface
{
    public function __construct(
        private PromptRegistryInterface $promptRegistry,
        private TemplateResolver $templateResolver,
        private PromptConfigFactory $promptFactory = new PromptConfigFactory(),
        private ?LoggerInterface $logger = null,
    ) {}

    public function getConfigKey(): string
    {
        return 'prompts';
    }

    public function parse(array $config, string $rootPath): ?RegistryInterface
    {
        \assert($this->promptRegistry instanceof RegistryInterface);

        if (!$this->supports($config)) {
            return null;
        }

        $this->logger?->debug('Parsing prompts configuration', [
            'count' => \count($config['prompts']),
        ]);

        // First pass: Register all prompts and templates
        foreach ($config['prompts'] as $index => $promptConfig) {
            try {
                $prompt = $this->promptFactory->createFromConfig($promptConfig);
                $this->promptRegistry->register($prompt);

                $this->logger?->debug('Prompt parsed and registered', [
                    'id' => $prompt->id,
                    'type' => $prompt->type->value,
                ]);
            } catch (\Throwable $e) {
                $this->logger?->warning('Failed to parse prompt', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);

                throw new PromptParsingException(
                    \sprintf('Failed to parse prompt at index %d: %s', $index, $e->getMessage()),
                    previous: $e,
                );
            }
        }

        // Second pass: Resolve templates for non-template prompts
        $resolver = $this->templateResolver;

        foreach ($this->promptRegistry->getItems() as $id => $prompt) {
            if (!empty($prompt->extensions)) {
                try {
                    $resolvedPrompt = $resolver->resolve($prompt);
                    $this->promptRegistry->register($resolvedPrompt);

                    $this->logger?->debug('Prompt template resolved', [
                        'id' => $resolvedPrompt->id,
                    ]);
                } catch (TemplateResolutionException $e) {
                    $this->logger?->warning('Failed to resolve prompt template', [
                        'id' => $id,
                        'error' => $e->getMessage(),
                    ]);

                    throw new PromptParsingException(
                        \sprintf('Failed to resolve template for prompt "%s": %s', $id, $e->getMessage()),
                        previous: $e,
                    );
                }
            }
        }

        return $this->promptRegistry;
    }

    public function supports(array $config): bool
    {
        return isset($config['prompts']) && \is_array($config['prompts']);
    }

    public function updateConfig(array $config, string $rootPath): array
    {
        // This plugin doesn't modify the configuration
        return $config;
    }
}
