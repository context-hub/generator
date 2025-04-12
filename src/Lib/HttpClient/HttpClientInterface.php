<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Lib\HttpClient;

use Butschster\ContextGenerator\Lib\HttpClient\Exception\HttpException;

interface HttpClientInterface
{
    /**
     * Send a GET request to the specified URL
     *
     * @param string $url The URL to request
     * @param array<string, string> $headers Optional request headers
     * @return HttpResponse The response object
     *
     * @throws HttpException If the request fails
     */
    public function get(string $url, array $headers = []): HttpResponse;

    /**
     * Send a POST request to the specified URL
     *
     * @param string $url The URL to request
     * @param array<string, string> $headers Optional request headers
     * @param string|null $body Optional request body
     * @return HttpResponse The response object
     *
     * @throws HttpException If the request fails
     */
    public function post(string $url, array $headers = [], ?string $body = null): HttpResponse;

    /**
     * Send a request to the specified URL and follow redirects if needed
     *
     * @param string $url The URL to request
     * @param array<string, string> $headers Optional request headers
     * @return HttpResponse The final response after following redirects
     *
     * @throws HttpException If the request fails
     */
    public function getWithRedirects(string $url, array $headers = []): HttpResponse;
}
