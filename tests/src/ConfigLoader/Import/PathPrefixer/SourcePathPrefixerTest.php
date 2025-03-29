<?php

declare(strict_types=1);

namespace Tests\ConfigLoader\Import\PathPrefixer;

use Butschster\ContextGenerator\ConfigLoader\Import\PathPrefixer\SourcePathPrefixer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the SourcePathPrefixer class
 */
#[CoversClass(SourcePathPrefixer::class)]
final class SourcePathPrefixerTest extends TestCase
{
    private SourcePathPrefixer $prefixer;

    #[Test]
    public function it_should_apply_prefix_to_string_source_paths(): void
    {
        $config = [
            'documents' => [
                [
                    'name' => 'Document with String Source',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => 'src/file.php',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->prefixer->applyPrefix($config, 'prefix');

        $this->assertEquals(
            'prefix/src/file.php',
            $result['documents'][0]['sources'][0]['sourcePaths'],
        );
    }

    #[Test]
    public function it_should_apply_prefix_to_array_source_paths(): void
    {
        $config = [
            'documents' => [
                [
                    'name' => 'Document with Array Source',
                    'sources' => [
                        [
                            'type' => 'file',
                            'sourcePaths' => [
                                'src/file1.php',
                                'src/file2.php',
                                '/absolute/path/file3.php', // Absolute path
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->prefixer->applyPrefix($config, 'prefix');

        $expected = [
            'prefix/src/file1.php',
            'prefix/src/file2.php',
            '/absolute/path/file3.php', // Absolute path should remain unchanged
        ];

        $this->assertEquals(
            $expected,
            $result['documents'][0]['sources'][0]['sourcePaths'],
        );
    }

    #[Test]
    public function it_should_apply_prefix_to_composer_path(): void
    {
        $config = [
            'documents' => [
                [
                    'name' => 'Document with Composer Source',
                    'sources' => [
                        [
                            'type' => 'composer',
                            'composerPath' => 'path/to/composer.json',
                        ],
                        [
                            'type' => 'composer',
                            'composerPath' => '/absolute/path/composer.json', // Absolute path
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->prefixer->applyPrefix($config, 'prefix');

        // Relative composer path should have prefix applied
        $this->assertEquals(
            'prefix/path/to/composer.json',
            $result['documents'][0]['sources'][0]['composerPath'],
        );

        // Absolute composer path should remain unchanged
        $this->assertEquals(
            '/absolute/path/composer.json',
            $result['documents'][0]['sources'][1]['composerPath'],
        );
    }

    #[Test]
    public function it_should_only_apply_prefix_to_composer_path_for_composer_source_type(): void
    {
        $config = [
            'documents' => [
                [
                    'name' => 'Document with Non-Composer Source',
                    'sources' => [
                        [
                            'type' => 'not_composer',
                            'composerPath' => 'path/to/composer.json',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->prefixer->applyPrefix($config, 'prefix');

        // composerPath should not be modified since source type is not 'composer'
        $this->assertEquals(
            'path/to/composer.json',
            $result['documents'][0]['sources'][0]['composerPath'],
        );
    }

    #[Test]
    public function it_should_handle_empty_config(): void
    {
        $config = [
            'name' => 'Empty Config',
            // No documents array
        ];

        $result = $this->prefixer->applyPrefix($config, 'prefix');

        // Config should remain unchanged
        $this->assertEquals($config, $result);
    }

    #[Test]
    public function it_should_handle_missing_sources(): void
    {
        $config = [
            'documents' => [
                [
                    'name' => 'Document without Sources',
                    // No sources array
                ],
            ],
        ];

        $result = $this->prefixer->applyPrefix($config, 'prefix');

        // Config should remain unchanged
        $this->assertEquals($config, $result);
    }

    #[Test]
    public function it_should_handle_sources_without_paths(): void
    {
        $config = [
            'documents' => [
                [
                    'name' => 'Document with Source without Paths',
                    'sources' => [
                        [
                            'type' => 'other',
                            // No sourcePaths or composerPath
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->prefixer->applyPrefix($config, 'prefix');

        // Config should remain unchanged
        $this->assertEquals($config, $result);
    }

    protected function setUp(): void
    {
        $this->prefixer = new SourcePathPrefixer();
    }
}
