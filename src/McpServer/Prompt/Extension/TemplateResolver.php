<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt\Extension;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\McpServer\Prompt\Exception\TemplateResolutionException;
use Butschster\ContextGenerator\McpServer\Prompt\PromptProviderInterface;
use Mcp\Types\PromptMessage;
use Mcp\Types\TextContent;
use Psr\Log\LoggerInterface;
use Spiral\Core\Container;

/**
 * Resolves templates in the inheritance chain.
 */
#[LoggerPrefix(prefix: 'prompt.template')]
final readonly class TemplateResolver
{
    public function __construct(
        private PromptProviderInterface $promptProvider,
        private Container $container,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Resolves a prompt definition by applying any template extensions.
     *
     * @param PromptDefinition $prompt The prompt definition to resolve
     * @return PromptDefinition The resolved prompt definition
     * @throws TemplateResolutionException If the resolution fails
     */
    public function resolve(PromptDefinition $prompt): PromptDefinition
    {
        // If no extensions, return the prompt as is
        if (empty($prompt->extensions)) {
            return $prompt;
        }

        // Process each extension
        $messages = $prompt->messages;
        $processedExtensions = [];

        foreach ($prompt->extensions as $extension) {
            // Prevent circular dependencies
            if (\in_array($extension->templateId, $processedExtensions, true)) {
                continue;
            }

            $processedExtensions[] = $extension->templateId;

            // Get the template
            try {
                $template = $this->promptProvider->get($extension->templateId);
            } catch (\InvalidArgumentException $e) {
                throw new TemplateResolutionException(
                    \sprintf('Template "%s" not found', $extension->templateId),
                    previous: $e,
                );
            }

            // Resolve nested templates first
            if (!empty($template->extensions)) {
                $template = $this->resolve($template);
            }

            // Apply variable substitution
            $messages = $this->mergeMessages(
                $messages,
                $template->messages,
                $extension->arguments,
            );
        }

        // Create a new prompt with the resolved messages
        return new PromptDefinition(
            id: $prompt->id,
            prompt: $prompt->prompt,
            messages: $messages,
            type: $prompt->type,
            extensions: $prompt->extensions,
        );
    }

    /**
     * Merges messages from a template with the prompt's messages, applying variable substitution.
     *
     * @param PromptMessage[] $promptMessages The prompt's messages
     * @param PromptMessage[] $templateMessages The template's messages
     * @param PromptExtensionArgument[] $arguments The variables to substitute
     * @return PromptMessage[] The merged messages
     */
    private function mergeMessages(array $promptMessages, array $templateMessages, array $arguments): array
    {
        // If the prompt has no messages, use the template's messages with substitution
        if (empty($promptMessages)) {
            return $this->substituteMessages($templateMessages, $arguments);
        }

        foreach ($this->substituteMessages($templateMessages, $arguments) as $templateMessage) {
            $promptMessages[] = $templateMessage;
        }

        // Otherwise, keep the prompt's messages (extensions just provide structure)
        return $promptMessages;
    }

    /**
     * Applies variable substitution to template messages.
     *
     * @param PromptMessage[] $messages The messages to process
     * @param PromptExtensionArgument[] $arguments The variables to substitute
     * @return PromptMessage[] The processed messages
     */
    private function substituteMessages(array $messages, array $arguments): array
    {
        $result = [];

        // Create a variable provider for the extension arguments
        $variableProvider = new PromptExtensionVariableProvider($arguments);

        // Create a resolver with the variable provider
        $resolver = $this->container
            ->get(VariableResolver::class)
            ->with(new VariableReplacementProcessor($variableProvider, $this->logger));

        foreach ($messages as $message) {
            \assert($message->content instanceof TextContent);
            $content = $message->content->text;
            $substitutedContent = $resolver->resolve($content);

            $this->logger?->debug('Template message processed', [
                'original' => $content,
                'resolved' => $substitutedContent,
            ]);

            // Create a new message with the substituted content
            $result[] = new PromptMessage(
                role: $message->role,
                content: new TextContent(text: $substitutedContent),
            );
        }

        return $result;
    }
}
