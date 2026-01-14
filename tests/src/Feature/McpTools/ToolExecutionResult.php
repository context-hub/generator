<?php

declare(strict_types=1);

namespace Tests\Feature\McpTools;

/**
 * Value object representing the result of executing an MCP tool.
 */
final readonly class ToolExecutionResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $toolId,
        public array $args,
    ) {}

    /**
     * Check if the tool execution was successful.
     */
    public function isSuccess(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * Parse the output as JSON.
     *
     * @return array<string, mixed>|null Returns null if output is not valid JSON
     */
    public function getJsonOutput(): ?array
    {
        $decoded = \json_decode($this->output, true);

        return \json_last_error() === JSON_ERROR_NONE && \is_array($decoded) ? $decoded : null;
    }

    /**
     * Get the content from the result.
     * If output is JSON, returns the 'content' or 'output' field.
     * Otherwise, returns the raw output.
     */
    public function getContent(): string
    {
        $json = $this->getJsonOutput();

        if ($json !== null) {
            // Try common content field names
            return $json['content']
                ?? $json['output']
                ?? $json['result']['output']
                ?? $this->output;
        }

        return $this->output;
    }

    /**
     * Check if the result contains an error.
     */
    public function hasError(): bool
    {
        if (!$this->isSuccess()) {
            return true;
        }

        $json = $this->getJsonOutput();

        if ($json !== null) {
            return isset($json['error']) || (isset($json['success']) && $json['success'] === false);
        }

        return false;
    }

    /**
     * Get the error message if present.
     */
    public function getError(): ?string
    {
        $json = $this->getJsonOutput();

        if ($json !== null && isset($json['error'])) {
            return $json['error'];
        }

        if (!$this->isSuccess()) {
            return $this->output;
        }

        return null;
    }
}
