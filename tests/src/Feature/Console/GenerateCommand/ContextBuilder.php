<?php

declare(strict_types=1);

namespace Tests\Feature\Console\GenerateCommand;

use Spiral\Console\Console;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ContextBuilder
{
    public function __construct(
        private Console $console,
        public int $defaultVerbosityLevel = OutputInterface::VERBOSITY_NORMAL,
    ) {}

    public function build(
        string $workDir,
        ?string $configPath = null,
        ?string $inlineJson = null,
        ?string $envFile = null,
        string $command = 'generate',
        bool $asJson = true,
    ): CompilingResult {
        $args = [];

        if ($configPath !== null) {
            $args['--config-file'] = $configPath;
        }

        if ($inlineJson !== null) {
            $args['--inline'] = $inlineJson;
        }

        if ($workDir !== null) {
            $args['--work-dir'] = $workDir;
        }

        if ($envFile !== null) {
            $args['--env'] = $envFile;
        }

        if ($asJson) {
            $args['--json'] = true;
        }

        $output = $this->runCommand(
            command: $command,
            args: $args,
        );

        $output = \trim($output);
        $data = \json_decode($output, true);

        if (!$data) {
            throw new \RuntimeException('Failed to decode JSON output: ' . \json_last_error_msg());
        }

        return new CompilingResult($data);
    }

    private function runCommand(
        string $command,
        array $args = [],
        ?OutputInterface $output = null,
        ?int $verbosityLevel = null,
    ): string {
        $input = new ArrayInput($args);
        $input->setInteractive(false);
        $output ??= new BufferedOutput();
        /** @psalm-suppress ArgumentTypeCoercion */
        $output->setVerbosity($verbosityLevel ?? $this->defaultVerbosityLevel);

        $this->console->run($command, $input, $output);

        return $output->fetch();
    }
}
