<?php

declare(strict_types=1);

namespace Tests\ConfigLoader\Import;

use Butschster\ContextGenerator\Config\Import\PathMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathMatcher::class)]
final class PathMatcherTest extends TestCase
{
    #[Test]
    public function it_should_detect_wildcards_in_paths(): void
    {
        $this->assertTrue(PathMatcher::containsWildcard('*.php'));
        $this->assertTrue(PathMatcher::containsWildcard('src/*.php'));
        $this->assertTrue(PathMatcher::containsWildcard('src/**.php'));
        $this->assertTrue(PathMatcher::containsWildcard('src/?onfig.php'));
        $this->assertTrue(PathMatcher::containsWildcard('src/[abc].php'));
        $this->assertTrue(PathMatcher::containsWildcard('src/{config,settings}.php'));

        $this->assertFalse(PathMatcher::containsWildcard('src/config.php'));
        $this->assertFalse(PathMatcher::containsWildcard('/absolute/path/to/file.php'));
    }

    #[Test]
    public function it_should_match_simple_patterns(): void
    {
        $matcher = new PathMatcher('*.php');

        $this->assertTrue($matcher->isMatch('file.php'));
        $this->assertTrue($matcher->isMatch('config.php'));
        $this->assertFalse($matcher->isMatch('file.txt'));
        $this->assertFalse($matcher->isMatch('dir/file.php')); // * doesn't match directory separators
    }

    #[Test]
    public function it_should_match_directory_patterns(): void
    {
        $matcher = new PathMatcher('src/*.php');

        $this->assertTrue($matcher->isMatch('src/file.php'));
        $this->assertFalse($matcher->isMatch('file.php'));
        $this->assertFalse($matcher->isMatch('src/subdir/file.php'));
    }

    #[Test]
    public function it_should_match_recursive_patterns(): void
    {
        $matcher = new PathMatcher('src/**.php');

        $this->assertTrue($matcher->isMatch('src/file.php'));
        $this->assertTrue($matcher->isMatch('src/subdir/file.php'));
        $this->assertTrue($matcher->isMatch('src/deep/nested/file.php'));
        $this->assertFalse($matcher->isMatch('file.php'));
        $this->assertFalse($matcher->isMatch('other/file.php'));
    }

    #[Test]
    public function it_should_match_question_mark_patterns(): void
    {
        $matcher = new PathMatcher('file.?hp');

        $this->assertTrue($matcher->isMatch('file.php'));
        $this->assertTrue($matcher->isMatch('file.xhp'));
        $this->assertFalse($matcher->isMatch('file.txt'));
        $this->assertFalse($matcher->isMatch('file.phpp'));
    }

    #[Test]
    public function it_should_match_character_class_patterns(): void
    {
        $matcher = new PathMatcher('file.[cp]hp');

        $this->assertTrue($matcher->isMatch('file.php'));
        $this->assertTrue($matcher->isMatch('file.chp'));
        $this->assertFalse($matcher->isMatch('file.xhp'));
    }

    #[Test]
    public function it_should_match_negated_character_class_patterns(): void
    {
        $matcher = new PathMatcher('file.[^cp]hp');

        $this->assertTrue($matcher->isMatch('file.xhp'));
        $this->assertFalse($matcher->isMatch('file.php'));
        $this->assertFalse($matcher->isMatch('file.chp'));
    }

    #[Test]
    public function it_should_match_alternation_patterns(): void
    {
        $matcher = new PathMatcher('src/{config,settings}.php');

        $this->assertTrue($matcher->isMatch('src/config.php'));
        $this->assertTrue($matcher->isMatch('src/settings.php'));
        $this->assertFalse($matcher->isMatch('src/other.php'));
    }

    #[Test]
    public function it_should_handle_escaped_characters(): void
    {
        $matcher = new PathMatcher('file\\*.php');

        $this->assertTrue($matcher->isMatch('file*.php'));
        $this->assertFalse($matcher->isMatch('fileX.php'));
    }

    #[Test]
    public function it_should_return_original_pattern(): void
    {
        $pattern = 'src/*.php';
        $matcher = new PathMatcher($pattern);

        $this->assertSame($pattern, $matcher->getPattern());
    }

    #[Test]
    public function it_should_return_regex_pattern(): void
    {
        $matcher = new PathMatcher('src/*.php');

        $this->assertStringContainsString('[^/]*', $matcher->getRegex());
        $this->assertStringStartsWith('~^', $matcher->getRegex());
        $this->assertStringEndsWith('$~', $matcher->getRegex());
    }
}
