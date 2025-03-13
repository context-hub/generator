<?php

declare(strict_types=1);

namespace Tests\Fetcher;

use Butschster\ContextGenerator\Lib\Html\HtmlCleanerInterface;
use Butschster\ContextGenerator\Lib\Html\SelectorContentExtractorInterface;
use Butschster\ContextGenerator\Source\Url\UrlSource;
use Butschster\ContextGenerator\Source\Url\UrlSourceFetcher;
use Butschster\ContextGenerator\SourceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

class UrlSourceFetcherTest extends TestCase
{
    private UrlSourceFetcher $fetcher;
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private UriFactoryInterface $uriFactory;
    private HtmlCleanerInterface $cleaner;
    private SelectorContentExtractorInterface $selectorExtractor;

    #[Test]
    public function it_should_support_url_source(): void
    {
        $source = new UrlSource(urls: ['https://example.com']);
        $this->assertTrue($this->fetcher->supports($source));
    }

    #[Test]
    public function it_should_not_support_other_sources(): void
    {
        $source = $this->createMock(SourceInterface::class);
        $this->assertFalse($this->fetcher->supports($source));
    }

    #[Test]
    public function it_should_throw_exception_for_invalid_source_type(): void
    {
        $source = $this->createMock(SourceInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source must be an instance of UrlSource');

        $this->fetcher->fetch($source);
    }

    #[Test]
    public function it_should_fetch_content_from_url_source(): void
    {
        $url = 'https://example.com';
        $htmlContent = '<html><body><h1>Example Domain</h1><p>This is a test page.</p></body></html>';
        $cleanedContent = 'Example Domain\n\nThis is a test page.';

        // Create real URL source
        $source = new UrlSource(urls: [$url]);

        // Mock URI factory
        $uri = $this->createMock(UriInterface::class);
        $this->uriFactory
            ->expects($this->once())
            ->method('createUri')
            ->with($url)
            ->willReturn($uri);

        // Mock request factory
        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with('GET', $uri)
            ->willReturn($request);

        // Mock request headers - the actual number may vary depending on implementation
        $request
            ->expects($this->any())
            ->method('withHeader')
            ->willReturnSelf();

        // Mock response
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        // Mock response body
        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->expects($this->once())
            ->method('__toString')
            ->willReturn($htmlContent);

        $response
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        // Mock HTTP client
        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        // Create a mock HtmlCleanerInterface
        $cleaner = $this->createMock(HtmlCleanerInterface::class);
        $cleaner
            ->expects($this->once())
            ->method('clean')
            ->with($htmlContent)
            ->willReturn($cleanedContent);

        // Create a fetcher with the mock cleaner
        $fetcher = new UrlSourceFetcher(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            uriFactory: $this->uriFactory,
            defaultHeaders: [
                'User-Agent' => 'Test User Agent',
                'Accept' => 'text/html',
            ],
            cleaner: $cleaner,
            selectorExtractor: $this->selectorExtractor,
        );

        $result = $fetcher->fetch($source);

        $expected = "// URL: {$url}" . PHP_EOL .
            $cleanedContent . PHP_EOL . PHP_EOL .
            "// END OF URL: {$url}" . PHP_EOL .
            '----------------------------------------------------------' . PHP_EOL;

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_should_handle_http_error_responses(): void
    {
        $url = 'https://example.com';

        // Create real URL source
        $source = new UrlSource(urls: [$url]);

        // Mock URI factory
        $uri = $this->createMock(UriInterface::class);
        $this->uriFactory
            ->expects($this->once())
            ->method('createUri')
            ->with($url)
            ->willReturn($uri);

        // Mock request factory
        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with('GET', $uri)
            ->willReturn($request);

        // Mock request headers - the actual number may vary depending on implementation
        $request
            ->expects($this->any())
            ->method('withHeader')
            ->willReturnSelf();

        // Mock response with error status
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(404);

        // Mock HTTP client
        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        // Create a new fetcher instance for this test
        $fetcher = new UrlSourceFetcher(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            uriFactory: $this->uriFactory,
            defaultHeaders: ['User-Agent' => 'Test User Agent'],
            cleaner: $this->cleaner,
        );

        $result = $fetcher->fetch($source);

        $expected = "// URL: {$url}" . PHP_EOL .
            "// Error: HTTP status code 404" . PHP_EOL .
            '----------------------------------------------------------' . PHP_EOL;

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_should_handle_selector_extraction(): void
    {
        $url = 'https://example.com';
        $selector = '.content';
        $htmlContent = '<html><body><div class="content"><h1>Example Domain</h1><p>This is a test page.</p></div></body></html>';
        $extractedContent = '<div class="content"><h1>Example Domain</h1><p>This is a test page.</p></div>';
        $cleanedContent = 'Example Domain\n\nThis is a test page.';

        // Create real URL source with selector
        $source = new UrlSource(urls: [$url], selector: $selector);

        // Mock URI factory
        $uri = $this->createMock(UriInterface::class);
        $this->uriFactory
            ->expects($this->once())
            ->method('createUri')
            ->with($url)
            ->willReturn($uri);

        // Mock request factory
        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with('GET', $uri)
            ->willReturn($request);

        // Mock request headers - the actual number may vary depending on implementation
        $request
            ->expects($this->any())
            ->method('withHeader')
            ->willReturnSelf();

        // Mock response
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        // Mock response body
        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->expects($this->once())
            ->method('__toString')
            ->willReturn($htmlContent);

        $response
            ->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        // Mock HTTP client
        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        // Create a mock HtmlCleanerInterface
        $cleaner = $this->createMock(HtmlCleanerInterface::class);
        $cleaner
            ->expects($this->once())
            ->method('clean')
            ->with($extractedContent)
            ->willReturn($cleanedContent);

        // Configure the selector extractor mock
        $this->selectorExtractor
            ->expects($this->once())
            ->method('extract')
            ->with($htmlContent, $selector)
            ->willReturn($extractedContent);

        // Create a fetcher with the configured mocks
        $fetcher = new UrlSourceFetcher(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            uriFactory: $this->uriFactory,
            defaultHeaders: ['User-Agent' => 'Test User Agent'],
            cleaner: $cleaner,
            selectorExtractor: $this->selectorExtractor,
        );

        $result = $fetcher->fetch($source);

        $expected = "// URL: {$url} (selector: {$selector})" . PHP_EOL .
            $cleanedContent . PHP_EOL . PHP_EOL .
            "// END OF URL: {$url}" . PHP_EOL .
            '----------------------------------------------------------' . PHP_EOL;

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_should_throw_exception_when_http_client_not_provided(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('To use Url source you need to install PSR-18 HTTP client');

        $fetcher = new UrlSourceFetcher(); // No dependencies provided
        $source = new UrlSource(urls: ['https://example.com']);

        $fetcher->fetch($source);
    }

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->uriFactory = $this->createMock(UriFactoryInterface::class);
        $this->cleaner = $this->createMock(HtmlCleanerInterface::class);
        $this->selectorExtractor = $this->createMock(SelectorContentExtractorInterface::class);

        $this->fetcher = new UrlSourceFetcher(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            uriFactory: $this->uriFactory,
            defaultHeaders: [
                'User-Agent' => 'Test User Agent',
                'Accept' => 'text/html',
            ],
            cleaner: $this->cleaner,
            selectorExtractor: $this->selectorExtractor,
        );
    }
}
