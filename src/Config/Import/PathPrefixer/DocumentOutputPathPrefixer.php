<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Config\Import\PathPrefixer;

/**
 * Applies path prefix to document output paths
 *
 * The pathPrefix specifies a subdirectory to prepend to all document outputPath values
 * in the imported configuration.
 *
 * Example:
 * - Document has outputPath: 'docs/api.md'
 * - Import has pathPrefix: 'api/v1'
 * - Resulting outputPath: 'api/v1/docs/api.md'
 */
final readonly class DocumentOutputPathPrefixer extends PathPrefixer
{
    /**
     * Apply path prefix to the output path of all documents
     */
    public function applyPrefix(array $config, string $pathPrefix): array
    {
        // Apply to document outputPath values
        if (isset($config['documents']) && \is_array($config['documents'])) {
            foreach ($config['documents'] as &$document) {
                if (isset($document['outputPath'])) {
                    $document['outputPath'] = $this->combinePaths($pathPrefix, $document['outputPath']);
                }
            }
        }

        return $config;
    }
}
