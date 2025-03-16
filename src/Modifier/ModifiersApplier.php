<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Modifier;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ModifiersApplier implements ModifiersApplierInterface
{
    /**
     * @param array<Modifier> $modifiers Modifiers to apply
     * @param SourceModifierRegistry $registry Registry of modifier implementations
     * @param LoggerInterface $logger PSR Logger instance
     */
    public function __construct(
        private array $modifiers,
        private SourceModifierRegistry $registry = new SourceModifierRegistry(),
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public static function empty(SourceModifierRegistry $registry, ?LoggerInterface $logger = null): self
    {
        return new self(
            modifiers: [],
            registry: $registry,
            logger: $logger ?? new NullLogger(),
        );
    }

    public function withModifiers(array $modifiers): self
    {
        if (empty($modifiers)) {
            return $this;
        }

        return new self(
            modifiers: \array_merge($this->modifiers, $modifiers),
            registry: $this->registry,
            logger: $this->logger,
        );
    }

    public function apply(string $content, string $filename): string
    {
        if (empty($this->modifiers)) {
            return $content;
        }

        $this->logger->debug('Applying modifiers to content', [
            'contentType' => $filename,
            'modifierCount' => \count($this->modifiers),
        ]);

        $originalLength = \strlen($content);
        $modifiedContent = $content;

        foreach ($this->modifiers as $modifierId) {
            if (!$this->registry->has($modifierId)) {
                $this->logger->warning('Modifier not found', [
                    'modifierId' => (string) $modifierId,
                ]);
                continue;
            }

            $modifier = $this->registry->get($modifierId);
            if (!$modifier->supports($filename)) {
                $this->logger->debug('Modifier not applicable to content type', [
                    'modifierId' => (string) $modifierId,
                    'contentType' => $filename,
                ]);
                continue;
            }

            $this->logger->debug('Applying modifier', [
                'modifierId' => (string) $modifierId,
                'contentType' => $filename,
            ]);

            // Apply the modifier
            $modifiedContent = $modifier->modify($modifiedContent, $modifierId->context);

            $this->logger->debug('Modifier applied', [
                'modifierId' => (string) $modifierId,
                'contentType' => $filename,
                'beforeLength' => $originalLength,
                'afterLength' => \strlen($modifiedContent),
            ]);
        }

        return $modifiedContent;
    }
}
