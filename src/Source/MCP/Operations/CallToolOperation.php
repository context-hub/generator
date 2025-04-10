<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\MCP\Operations;

use Butschster\ContextGenerator\Lib\Content\ContentBuilder;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Mcp\Client\ClientSession;
use Mcp\Types\TextContent;

/**
 * Operation for calling tools on an MCP server
 */
final readonly class CallToolOperation extends AbstractOperation
{
    /**
     * @param string $name Name of the tool to call
     * @param array<string, mixed>|null $arguments Arguments to pass to the tool
     */
    public function __construct(
        public string $name,
        public ?array $arguments = null,
    ) {
        parent::__construct('tool.call');
    }

    /**
     * Execute this operation on the given client session
     *
     * @param ClientSession $session MCP client session
     * @param VariableResolver $resolver Variable resolver for resolving variables in configuration
     * @return string Raw result of the tool call
     */
    public function execute(ClientSession $session, VariableResolver $resolver): string
    {
        // Resolve variables in tool name
        $resolvedToolName = $resolver->resolve($this->name);

        // Resolve variables in arguments
        $resolvedArguments = null;
        if ($this->arguments !== null) {
            $resolvedArguments = [];
            foreach ($this->arguments as $key => $value) {
                if (\is_string($value)) {
                    $resolvedArguments[$key] = $resolver->resolve($value);
                } else {
                    $resolvedArguments[$key] = $value;
                }
            }
        }

        // Call the tool
        $result = $session->callTool($resolvedToolName, $resolvedArguments);

        $content = '';

        foreach ($result->content as $item) {
            if ($item instanceof TextContent) {
                $content .= $item->text;
            }
        }

        return $content;
    }

    /**
     * Build content for this operation
     *
     * @param ContentBuilder $builder Content builder to add content to
     * @param string $content The processed content to build with
     */
    #[\Override]
    public function buildContent(ContentBuilder $builder, string $content): void
    {
        // Try to parse as JSON first
        $decoded = \json_decode($content, true);

        if (\json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
            // It's valid JSON, so format it nicely
            $builder->addTitle(\sprintf('Tool: %s', $this->name), 2);

            if ($this->arguments !== null && !empty($this->arguments)) {
                $builder->addTitle('Arguments:', 3);
                $builder->addCodeBlock(\json_encode($this->arguments, JSON_PRETTY_PRINT), 'json');
            }

            $builder->addTitle('Result:', 3);
            $builder->addCodeBlock(\json_encode($decoded, JSON_PRETTY_PRINT), 'json');
        } else {
            // It's not JSON, or not an array, so treat it as text
            $builder->addTitle(\sprintf('Tool: %s', $this->name), 2);

            if ($this->arguments !== null && !empty($this->arguments)) {
                $builder->addTitle('Arguments:', 3);
                $builder->addCodeBlock(\json_encode($this->arguments, JSON_PRETTY_PRINT), 'json');
            }

            $builder->addTitle('Result:', 3);
            $builder->addText($content);
        }
    }

    /**
     * Serialize to JSON
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return \array_filter([
            ...parent::jsonSerialize(),
            'name' => $this->name,
            'arguments' => $this->arguments,
        ]);
    }
}
