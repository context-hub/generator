<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Source\GitDiff\Fetcher;

/**
 * Service for parsing git commit range aliases and expressions
 */
final readonly class CommitRangeParser
{
    /**
     * Predefined commit range aliases for easier configuration
     */
    private const array COMMIT_RANGE_PRESETS = [
        // Basic ranges
        'last' => 'HEAD~1..HEAD',
        'last-2' => 'HEAD~2..HEAD',
        'last-3' => 'HEAD~3..HEAD',
        'last-5' => 'HEAD~5..HEAD',
        'last-10' => 'HEAD~10..HEAD',

        // Time-based ranges
        'today' => 'HEAD@{0:00:00}..HEAD',
        'last-24h' => 'HEAD@{24.hours.ago}..HEAD',
        'yesterday' => 'HEAD@{1.days.ago}..HEAD@{0.days.ago}',
        'last-week' => 'HEAD@{1.week.ago}..HEAD',
        'last-2weeks' => 'HEAD@{2.weeks.ago}..HEAD',
        'last-month' => 'HEAD@{1.month.ago}..HEAD',
        'last-quarter' => 'HEAD@{3.months.ago}..HEAD',
        'last-year' => 'HEAD@{1.year.ago}..HEAD',

        // Special cases
        'unstaged' => 'unstaged', // Special case handled in the fetcher
        'staged' => 'staged', // Changes staged but not committed
        'wip' => 'HEAD~1..HEAD', // Same as 'last' but semantically for WIP commits

        // Branch comparison patterns
        'main-diff' => 'main..HEAD', // Difference between main and current branch
        'master-diff' => 'master..HEAD', // Difference between master and current branch
        'develop-diff' => 'develop..HEAD', // Difference between develop and current branch

        // Stash related presets
        'stash' => 'stash@{0}', // Latest stash
        'stash-last' => 'stash@{0}', // Latest stash (alias)
        'stash-1' => 'stash@{1}', // Second most recent stash
        'stash-2' => 'stash@{2}', // Third most recent stash
        'stash-3' => 'stash@{3}', // Fourth most recent stash
        'stash-all' => 'stash@{0}..stash@{100}', // All stashes (up to 100)
        'stash-latest-2' => 'stash@{0}..stash@{1}', // Latest 2 stashes
        'stash-latest-3' => 'stash@{0}..stash@{2}', // Latest 3 stashes
        'stash-latest-5' => 'stash@{0}..stash@{4}', // Latest 5 stashes
        'stash-before-pull' => 'stash@{/before pull}', // Stash with "before pull" in message
        'stash-wip' => 'stash@{/WIP}', // Stash with "WIP" in message
        'stash-untracked' => 'stash@{/untracked}', // Stash with "untracked" in message
        'stash-index' => 'stash@{/index}', // Stash with "index" in message
    ];

    /**
     * Resolve a commit range expression, which can be an alias, specific commit hash, or a full range
     *
     * @param string|array<string> $commitRange The commit range expression(s) to resolve
     * @return string|array<string> The resolved commit range(s)
     */
    public function resolve(string|array $commitRange): string|array
    {
        if (\is_array($commitRange)) {
            return \array_map(fn(string $range): string => $this->resolveExpression($range), $commitRange);
        }

        return $this->resolveExpression($commitRange);
    }

    /**
     * Parse a commit expression which can be in various formats:
     * - A preset alias: 'last', 'last-week', 'stash', etc.
     * - A commit hash: 'abc1234'
     * - A tag: 'v1.0.0'
     * - A branch: 'feature/branch'
     * - A full range: 'commit1..commit2'
     * - A specific file at a commit: 'commit:path/to/file.php'
     * - A stash reference: 'stash@{0}', 'stash@{/message}'
     */
    public function resolveExpression(string $expression): string
    {
        // If it's a predefined alias, resolve it
        if (isset(self::COMMIT_RANGE_PRESETS[$expression])) {
            return self::COMMIT_RANGE_PRESETS[$expression];
        }

        // Handle specific commit patterns
        // 1. Check for a specific commit hash (must be at least 7 chars)
        if (\preg_match('/^[0-9a-f]{7,40}$/', $expression)) {
            return $expression . '~1..' . $expression;
        }

        // 2. Check for specific commit with file pattern: abc1234:path/to/file.php
        if (\preg_match('/^([0-9a-f]{7,40}):(.+)$/', $expression, $matches)) {
            return $matches[1] . ' -- ' . $matches[2];
        }

        // 3. Check for tag or branch comparison: tag:v1.0.0 or branch:feature/name
        if (\preg_match('/^(tag|branch):(.+)$/', $expression, $matches)) {
            $ref = $matches[2];
            return $ref . '~1..' . $ref;
        }

        // 4. Check for branch comparison: main..feature
        if (\preg_match('/^([^\.]+)\.\.([^\.]+)$/', $expression)) {
            // This is already a valid git range - return as is
            return $expression;
        }

        // 5. Check for 'since:' pattern to specify a starting point
        if (\preg_match('/^since:(.+)$/', $expression, $matches)) {
            return $matches[1] . '..HEAD';
        }

        // 6. Handle stash pattern: stash@{n} or stash@{/message}
        if (\preg_match('/^stash@\{.+\}$/', $expression)) {
            // This is already a valid stash reference - return as is
            return $expression;
        }

        // 7. Handle numeric stash pattern: stash:0, stash:1, etc.
        if (\preg_match('/^stash:(\d+)$/', $expression, $matches)) {
            return 'stash@{' . $matches[1] . '}';
        }

        // 8. Handle stash message pattern: stash:/message
        if (\preg_match('/^stash:\/(.+)$/', $expression, $matches)) {
            return 'stash@{/' . $matches[1] . '}';
        }

        // Handle ISO-8601 date format: @2023-01-15 or date:2023-01-15
        if (\preg_match('/^(?:@|date:)(\d{4}-\d{2}-\d{2})$/', $expression, $matches)) {
            return '--since=' . $matches[1] . ' --until=' . $matches[1] . ' 23:59:59';
        }

        // If nothing matches and it's not a recognized pattern, return as is
        // (let git decide if it's valid)
        return $expression;
    }
}
