<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Url;

use Butschster\ContextGenerator\Lib\Content\ContentBuilderFactory;
use Butschster\ContextGenerator\Lib\Html\HtmlCleanerInterface;
use Butschster\ContextGenerator\Lib\Html\SelectorContentExtractorInterface;
use Butschster\ContextGenerator\Lib\HttpClient\HttpClientInterface;
use Butschster\ContextGenerator\Lib\HttpClient\HttpResponse;
use Butschster\ContextGenerator\Lib\Variable\VariableReplacementProcessor;
use Butschster\ContextGenerator\Lib\Variable\VariableResolver;
use Butschster\ContextGenerator\Modifier\ModifiersApplierInterface;
use Butschster\ContextGenerator\Source\SourceInterface;
use Butschster\ContextGenerator\Source\Url\UrlSource;
use Butschster\ContextGenerator\Source\Url\UrlSourceFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(UrlSourceFetcher::class)]
class UrlSourceFetcherTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private VariableResolver $variableResolver;
    private HtmlCleanerInterface&MockObject $cleaner;
    private SelectorContentExtractorInterface&MockObject $selectorExtractor;
    private ContentBuilderFactory $builderFactory;
    private LoggerInterface&MockObject $logger;
    private ModifiersApplierInterface&MockObject $modifiersApplier;
    private UrlSourceFetcher $fetcher;

    #[Test]
    public function it_should_support_url_sources(): void
    {
        $urlSource = new UrlSource(urls: ['https://example.com']);

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Checking if source is supported', [
                'sourceType' => UrlSource::class,
                'isSupported' => true,
            ]);

        $this->assertTrue($this->fetcher->supports(source: $urlSource));
    }

    #[Test]
    public function it_should_not_support_other_sources(): void
    {
        $otherSource = $this->createMock(SourceInterface::class);

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Checking if source is supported', [
                'sourceType' => $otherSource::class,
                'isSupported' => false,
            ]);

        $this->assertFalse($this->fetcher->supports(source: $otherSource));
    }

    #[Test]
    public function it_should_throw_exception_for_invalid_source_type(): void
    {
        $invalidSource = $this->createMock(SourceInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source must be an instance of UrlSource');

        $this->fetcher->fetch(source: $invalidSource, modifiersApplier: $this->modifiersApplier);
    }

    #[Test]
    public function it_should_fetch_single_url_content(): void
    {
        // Arrange
        $url = 'https://example.com';
        $htmlContent = '<html><body><div>Test content</div></body></html>';
        $cleanedContent = '<div>Test content</div>';
        $modifiedContent = '<div>Modified test content</div>';

        $urlSource = new UrlSource(
            urls: [$url],
            description: 'Test description',
        );

        // Setup HTTP client to return success response
        $response = new HttpResponse(
            statusCode: 200,
            body: $htmlContent,
            headers: ['Content-Type' => 'text/html'],
        );

        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->with($url, ['User-Agent' => 'Test User Agent'])
            ->willReturn($response);

        // Setup cleaner
        $this->cleaner
            ->expects($this->once())
            ->method('clean')
            ->with($htmlContent)
            ->willReturn($cleanedContent);

        // Setup modifiers applier
        $this->modifiersApplier
            ->expects($this->once())
            ->method('apply')
            ->with($cleanedContent, $url)
            ->willReturn($modifiedContent);

        // Act
        $result = $this->fetcher->fetch(source: $urlSource, modifiersApplier: $this->modifiersApplier);

        // Assert
        $this->assertStringContainsString('Test description', $result);
        $this->assertStringContainsString("URL: {$url}", $result);
        $this->assertStringContainsString("END OF URL: {$url}", $result);
        $this->assertStringContainsString($modifiedContent, $result);
    }

    #[Test]
    public function it_should_extract_content_using_selector(): void
    {
        // Arrange
        $url = 'https://example.com';
        $selector = '.content';
        $htmlContent = '<html><body><div class="content">Selected content</div></body></html>';
        $extractedContent = '<div class="content">Selected content</div>';
        $cleanedContent = 'Selected content';
        $modifiedContent = 'Modified selected content';

        $urlSource = new UrlSource(
            urls: [$url],
            selector: $selector,
        );

        // HTTP client mocks
        $response = new HttpResponse(
            statusCode: 200,
            body: $htmlContent,
            headers: ['Content-Type' => 'text/html'],
        );

        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn($response);

        // Selector extractor mocks
        $this->selectorExtractor
            ->expects($this->once())
            ->method('extract')
            ->with($htmlContent, $selector)
            ->willReturn($extractedContent);

        // Other mocks
        $this->cleaner->method('clean')->willReturn($cleanedContent);
        $this->modifiersApplier->method('apply')->willReturn($modifiedContent);

        // Act
        $result = $this->fetcher->fetch(source: $urlSource, modifiersApplier: $this->modifiersApplier);

        // Assert
        $this->assertStringContainsString("URL: {$url} (selector: {$selector})", $result);
        $this->assertStringContainsString($modifiedContent, $result);
    }

    #[Test]
    public function it_should_handle_empty_selector_result(): void
    {
        // Arrange
        $url = 'https://example.com';
        $selector = '.non-existent';
        $htmlContent = '<html><body><div>Content</div></body></html>';

        $urlSource = new UrlSource(
            urls: [$url],
            selector: $selector,
        );

        // HTTP client mocks
        $response = new HttpResponse(
            statusCode: 200,
            body: $htmlContent,
            headers: ['Content-Type' => 'text/html'],
        );

        $this->httpClient->method('get')->willReturn($response);

        // Selector extractor mocks - return empty result
        $this->selectorExtractor
            ->expects($this->once())
            ->method('extract')
            ->with($htmlContent, $selector)
            ->willReturn('');

        // Act
        $result = $this->fetcher->fetch(source: $urlSource, modifiersApplier: $this->modifiersApplier);

        // Assert
        $this->assertStringContainsString("URL: {$url}", $result);
        $this->assertStringContainsString("Warning: Selector '{$selector}' didn't match any content", $result);
    }

    #[Test]
    public function it_should_handle_http_error_responses(): void
    {
        // Arrange
        $url = 'https://example.com';

        $urlSource = new UrlSource(
            urls: [$url],
        );

        // HTTP client mocks - return error response
        $response = new HttpResponse(
            statusCode: 404,
            body: '',
            headers: ['Content-Type' => 'text/html'],
        );

        $this->httpClient->method('get')->willReturn($response);

        // Act
        $result = $this->fetcher->fetch(source: $urlSource, modifiersApplier: $this->modifiersApplier);

        // Assert
        $this->assertStringContainsString("URL: {$url}", $result);
        $this->assertStringContainsString("Error: HTTP status code 404", $result);
    }

    #[Test]
    public function it_should_handle_exceptions_during_http_request(): void
    {
        // Arrange
        $url = 'https://example.com';
        $exceptionMessage = 'Connection timeout';

        $urlSource = new UrlSource(
            urls: [$url],
        );

        // HTTP client mocks - throw exception
        $this->httpClient
            ->method('get')
            ->willThrowException(new \RuntimeException(message: $exceptionMessage));

        // Log the error
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Error processing URL',
                $this->callback(
                    static fn($context) => $context['url'] === $url && $context['error'] === $exceptionMessage,
                ),
            );

        // Act
        $result = $this->fetcher->fetch(source: $urlSource, modifiersApplier: $this->modifiersApplier);

        // Assert
        $this->assertStringContainsString("URL: {$url}", $result);
        $this->assertStringContainsString("Error: {$exceptionMessage}", $result);
    }

    #[Test]
    public function it_should_process_multiple_urls(): void
    {
        // Arrange
        $urls = ['https://example.com', 'https://example.org'];

        $urlSource = new UrlSource(
            urls: $urls,
        );

        // HTTP client mocks
        $response = new HttpResponse(
            statusCode: 200,
            body: '<html><body>Content</body></html>',
            headers: ['Content-Type' => 'text/html'],
        );

        // Expect two calls to get() method
        $this->httpClient
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturn($response);

        // Cleaner and modifier mocks
        $this->cleaner->method('clean')->willReturn('Cleaned content');
        $this->modifiersApplier->method('apply')->willReturn('Modified content');

        // Act
        $result = $this->fetcher->fetch(source: $urlSource, modifiersApplier: $this->modifiersApplier);

        // Assert
        $this->assertStringContainsString("URL: https://example.com", $result);
        $this->assertStringContainsString("URL: https://example.org", $result);
        $this->assertStringContainsString("Modified content", $result);
        // Check if each URL appears twice (once in URL comment, once in END OF URL comment)
        $this->assertEquals(2, \substr_count(haystack: $result, needle: "https://example.com"));
        $this->assertEquals(2, \substr_count(haystack: $result, needle: "https://example.org"));
    }

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->variableResolver = new VariableResolver(
            processor: new VariableReplacementProcessor(),
        );
        $this->cleaner = $this->createMock(HtmlCleanerInterface::class);
        $this->selectorExtractor = $this->createMock(SelectorContentExtractorInterface::class);
        $this->builderFactory = new ContentBuilderFactory();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->modifiersApplier = $this->createMock(ModifiersApplierInterface::class);

        $this->fetcher = new UrlSourceFetcher(
            httpClient: $this->httpClient,
            defaultHeaders: ['User-Agent' => 'Test User Agent'],
            variableResolver: $this->variableResolver,
            cleaner: $this->cleaner,
            selectorExtractor: $this->selectorExtractor,
            builderFactory: $this->builderFactory,
            logger: $this->logger,
        );
    }
}
