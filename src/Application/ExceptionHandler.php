<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Application;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Attribute\Singleton;
use Spiral\Exceptions\ExceptionHandlerInterface;
use Spiral\Exceptions\ExceptionRendererInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Exceptions\Renderer\ConsoleRenderer;
use Spiral\Exceptions\Verbosity;

#[Singleton]
final class ExceptionHandler implements ExceptionHandlerInterface, LoggerAwareInterface
{
    public ?Verbosity $verbosity = Verbosity::BASIC;
    protected mixed $output = null;
    private readonly \Spiral\Exceptions\ExceptionHandler $handler;

    public function __construct(
        #[Proxy] LoggerInterface $logger,
    ) {
        $this->setLogger($logger);
        $this->handler = new \Spiral\Exceptions\ExceptionHandler();
        $this->bootBasicHandlers();
    }

    public function getRenderer(?string $format = null): ?ExceptionRendererInterface
    {
        return $this->handler->getRenderer($format);
    }

    public function setLogger(LoggerInterface $logger): void {}

    public function register(): void
    {
        $this->handler->register();
    }

    public function render(
        \Throwable $exception,
        ?Verbosity $verbosity = Verbosity::BASIC,
        ?string $format = null,
    ): string {
        return $this->handler->render($exception, $verbosity, $format);
    }

    public function canRender(string $format): bool
    {
        return $this->handler->canRender($format);
    }

    public function report(\Throwable $exception): void
    {
        $this->handler->report($exception);
    }

    public function handleGlobalException(\Throwable $e): void
    {
        $this->handler->handleGlobalException($e);
    }

    /**
     * Add renderer to the beginning of the renderers list
     */
    public function addRenderer(ExceptionRendererInterface $renderer): void
    {
        $this->handler->addRenderer($renderer);
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    public function dontReport(string $exception): void
    {
        $this->handler->dontReport($exception);
    }

    /**
     * @param ExceptionReporterInterface|\Closure(\Throwable):void $reporter
     */
    public function addReporter(ExceptionReporterInterface|\Closure $reporter): void
    {
        $this->handler->addReporter($reporter);
    }

    /**
     * @param resource $output
     */
    public function setOutput(mixed $output): void
    {
        $this->handler->setOutput($output);
    }

    protected function bootBasicHandlers(): void
    {
        $this->addRenderer(new ConsoleRenderer());
    }
}
