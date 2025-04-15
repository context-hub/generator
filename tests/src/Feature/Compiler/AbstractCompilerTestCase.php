<?php

declare(strict_types=1);

namespace Tests\Feature\Compiler;

use Butschster\ContextGenerator\Config\Registry\ConfigRegistryAccessor;
use Butschster\ContextGenerator\Document\Compiler\DocumentCompiler;
use Tests\Feature\FeatureTestCases;

abstract class AbstractCompilerTestCase extends FeatureTestCases
{
    protected function assertConfigItems(DocumentCompiler $compiler, ConfigRegistryAccessor $config): void
    {
        $outputPaths = [];
        $results = [];

        foreach ($config->getDocuments() as $document) {
            $outputPaths[] = $this->getContextsDir($document->outputPath);
            $compiledDocument = $compiler->compile($document);

            $results[$document->outputPath] = (string) $compiledDocument->content;
        }

        foreach ($outputPaths as $outputPath) {
            $this->assertFileExists($outputPath);
        }

        $this->assertSame(
            <<<'CONTENT'
                # Project structure
                ###  
                ```
                └── src/ [309.0 B, 309 chars]
                    └── dir1/ [217.0 B, 217 chars]
                        ├── Test1Class.php [68.0 B, 68 chars]
                        ├── TestClass.php [67.0 B, 67 chars]
                        ├── file.txt [7.0 B, 7 chars]
                        ├── subdir1/ [75.0 B, 75 chars]
                        │   └── Test3Class.php [68.0 B, 68 chars]
                        │   └── file.txt [7.0 B, 7 chars]
                    └── dir2/ [92.0 B, 92 chars]
                        └── Test2Class.php [85.0 B, 85 chars]
                        └── file.txt [7.0 B, 7 chars]
                
                ```
                CONTENT,
            $results['structure.md'],
        );

        $this->assertSame(
            <<<'CONTENT'
                # This is a test document
                ```
                // Structure of documents
                └── src/
                    └── dir1/
                        └── Test1Class.php
                        └── TestClass.php
                        └── subdir1/
                            └── Test3Class.php
                
                ```
                ###  Path: `/src/dir1/Test1Class.php`
                
                ```php
                <?php
                
                declare(strict_types=1);
                
                final readonly class Test1Class {}
                
                ```
                ###  Path: `/src/dir1/TestClass.php`
                
                ```php
                <?php
                
                declare(strict_types=1);
                
                final readonly class TestClass {}
                
                ```
                ###  Path: `/src/dir1/subdir1/Test3Class.php`
                
                ```php
                <?php
                
                declare(strict_types=1);
                
                final readonly class Test3Class {}
                
                ```
                <INSTRUCTION>
                Foo Bar
                </INSTRUCTION>
                ------------------------------------------------------------
                CONTENT,
            $results['test-document.md'],
        );

        $this->assertSame(
            <<<'CONTENT'
                # This is a test document 1
                ```
                // Structure of documents
                └── src/
                    └── dir2/
                        └── Test2Class.php
                
                ```
                ###  Path: `/src/dir2/Test2Class.php`
                
                ```php
                <?php
                
                declare(strict_types=1);
                
                namespace dir2;
                
                final readonly class Test2Class {}
                
                ```
                <INSTRUCTION>
                Foo Bar
                </INSTRUCTION>
                ------------------------------------------------------------
                CONTENT,
            $results['test-document1.md'],
        );

        $this->assertSame(
            <<<'CONTENT'
                # This is a test document 2
                ```
                // Structure of documents
                └── src/
                    └── dir1/
                        ├── file.txt
                        ├── subdir1/
                        │   └── file.txt
                    └── dir2/
                        └── file.txt
                
                ```
                ###  Path: `/src/dir1/file.txt`
                
                ```txt
                foo baf
                ```
                ###  Path: `/src/dir1/subdir1/file.txt`
                
                ```txt
                foo bar
                ```
                ###  Path: `/src/dir2/file.txt`
                
                ```txt
                foo baz
                ```
                <INSTRUCTION>
                Foo Bar
                </INSTRUCTION>
                ------------------------------------------------------------
                CONTENT,
            $results['test-document2.md'],
        );
    }
}
