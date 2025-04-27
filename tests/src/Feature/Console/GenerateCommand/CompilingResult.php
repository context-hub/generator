<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use PHPUnit\Framework\TestCase;

/**
 * Wrapper for generate command result assertions
 *
 * Provides methods to assert various aspects of the generate command output
 * including document compilation, imports, errors, and prompts.
 */
final readonly class CompilingResult
{
    /**
     * Constructor
     *
     * @param array $result The raw result data from the generate command (parsed JSON)
     */
    public function __construct(
        private array $result,
    ) {}

    /**
     * Get the raw result array
     *
     * @return array The complete result data
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * Assert that documents were successfully compiled
     *
     * Checks that status is "success" and message indicates successful compilation
     *
     * @return self For method chaining
     */
    public function assertDocumentsCompiled(): self
    {
        TestCase::assertEquals(
            'Documents compiled successfully',
            $this->result['message'] ?? null,
            'Message should be success',
        );

        return $this->assertSuccess();
    }

    public function assertSuccess(): self
    {
        TestCase::assertEquals('success', $this->result['status'] ?? null, 'Status should be success');

        return $this;
    }

    /**
     * Assert that no documents were found to compile
     *
     * Checks that status is "success" but message indicates no documents found
     *
     * @return self For method chaining
     */
    public function assertNoDocumentsToCompile(): self
    {
        TestCase::assertEquals(
            'No documents found in configuration.',
            $this->result['message'] ?? null,
            'Message should be no documents found',
        );

        return $this->assertSuccess();
    }

    /**
     * Assert that a specific document was not generated
     *
     * @param string $document The document path to check for absence
     * @return self For method chaining
     */
    public function assertMissedContext(string $document): self
    {
        foreach ($this->result['result'] as $documentData) {
            if ($documentData['context_path'] === $document) {
                TestCase::fail(\sprintf('Context file [%s] found', $document));
            }
        }

        return $this;
    }

    /**
     * Assert that a generated document contains (or doesn't contain) specific content
     *
     * @param string $document The document path to check
     * @param array $contains Strings that should be in the document
     * @param array $notContains Strings that should NOT be in the document
     * @return self For method chaining
     */
    public function assertContext(string $document, array $contains, array $notContains = []): self
    {
        foreach ($this->result['result'] as $documentData) {
            if ($documentData['context_path'] === $document) {
                TestCase::assertFileExists(
                    $contextPath = $documentData['output_path'] . '/' . $documentData['context_path'],
                );

                $content = \file_get_contents($contextPath);
                foreach ($contains as $string) {
                    TestCase::assertStringContainsString(
                        $string,
                        $content,
                        \sprintf(
                            'Context file [%s] does not contain string [%s]',
                            $documentData['context_path'],
                            $string,
                        ),
                    );
                }

                foreach ($notContains as $string) {
                    TestCase::assertStringNotContainsString(
                        $string,
                        $content,
                        \sprintf(
                            'Context file [%s] should not contain string [%s]',
                            $documentData['context_path'],
                            $string,
                        ),
                    );
                }

                return $this;
            }
        }

        return $this;
    }

    /**
     * Assert that a specific import was processed
     *
     * @param string $path Path of the imported file
     * @param string $type Type of the import (e.g., "local", "url")
     * @return self For method chaining
     */
    public function assertImported(string $path, string $type): self
    {
        $this->assertSuccess();

        foreach ($this->result['imports'] ?? [] as $import) {
            if ($import['path'] === $path && $import['type'] === $type) {
                return $this;
            }
        }

        TestCase::fail(\sprintf('Import [%s] with type [%s] not found', $path, $type));
    }

    /**
     * Assert that the configuration failed to load
     *
     * @return self For method chaining
     */
    public function assetFiledToLoadConfig(): self
    {
        TestCase::assertEquals('error', $this->result['status'] ?? null, 'Status should be error');
        TestCase::assertEquals(
            'Failed to load configuration',
            $this->result['message'] ?? null,
            'Message should be error',
        );

        return $this;
    }

    /**
     * Assert that a document has specific error messages
     *
     * @param string $document The document path to check
     * @param array $contains Error strings that should be present
     * @return self For method chaining
     */
    public function assertDocumentError(string $document, array $contains): self
    {
        foreach ($this->result['result'] as $documentData) {
            if ($documentData['context_path'] === $document) {
                foreach ($contains as $string) {
                    TestCase::assertStringContainsString(
                        $string,
                        \implode("\n", $documentData['errors']),
                        \sprintf(
                            'Document [%s] does not contain error [%s]',
                            $documentData['context_path'],
                            $string,
                        ),
                    );
                }

                return $this;
            }
        }

        return $this;
    }

    /**
     * Assert that a prompt with the specified ID exists in the result
     *
     * @param string $id The prompt ID to check for
     * @return self For method chaining
     */
    public function assertPromptExists(string $id): self
    {
        $promptFound = false;
        foreach ($this->result['prompts'] ?? [] as $prompt) {
            if ($prompt['id'] === $id) {
                $promptFound = true;
                break;
            }
        }

        TestCase::assertTrue($promptFound, \sprintf('Prompt with ID [%s] not found', $id));

        return $this;
    }

    /**
     * Assert that a prompt with the specified ID has the expected properties
     *
     * Checks that the prompt exists and has all the specified property values.
     *
     * @param string $id Prompt ID to check
     * @param array $properties Key-value pairs of properties to check (e.g. ['type' => 'prompt', 'description' => 'Test prompt'])
     * @return self For method chaining
     */
    public function assertPrompt(string $id, array $properties): self
    {
        $prompt = null;
        foreach ($this->result['prompts'] ?? [] as $p) {
            if ($p['id'] === $id) {
                $prompt = $p;
                break;
            }
        }

        TestCase::assertNotNull($prompt, \sprintf('Prompt with ID [%s] not found', $id));

        foreach ($properties as $key => $value) {
            TestCase::assertArrayHasKey(
                $key,
                $prompt,
                \sprintf('Prompt [%s] does not have property [%s]', $id, $key),
            );

            TestCase::assertEquals(
                $value,
                $prompt[$key],
                \sprintf('Prompt [%s] property [%s] does not match expected value', $id, $key),
            );
        }

        return $this;
    }

    /**
     * Assert that a prompt has the expected message content
     *
     * Checks that the prompt exists and its messages contain the specified text.
     * This works with different message content formats (string or text object).
     *
     * @param string $id Prompt ID to check
     * @param array $messageContents Array of strings that should be contained in the messages
     * @return self For method chaining
     */
    public function assertPromptMessages(string $id, array $messageContents): self
    {
        $prompt = null;
        foreach ($this->result['prompts'] ?? [] as $p) {
            if ($p['id'] === $id) {
                $prompt = $p;
                break;
            }
        }

        TestCase::assertNotNull($prompt, \sprintf('Prompt with ID [%s] not found', $id));
        TestCase::assertArrayHasKey('messages', $prompt, \sprintf('Prompt [%s] does not have messages', $id));

        $messagesContent = '';
        foreach ($prompt['messages'] as $message) {
            if (isset($message['content']['text'])) {
                $messagesContent .= $message['content']['text'] . "\n";
            } elseif (\is_string($message['content'])) {
                $messagesContent .= $message['content'] . "\n";
            }
        }

        foreach ($messageContents as $content) {
            TestCase::assertStringContainsString(
                $content,
                $messagesContent,
                \sprintf('Prompt [%s] messages do not contain [%s]', $id, $content),
            );
        }

        return $this;
    }

    /**
     * Assert that a prompt extends a specific template
     *
     * Checks that the prompt exists and extends the specified template ID.
     *
     * @param string $id Prompt ID to check
     * @param string $templateId Template ID that should be extended
     * @return self For method chaining
     */
    public function assertPromptExtends(string $id, string $templateId): self
    {
        $prompt = null;
        foreach ($this->result['prompts'] ?? [] as $p) {
            if ($p['id'] === $id) {
                $prompt = $p;
                break;
            }
        }

        TestCase::assertNotNull($prompt, \sprintf('Prompt with ID [%s] not found', $id));
        TestCase::assertArrayHasKey('extend', $prompt, \sprintf('Prompt [%s] does not extend any templates', $id));

        $templateFound = false;
        foreach ($prompt['extend'] as $extend) {
            if ($extend['id'] === $templateId) {
                $templateFound = true;
                break;
            }
        }

        TestCase::assertTrue(
            $templateFound,
            \sprintf('Prompt [%s] does not extend template [%s]', $id, $templateId),
        );

        return $this;
    }

    /**
     * Assert that a prompt has specific template arguments
     *
     * Checks that the prompt extends the specified template and has the expected
     * argument values for that template.
     *
     * @param string $id Prompt ID to check
     * @param string $templateId Template ID to check arguments for
     * @param array $arguments Key-value pairs of arguments to check
     * @return self For method chaining
     */
    public function assertPromptTemplateArguments(string $id, string $templateId, array $arguments): self
    {
        $prompt = null;
        foreach ($this->result['prompts'] ?? [] as $p) {
            if ($p['id'] === $id) {
                $prompt = $p;
                break;
            }
        }

        TestCase::assertNotNull($prompt, \sprintf('Prompt with ID [%s] not found', $id));
        TestCase::assertArrayHasKey('extend', $prompt, \sprintf('Prompt [%s] does not extend any templates', $id));

        $templateArgs = null;
        foreach ($prompt['extend'] as $extend) {
            if ($extend['id'] === $templateId) {
                TestCase::assertArrayHasKey(
                    'arguments',
                    $extend,
                    \sprintf('Extension for template [%s] in prompt [%s] does not have arguments', $templateId, $id),
                );
                $templateArgs = $extend['arguments'];
                break;
            }
        }

        TestCase::assertNotNull(
            $templateArgs,
            \sprintf('Prompt [%s] does not extend template [%s]', $id, $templateId),
        );

        foreach ($arguments as $key => $value) {
            TestCase::assertArrayHasKey(
                $key,
                $templateArgs,
                \sprintf('Arguments for template [%s] in prompt [%s] does not have key [%s]', $templateId, $id, $key),
            );

            TestCase::assertEquals(
                $value,
                $templateArgs[$key],
                \sprintf(
                    'Arguments for template [%s] in prompt [%s], key [%s] does not match expected value',
                    $templateId,
                    $id,
                    $key,
                ),
            );
        }

        return $this;
    }

    /**
     * Assert that a prompt has a specific schema structure
     *
     * Checks that the prompt exists and has the expected schema properties and required fields.
     *
     * @param string $id Prompt ID to check
     * @param array $properties Properties that should be in the schema with their configurations
     * @param array $required Array of property names that should be marked as required
     * @return self For method chaining
     */
    public function assertPromptSchema(string $id, array $properties = [], array $required = []): self
    {
        $prompt = null;
        foreach ($this->result['prompts'] ?? [] as $p) {
            if ($p['id'] === $id) {
                $prompt = $p;
                break;
            }
        }

        TestCase::assertNotNull($prompt, \sprintf('Prompt with ID [%s] not found', $id));
        TestCase::assertArrayHasKey('schema', $prompt, \sprintf('Prompt [%s] does not have a schema', $id));

        if (!empty($properties)) {
            TestCase::assertArrayHasKey(
                'properties',
                $prompt['schema'],
                \sprintf('Schema for prompt [%s] does not have properties', $id),
            );

            foreach ($properties as $propName => $propData) {
                TestCase::assertArrayHasKey(
                    $propName,
                    $prompt['schema']['properties'],
                    \sprintf('Schema for prompt [%s] does not have property [%s]', $id, $propName),
                );

                if (\is_array($propData)) {
                    foreach ($propData as $key => $value) {
                        TestCase::assertArrayHasKey(
                            $key,
                            $prompt['schema']['properties'][$propName],
                            \sprintf(
                                'Property [%s] in schema for prompt [%s] does not have key [%s]',
                                $propName,
                                $id,
                                $key,
                            ),
                        );

                        TestCase::assertEquals(
                            $value,
                            $prompt['schema']['properties'][$propName][$key],
                            \sprintf(
                                'Property [%s] in schema for prompt [%s], key [%s] does not match expected value',
                                $propName,
                                $id,
                                $key,
                            ),
                        );
                    }
                }
            }
        }

        if (!empty($required)) {
            TestCase::assertArrayHasKey(
                'required',
                $prompt['schema'],
                \sprintf('Schema for prompt [%s] does not have required properties', $id),
            );

            foreach ($required as $reqProp) {
                TestCase::assertContains(
                    $reqProp,
                    $prompt['schema']['required'],
                    \sprintf('Schema for prompt [%s] does not have required property [%s]', $id, $reqProp),
                );
            }
        }

        return $this;
    }

    /**
     * Assert that the result contains a specific number of prompts
     *
     * @param int $count Expected number of prompts
     * @return self For method chaining
     */
    public function assertPromptCount(int $count): self
    {
        TestCase::assertCount(
            $count,
            $this->result['prompts'] ?? [],
            \sprintf('Expected %d prompts, got %d', $count, \count($this->result['prompts'] ?? [])),
        );

        return $this;
    }

    /**
     * Assert that no prompts were imported or found in the result
     *
     * @return self For method chaining
     */
    public function assertNoPrompts(): self
    {
        TestCase::assertEmpty($this->result['prompts'] ?? [], 'Expected no prompts, but found some');

        return $this;
    }
}
