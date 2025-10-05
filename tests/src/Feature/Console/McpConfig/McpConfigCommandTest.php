<?php

declare(strict_types=1);

namespace Tests\Feature\Console\McpConfig;

use PHPUnit\Framework\Attributes\Test;
use Spiral\Console\Console;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tests\Feature\Console\ConsoleTestCase;

final class McpConfigCommandTest extends ConsoleTestCase
{
    #[Test]
    public function claude_client_ouptuts_correct_json(): void
    {
        $out = $this->runMcpConfig(['--client' => 'claude']);

        $this->assertStringContainsString('Generated Configuration', $out);
        $this->assertStringContainsString('"mcpServers"', $out); // JSON config key for Claude Desktop
        $this->assertStringContainsString('Configuration file location:', $out);
    }

    #[Test]
    public function codex_client_outputs_toml_snippet(): void
    {
        $out = $this->runMcpConfig(['--client' => 'codex']);

        $this->assertStringContainsString('Generated Configuration', $out);
        $this->assertStringContainsString('Codex configuration (TOML format):', $out);
        $this->assertStringContainsString('[mcp_servers.ctx]', $out);
        $this->assertStringContainsString('command = ', $out);
        $this->assertStringContainsString('args = [', $out);
    }

    #[Test]
    public function cursor_client_uses_generic_renderer(): void
    {
        $out = $this->runMcpConfig(['--client' => 'cursor']);

        $this->assertStringContainsString('Generated Configuration', $out);
        $this->assertStringContainsString('Configuration type: Cursor MCP', $out);
        $this->assertStringContainsString('Add this to your Cursor MCP configuration:', $out);
        $this->assertStringContainsString('"command":', $out);
        $this->assertStringContainsString('"args": [', $out);
    }

    #[Test]
    public function generic_client_outputs_generic_configuration(): void
    {
        $out = $this->runMcpConfig(['--client' => 'generic']);

        $this->assertStringContainsString('Generated Configuration', $out);
        $this->assertStringContainsString('Configuration type: Generic MCP', $out);
        $this->assertStringContainsString('Generic MCP client configuration', $out);
    }

    private function runMcpConfig(array $args = [], ?int $verbosity = null): string
    {
        /** @var Console $console */
        $console = $this->getConsole();

        // Prepare explicit input/output so our BaseCommand receives SymfonyStyle
        $input = new ArrayInput($args);
        $input->setInteractive(false);

        $buffer = new BufferedOutput();
        /** @psalm-suppress ArgumentTypeCoercion */
        $buffer->setVerbosity($verbosity ?? OutputInterface::VERBOSITY_NORMAL);

        $style = new SymfonyStyle($input, $buffer);

        $console->run('mcp:config', $input, $style);

        return $buffer->fetch();
    }
}
