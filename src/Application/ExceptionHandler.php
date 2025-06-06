<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Exceptions\Attribute\NonReportable;
use Spiral\Exceptions\ExceptionHandlerInterface;
use Spiral\Exceptions\ExceptionRendererInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Exceptions\Renderer\ConsoleRenderer;
use Spiral\Exceptions\Verbosity;

final class ExceptionHandler implements ExceptionHandlerInterface, LoggerAwareInterface
{
    public ?Verbosity $verbosity = Verbosity::BASIC;

    /** @var array<int, ExceptionRendererInterface> */
    protected array $renderers = [];

    /** @var array<int, ExceptionReporterInterface|\Closure> */
    protected array $reporters = [];

    /**
     * @var array<class-string<\Throwable>>
     */
    protected array $nonReportableExceptions = [];

    protected mixed $output = null;
    private ExceptionHandlerInterface $handler;

    public function __construct(
        #[Proxy] LoggerInterface $logger,
    ) {
        $this->setLogger($logger);
        $this->bootBasicHandlers();
    }

    public function getRenderer(?string $format = null): ?ExceptionRendererInterface
    {
        if ($format !== null) {
            foreach ($this->renderers as $renderer) {
                if ($renderer->canRender($format)) {
                    return $renderer;
                }
            }
        }
        return \end($this->renderers) ?: null;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->handler = new \Spiral\Exceptions\ExceptionHandler();
    }

    public function register(): void
    {
        $this->handler->register();
    }

    public function render(
        \Throwable $exception,
        ?Verbosity $verbosity = Verbosity::BASIC,
        ?string $format = null,
    ): string {
        return (string) $this->getRenderer($format)?->render($exception, $verbosity ?? $this->verbosity, $format);
    }

    public function canRender(string $format): bool
    {
        return $this->getRenderer($format) !== null;
    }

    public function report(\Throwable $exception): void
    {
        if ($this->shouldNotReport($exception)) {
            return;
        }

        foreach ($this->reporters as $reporter) {
            try {
                if ($reporter instanceof ExceptionReporterInterface) {
                    $reporter->report($exception);
                } else {
                    $reporter($exception);
                }
            } catch (\Throwable) {
                // Do nothing
            }
        }
    }

    public function handleGlobalException(\Throwable $e): void
    {
        if (\in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
            $this->output ??= \defined('STDERR') ? \STDERR : \fopen('php://stderr', 'wb+');
            $format = 'cli';
        } else {
            $this->output ??= \defined('STDOUT') ? \STDOUT : \fopen('php://stdout', 'wb+');
            $format = 'html';
        }

        // we are safe to handle global exceptions (system level) with maximum verbosity
        $this->report($e);

        // There is possible an exception on the application termination
        try {
            \fwrite($this->output, $this->render($e, verbosity: $this->verbosity, format: $format));
        } catch (\Throwable) {
            $this->output = null;
        }
    }

    /**
     * Add renderer to the beginning of the renderers list
     */
    public function addRenderer(ExceptionRendererInterface $renderer): void
    {
        \array_unshift($this->renderers, $renderer);
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    public function dontReport(string $exception): void
    {
        $this->nonReportableExceptions[] = $exception;
    }

    /**
     * @param ExceptionReporterInterface|\Closure(\Throwable):void $reporter
     */
    public function addReporter(ExceptionReporterInterface|\Closure $reporter): void
    {
        $this->reporters[] = $reporter;
    }

    /**
     * @param resource $output
     */
    public function setOutput(mixed $output): void
    {
        $this->output = $output;
    }

    /**
     * Handle php shutdown and search for fatal errors.
     */
    protected function handleShutdown(): void
    {
        if (empty($error = \error_get_last())) {
            return;
        }

        try {
            $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        } catch (\Throwable $e) {
            $this->handleGlobalException($e);
        }
    }

    /**
     * Convert application error into exception.
     * Handler for the {@see \set_error_handler()}.
     * @throws \ErrorException
     */
    protected function handleError(
        int $errno,
        string $errstr,
        string $errfile = '',
        int $errline = 0,
    ): bool {
        if (!(\error_reporting() & $errno)) {
            return false;
        }

        throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
    }

    protected function bootBasicHandlers(): void
    {
        $this->addRenderer(new ConsoleRenderer());
    }

    protected function shouldNotReport(\Throwable $exception): bool
    {
        foreach ($this->nonReportableExceptions as $nonReportableException) {
            if ($exception instanceof $nonReportableException) {
                return true;
            }
        }

        $attribute = (new \ReflectionClass($exception))->getAttributes(NonReportable::class)[0] ?? null;

        return $attribute !== null;
    }
}
