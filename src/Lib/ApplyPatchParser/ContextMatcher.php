<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\ApplyPatchParser;

use Butschster\ContextGenerator\Lib\ApplyPatchParser\Matcher\ExactMatcher;
use Butschster\ContextGenerator\Lib\ApplyPatchParser\Matcher\MatcherInterface;
use Butschster\ContextGenerator\Lib\ApplyPatchParser\Matcher\MatchResult;
use Butschster\ContextGenerator\Lib\ApplyPatchParser\Matcher\UnicodeMatcher;
use Butschster\ContextGenerator\Lib\ApplyPatchParser\Matcher\WhitespaceTolerantMatcher;

final readonly class ContextMatcher
{
    /**
     * @var MatcherInterface[]
     */
    private array $matchers;

    public function __construct()
    {
        $this->matchers = [
            new ExactMatcher(),
            new WhitespaceTolerantMatcher(),
            new UnicodeMatcher(),
        ];
    }

    public function findBestMatch(
        array $contentLines,
        string $contextMarker,
        ChangeChunkConfig $config,
    ): MatchResult {
        foreach ($this->matchers as $matcher) {
            $result = $matcher->findMatch($contentLines, $contextMarker, $config->maxSearchLines);

            if ($result->isAcceptable($config->minConfidence)) {
                return $result;
            }
        }

        return MatchResult::notFound('No suitable match found with minimum confidence');
    }

    public function findAllMatches(
        array $contentLines,
        string $contextMarker,
        ChangeChunkConfig $config,
    ): array {
        $matches = [];

        foreach ($this->matchers as $matcher) {
            $result = $matcher->findMatch($contentLines, $contextMarker, $config->maxSearchLines);
            $matches[] = $result;
        }

        return $matches;
    }
}
