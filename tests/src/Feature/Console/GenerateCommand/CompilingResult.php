<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use PHPUnit\Framework\TestCase;

final readonly class CompilingResult
{
    public function __construct(
        private array $result,
    ) {}

    public function getResult(): array
    {
        return $this->result;
    }

    public function assertSuccessfulCompiled(): self
    {
        TestCase::assertEquals('success', $this->result['status'] ?? null, 'Status should be success');
        TestCase::assertEquals(
            'Documents compiled successfully',
            $this->result['message'] ?? null,
            'Message should be success',
        );

        return $this;
    }

    public function assertNoDocumentsToCompile(): self
    {
        TestCase::assertEquals('success', $this->result['status'] ?? null, 'Status should be success');
        TestCase::assertEquals(
            'No documents found in configuration.',
            $this->result['message'] ?? null,
            'Message should be no documents found',
        );

        return $this;
    }

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
