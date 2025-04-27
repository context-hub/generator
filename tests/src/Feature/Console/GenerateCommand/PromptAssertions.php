<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use Tests\TestCase;

trait PromptAssertions
{
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
