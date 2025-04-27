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
    use ToolAssertions;
    use PromptAssertions;

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
}
