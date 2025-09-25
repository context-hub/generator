<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Action\Resources;

use Butschster\ContextGenerator\DirectoriesInterface;
use Butschster\ContextGenerator\McpServer\Action\Resources\Service\JsonSchemaService;
use Butschster\ContextGenerator\McpServer\Attribute\Resource;
use Butschster\ContextGenerator\McpServer\Routing\Attribute\Get;
use Butschster\ContextGenerator\Template\Detection\TemplateDetectionService;
use PhpMcp\Schema\Content\TextResourceContents;
use PhpMcp\Schema\Result\ReadResourceResult;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[Resource(
    name: 'CTX Schema Builder Instructions',
    description: 'Returns instructions for generating a CTX configuration file',
    uri: 'ctx://schema-builder-instructions',
    mimeType: 'application/json',
)]
final readonly class GenerateConfigAction
{
    public function __construct(
        private LoggerInterface $logger,
        private DirectoriesInterface $dirs,
        private TemplateDetectionService $detectionService,
        private JsonSchemaService $jsonSchema,
    ) {}

    #[Get(path: '/resource/ctx/schema-builder-instructions', name: 'resources.ctx.schema-builder-instructions')]
    public function __invoke(ServerRequestInterface $request): ReadResourceResult
    {
        try {
            $detection = $this->detectionService->detectBestTemplate($this->dirs->getRootPath())->jsonSerialize();

            $response = <<<'INSTRUCTIONS'
                You are an expert system for analyzing software projects and generating CTX configuration files.
                
                ## Your Role
                Analyze the provided project structure, user requirements, and generate a complete, valid CTX 
                configuration file that follows the JSON Schema specification.
                
                ## JSON Schema
                %s
                
                ## Project info
                %s
                
                ## Available Tools
                1. Use directory-list if you need to list directories in the project
                2. Use file-read if you need to read files in the project
                
                ## Analysis Process
                1. **Project Structure**: Examine directories, files, and detected frameworks
                2. **Framework Patterns**: Apply framework-specific best practices
                3. **Context Prioritization**: Focus on code most valuable for AI development assistance
                4. **Document Organization**: Create logical, coherent context document groupings
                
                ## Configuration Rules
                1. **Schema Compliance**: All output must validate against the provided JSON Schema
                2. **Practical Focus**: Prioritize directories and files developers actively work with
                3. **Logical Grouping**: Create documents that group related functionality
                4. **Appropriate Sources**: Use optimal source types (file/tree) for each context need
                5. **Meaningful Descriptions**: Provide clear, descriptive document names
                
                ## Output Requirements
                - Generate complete YAML configuration in artefact form
                - Use tree sources for structure overview
                - Use file sources for code analysis
                - Include tags
                - Do not include modifiers, 
                - Validate all paths exist in project structure
                INSTRUCTIONS;

            $response = \sprintf(
                $response,
                \json_encode($this->jsonSchema->getSimplifiedSchema()),
                \json_encode($detection),
            );

            return new ReadResourceResult([
                new TextResourceContents(
                    uri: 'ctx://schema-builder-instructions',
                    mimeType: 'application/json',
                    text: $response,
                ),
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Error generating configuration', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new ReadResourceResult([
                new TextResourceContents(
                    uri: 'ctx://schema-builder-instructions',
                    mimeType: 'application/json',
                    text: 'Error: ' . $e->getMessage(),
                ),
            ]);
        }
    }
}
