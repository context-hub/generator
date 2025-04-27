<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use PHPUnit\Framework\TestCase;

/**
 * Trait providing tool assertion methods for CompilingResult
 *
 * These methods can be added to the CompilingResult class to provide
 * comprehensive testing capabilities for tools in configuration.
 */
trait ToolAssertions
{
    /**
     * Assert that a tool with the specified ID exists in the result
     *
     * @param string $id The tool ID to check for
     * @return self For method chaining
     */
    public function assertToolExists(string $id): self
    {
        $toolFound = false;
        foreach ($this->result['tools'] ?? [] as $tool) {
            if ($tool['id'] === $id) {
                $toolFound = true;
                break;
            }
        }

        TestCase::assertTrue($toolFound, \sprintf('Tool with ID [%s] not found', $id));

        return $this;
    }

    /**
     * Assert that a tool with the specified ID has the expected properties
     *
     * Checks that the tool exists and has all the specified property values.
     *
     * @param string $id Tool ID to check
     * @param array $properties Key-value pairs of properties to check (e.g. ['type' => 'run', 'description' => 'Test tool'])
     * @return self For method chaining
     */
    public function assertTool(string $id, array $properties): self
    {
        $tool = null;
        foreach ($this->result['tools'] ?? [] as $t) {
            if ($t['id'] === $id) {
                $tool = $t;
                break;
            }
        }

        TestCase::assertNotNull($tool, \sprintf('Tool with ID [%s] not found', $id));

        foreach ($properties as $key => $value) {
            TestCase::assertArrayHasKey(
                $key,
                $tool,
                \sprintf('Tool [%s] does not have property [%s]', $id, $key),
            );

            TestCase::assertEquals(
                $value,
                $tool[$key],
                \sprintf('Tool [%s] property [%s] does not match expected value', $id, $key),
            );
        }

        return $this;
    }

    /**
     * Assert that a tool has specific schema properties
     *
     * Checks that the tool exists and has the expected schema structure with
     * the specified properties and required fields.
     *
     * @param string $id Tool ID to check
     * @param array $properties Properties that should be in the schema with their configurations
     * @param array $required Array of property names that should be marked as required
     * @return self For method chaining
     */
    public function assertToolSchema(string $id, array $properties = [], array $required = []): self
    {
        $tool = null;
        foreach ($this->result['tools'] ?? [] as $t) {
            if ($t['id'] === $id) {
                $tool = $t;
                break;
            }
        }

        TestCase::assertNotNull($tool, \sprintf('Tool with ID [%s] not found', $id));
        TestCase::assertArrayHasKey('schema', $tool, \sprintf('Tool [%s] does not have a schema', $id));

        if (!empty($properties)) {
            TestCase::assertArrayHasKey(
                'properties',
                $tool['schema'],
                \sprintf('Schema for tool [%s] does not have properties', $id),
            );

            foreach ($properties as $propName => $propDef) {
                TestCase::assertArrayHasKey(
                    $propName,
                    $tool['schema']['properties'],
                    \sprintf('Schema for tool [%s] does not have property [%s]', $id, $propName),
                );

                if (\is_array($propDef)) {
                    foreach ($propDef as $key => $value) {
                        TestCase::assertArrayHasKey(
                            $key,
                            $tool['schema']['properties'][$propName],
                            \sprintf(
                                'Property [%s] in schema for tool [%s] does not have key [%s]',
                                $propName,
                                $id,
                                $key,
                            ),
                        );

                        TestCase::assertEquals(
                            $value,
                            $tool['schema']['properties'][$propName][$key],
                            \sprintf(
                                'Property [%s] in schema for tool [%s], key [%s] does not match expected value',
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
                $tool['schema'],
                \sprintf('Schema for tool [%s] does not have required properties', $id),
            );

            foreach ($required as $reqProp) {
                TestCase::assertContains(
                    $reqProp,
                    $tool['schema']['required'],
                    \sprintf('Schema for tool [%s] does not have required property [%s]', $id, $reqProp),
                );
            }
        }

        return $this;
    }

    /**
     * Assert that a run tool has specific commands
     *
     * @param string $id Tool ID to check
     * @param array $expectedCommands Array of expected command configurations
     * @return self For method chaining
     */
    public function assertToolCommands(string $id, array $expectedCommands): self
    {
        $tool = null;
        foreach ($this->result['tools'] ?? [] as $t) {
            if ($t['id'] === $id) {
                $tool = $t;
                break;
            }
        }

        TestCase::assertNotNull($tool, \sprintf('Tool with ID [%s] not found', $id));
        TestCase::assertEquals('run', $tool['type'], \sprintf('Tool [%s] is not a "run" type tool', $id));
        TestCase::assertArrayHasKey('commands', $tool, \sprintf('Tool [%s] does not have commands', $id));

        TestCase::assertCount(
            \count($expectedCommands),
            $tool['commands'],
            \sprintf(
                'Tool [%s] has %d commands, expected %d',
                $id,
                \count($tool['commands']),
                \count($expectedCommands),
            ),
        );

        foreach ($expectedCommands as $index => $expectedCommand) {
            TestCase::assertArrayHasKey(
                $index,
                $tool['commands'],
                \sprintf('Tool [%s] does not have command at index [%d]', $id, $index),
            );

            $actualCommand = $tool['commands'][$index];

            if (isset($expectedCommand['cmd'])) {
                TestCase::assertEquals(
                    $expectedCommand['cmd'],
                    $actualCommand['cmd'],
                    \sprintf('Tool [%s] command at index [%d] has wrong cmd', $id, $index),
                );
            }

            if (isset($expectedCommand['args']) && \is_array($expectedCommand['args'])) {
                TestCase::assertCount(
                    \count($expectedCommand['args']),
                    $actualCommand['args'],
                    \sprintf(
                        'Tool [%s] command at index [%d] has %d args, expected %d',
                        $id,
                        $index,
                        \count($actualCommand['args']),
                        \count($expectedCommand['args']),
                    ),
                );

                foreach ($expectedCommand['args'] as $argIndex => $expectedArg) {
                    if (\is_string($expectedArg)) {
                        TestCase::assertEquals(
                            $expectedArg,
                            $actualCommand['args'][$argIndex],
                            \sprintf(
                                'Tool [%s] command at index [%d], argument at index [%d] does not match expected value',
                                $id,
                                $index,
                                $argIndex,
                            ),
                        );
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Assert that an http tool has specific requests
     *
     * @param string $id Tool ID to check
     * @param array $expectedRequests Array of expected request configurations
     * @return self For method chaining
     */
    public function assertToolRequests(string $id, array $expectedRequests): self
    {
        $tool = null;
        foreach ($this->result['tools'] ?? [] as $t) {
            if ($t['id'] === $id) {
                $tool = $t;
                break;
            }
        }

        TestCase::assertNotNull($tool, \sprintf('Tool with ID [%s] not found', $id));
        TestCase::assertEquals('http', $tool['type'], \sprintf('Tool [%s] is not an "http" type tool', $id));
        TestCase::assertArrayHasKey('requests', $tool, \sprintf('Tool [%s] does not have requests', $id));

        TestCase::assertCount(
            \count($expectedRequests),
            $tool['requests'],
            \sprintf(
                'Tool [%s] has %d requests, expected %d',
                $id,
                \count($tool['requests']),
                \count($expectedRequests),
            ),
        );

        foreach ($expectedRequests as $index => $expectedRequest) {
            TestCase::assertArrayHasKey(
                $index,
                $tool['requests'],
                \sprintf('Tool [%s] does not have request at index [%d]', $id, $index),
            );

            $actualRequest = $tool['requests'][$index];

            if (isset($expectedRequest['url'])) {
                TestCase::assertEquals(
                    $expectedRequest['url'],
                    $actualRequest['url'],
                    \sprintf('Tool [%s] request at index [%d] has wrong URL', $id, $index),
                );
            }

            if (isset($expectedRequest['method'])) {
                TestCase::assertEquals(
                    $expectedRequest['method'],
                    $actualRequest['method'],
                    \sprintf('Tool [%s] request at index [%d] has wrong method', $id, $index),
                );
            }

            if (isset($expectedRequest['headers']) && \is_array($expectedRequest['headers'])) {
                foreach ($expectedRequest['headers'] as $headerName => $expectedValue) {
                    TestCase::assertArrayHasKey(
                        $headerName,
                        $actualRequest['headers'],
                        \sprintf(
                            'Tool [%s] request at index [%d] does not have header [%s]',
                            $id,
                            $index,
                            $headerName,
                        ),
                    );

                    TestCase::assertEquals(
                        $expectedValue,
                        $actualRequest['headers'][$headerName],
                        \sprintf(
                            'Tool [%s] request at index [%d], header [%s] does not match expected value',
                            $id,
                            $index,
                            $headerName,
                        ),
                    );
                }
            }
        }

        return $this;
    }

    /**
     * Assert that the result contains a specific number of tools
     *
     * @param int $count Expected number of tools
     * @return self For method chaining
     */
    public function assertToolCount(int $count): self
    {
        TestCase::assertCount(
            $count,
            $this->result['tools'] ?? [],
            \sprintf('Expected %d tools, got %d', $count, \count($this->result['tools'] ?? [])),
        );

        return $this;
    }

    /**
     * Assert that no tools were found in the result
     *
     * @return self For method chaining
     */
    public function assertNoTools(): self
    {
        TestCase::assertEmpty($this->result['tools'] ?? [], 'Expected no tools, but found some');

        return $this;
    }
}
