<?php

declare(strict_types=1);

namespace Tests\Feature\Console\McpConfig;

use PHPUnit\Framework\Attributes\Test;
use Spiral\Console\Console;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\QuestionHelper;
use Tests\Feature\Console\ConsoleTestCase;

final class McpConfigInteractiveTest extends ConsoleTestCase
{
    private function runInteractive(array $answers, array $args = []): string
    {
        $console = $this->getConsole();

        // Prepare interactive input with a stream for answers
        $argv = ['console', 'mcp:config', '--interactive'];
        foreach ($args as $k => $v) {
            if (\is_bool($v)) {
                if ($v === true) {
                    $argv[] = $k;
                }
            } else {
                $argv[] = $k . '=' . $v;
            }
        }

        $input = new ArgvInput($argv);
        $input->setInteractive(true);

        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, implode("\n", $answers) . "\n");
        rewind($stream);
        $input->setStream($stream);

        // Use SymfonyStyle so BaseCommand assertion passes; capture output via buffer
        $buffer = new BufferedOutput();
        /** @psalm-suppress ArgumentTypeCoercion */
        $buffer->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
        $style = new SymfonyStyle($input, $buffer);

        // Avoid stty-based interactive handling on non-tty streams
        QuestionHelper::disableStty();

        // Run via Console but avoid InputProxy by passing null command name
        $prev = getenv('SHELL_INTERACTIVE');
        putenv('SHELL_INTERACTIVE=1');
        try {
            $console->run(null, $input, $style);
        } finally {
            if ($prev === false) {
                putenv('SHELL_INTERACTIVE');
            } else {
                putenv('SHELL_INTERACTIVE=' . $prev);
            }
        }

        return $buffer->fetch();
    }

    #[Test]
    public function interactive_accepts_lowercase_codex(): void
    {
        // Provide: client = "codex", then accept defaults for the rest
        $out = $this->runInteractive([
            'codex', // Which MCP client
            '',      // Project access -> default (global)
            '',      // Env vars? default (no)
        ]);

        $this->assertStringContainsString('Generated Configuration', $out);
        $this->assertStringContainsString('Codex configuration (TOML):', $out);
        $this->assertStringContainsString('[mcp_servers.ctx]', $out);
        $this->assertStringContainsString('command = "ctx"', $out);
        $this->assertStringContainsString('args = ["server"]', $out);
    }
}
