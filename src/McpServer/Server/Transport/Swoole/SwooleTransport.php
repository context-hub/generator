<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server\Transport\Swoole;

use Butschster\ContextGenerator\McpServer\Config\SwooleTransportConfig;
use Butschster\ContextGenerator\McpServer\Server\MessageParser;
use Mcp\Server\Transport\BufferedTransport;
use Mcp\Server\Transport\Http\HttpMessage;
use Mcp\Server\Transport\Http\HttpSession;
use Mcp\Server\Transport\Http\MessageQueue;
use Mcp\Server\Transport\Http\SessionStoreInterface;
use Mcp\Server\Transport\SessionAwareTransport;
use Mcp\Shared\BaseSession;
use Mcp\Shared\McpError;
use Mcp\Types\JSONRPCBatchResponse;
use Mcp\Types\JSONRPCError;
use Mcp\Types\JsonRpcMessage;
use Mcp\Types\JSONRPCResponse;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Advanced Swoole-based HTTP transport with SSE support
 */
final class SwooleTransport implements BufferedTransport, SessionAwareTransport
{
    private readonly MessageQueue $messageQueue;

    /** @var array<string, HttpSession> */
    private array $sessions = [];

    private ?string $currentSessionId = null;
    private bool $isStarted = false;
    private int $lastSessionCleanup = 0;
    private int $sessionCleanupInterval = 300;

    /**
     * @var array<string, callable>
     */
    private array $eventListeners = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MessageParser $messageParser,
        private readonly SwooleTransportConfig $config,
        private readonly SessionStoreInterface $sessionStore,
        private readonly SseConnectionManager $sseManager,
        private readonly int $sessionTimeout = 300,
    ) {
        $this->messageQueue = new MessageQueue(1000);
    }

    public function start(): void
    {
        if ($this->isStarted) {
            throw new \RuntimeException('Transport already started');
        }

        $this->isStarted = true;
        $this->lastSessionCleanup = \time();

        $this->logger->info('Swoole transport started', [
            'sse_enabled' => $this->config->sseEnabled,
            'max_connections' => $this->config->maxSseConnections,
        ]);
    }

    public function stop(): void
    {
        if (!$this->isStarted) {
            return;
        }

        // Close all SSE connections
        $this->sseManager->cleanup();

        // Close all sessions
        foreach ($this->sessions as $session) {
            $session->expire();
        }

        // Clear message queues
        $this->messageQueue->clear();

        $this->isStarted = false;

        $this->logger->info('Swoole transport stopped');
    }

    public function readMessage(): ?JsonRpcMessage
    {
        if (!$this->isStarted) {
            throw new \RuntimeException('Transport not started');
        }

        $this->cleanupExpiredSessions();
        return $this->messageQueue->dequeueIncoming();
    }

    public function writeMessage(JsonRpcMessage $message): void
    {
        if (!$this->isStarted) {
            throw new \RuntimeException('Transport not started');
        }

        // If we have a current session, use that
        if ($this->currentSessionId !== null) {
            $this->messageQueue->queueOutgoing($message, $this->currentSessionId);

            // Also send to SSE connections if available
            if ($this->config->sseEnabled) {
                $this->sseManager->sendToSession($this->currentSessionId, $message->message);
            }
            return;
        }

        // Otherwise, try to determine the target session based on message type
        $innerMessage = $message->message;

        if ($innerMessage instanceof JSONRPCResponse || $innerMessage instanceof JSONRPCError) {
            $this->messageQueue->queueResponse($message);
        } elseif ($innerMessage instanceof JSONRPCBatchResponse) {
            $this->messageQueue->queueResponse($message);
        } else {
            throw new \RuntimeException('Cannot route message: no target session');
        }
    }

    public function flush(): void
    {
        // Send heartbeat to SSE connections
        if ($this->config->sseEnabled) {
            $this->sseManager->sendHeartbeat();
        }
    }

    public function attachSession(BaseSession $session): void
    {
        if (!$session instanceof HttpSession) {
            throw new \InvalidArgumentException('Only HttpSession instances are supported');
        }

        $this->sessions[$session->getId()] = $session;
        $this->currentSessionId = $session->getId();

        $this->logger->debug('Session attached to transport', [
            'session_id' => $session->getId(),
        ]);
    }

    /**
     * Handle HTTP request with Swoole integration
     */
    public function handleSwooleRequest(Request $request, Response $response): void
    {
        try {
            $httpMessage = $this->createHttpMessageFromSwoole($request);
            trap($httpMessage);
            $path = $request->server['request_uri'] ?? '/';
            $method = \strtoupper($request->server['request_method'] ?? 'GET');

            // Handle SSE connections (GET /sse)
            if ($method === 'GET' && \str_ends_with($path, '/sse')) {
                $this->handleSseConnection($request, $response);
                return;
            }

            // Handle message posts (POST /message)
            if ($method === 'POST' && \str_ends_with($path, '/message')) {
                $responseMessage = $this->handleMessagePost($httpMessage, $request);
                $this->sendSwooleResponse($response, $responseMessage);
                return;
            }

            // Other requests
            $responseMessage = $this->handleRequest($httpMessage, $response);
            if ($responseMessage !== null) {
                $this->sendSwooleResponse($response, $responseMessage);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error handling Swoole request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->sendErrorResponse($response, 'Internal server error', 500);
        }
    }

    /**
     * Get SSE connection manager
     */
    public function getSseManager(): SseConnectionManager
    {
        return $this->sseManager;
    }

    /**
     * Get transport configuration
     */
    public function getConfig(): SwooleTransportConfig
    {
        return $this->config;
    }

    /**
     * Send message to client via SSE (compatible with ReactPHP interface)
     */
    public function sendMessage(mixed $message, string $sessionId): bool
    {
        if (!$this->config->sseEnabled) {
            return false;
        }

        $json = \json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        return $this->sseManager->sendEventToSession($sessionId, 'message', $json) > 0;
    }

    /**
     * Event emission support (compatible with ReactPHP EventEmitter)
     */
    public function on(string $event, callable $listener): void
    {
        if (!isset($this->eventListeners[$event])) {
            $this->eventListeners[$event] = [];
        }
        $this->eventListeners[$event][] = $listener;
    }

    /**
     * Emit event to listeners
     */
    public function emit(string $event, array $arguments = []): void
    {
        if (!isset($this->eventListeners[$event])) {
            return;
        }

        foreach ($this->eventListeners[$event] as $listener) {
            try {
                \call_user_func_array($listener, $arguments);
            } catch (\Throwable $e) {
                $this->logger->error('Error in event listener', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function isStarted(): bool
    {
        return $this->isStarted;
    }

    /**
     * Handle SSE connection (simplified approach like ReactPHP)
     */
    private function handleSseConnection(Request $request, Response $response): void
    {
        // Generate new session ID automatically like ReactPHP version
        $sessionId = $this->generateSessionId();

        $this->logger->info('New SSE connection', ['sessionId' => $sessionId]);

        // Create session
        $session = $this->createSession($sessionId);
        $this->currentSessionId = $sessionId;

        // Create SSE connection
        $connectionId = \bin2hex(\random_bytes(16));
        $sseConnection = new SseConnection(
            $connectionId,
            $response,
            $sessionId,
        );

        if (!$this->sseManager->addConnection($sseConnection)) {
            $this->sendErrorResponse($response, 'SSE connection limit reached', 503);
            return;
        }

        $postEndpoint = '/message?clientId=' . $sessionId;

        $sseConnection->sendEvent('endpoint', $postEndpoint, "init-{$sessionId}");

        // Emit client_connected event
        $this->emit('client_connected', [$sessionId]);

        // The connection will be handled by SseConnection class
        // Response is already configured by SseConnection constructor
    }

    /**
     * Handle message POST requests
     */
    private function handleMessagePost(HttpMessage $httpMessage, Request $request): HttpMessage
    {
        $queryParams = $request->get ?? [];
        $sessionId = $queryParams['clientId'] ?? null;

        if (!$sessionId || !\is_string($sessionId)) {
            $this->logger->warning('Received POST without valid clientId query parameter.');
            return HttpMessage::createJsonResponse(
                ['error' => 'Missing or invalid clientId query parameter'],
                400,
            );
        }

        // todo: Workaround. Find out why session ID contains double quotes at the end!
        $sessionId = \trim($sessionId, '"');

        if (!$this->sseManager->hasSessionConnections($sessionId)) {
            $this->logger->warning('Received POST for unknown or disconnected sessionId.', ['sessionId' => $sessionId]);
            return HttpMessage::createJsonResponse(
                ['error' => 'Session ID not found or disconnected'],
                404,
            );
        }

        $contentType = $httpMessage->getHeader('Content-Type');
        if ($contentType === null || \stripos($contentType, 'application/json') === false) {
            return HttpMessage::createJsonResponse(
                ['error' => 'Content-Type must be application/json'],
                415,
            );
        }

        $body = $httpMessage->getBody();
        if ($body === null || $body === '') {
            $this->logger->warning('Received empty POST body', ['sessionId' => $sessionId]);
            return HttpMessage::createJsonResponse(
                ['error' => 'Empty request body'],
                400,
            );
        }

        try {
            $jsonData = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            // Get or create session
            $session = $this->getSession($sessionId);
            if ($session === null) {
                $session = $this->createSession($sessionId);
            }

            $this->currentSessionId = $sessionId;
            $containsRequests = $this->processJsonRpcData($jsonData, $session);

            $session->updateActivity();
            $this->sessionStore->save($session);

            // Emit message event
            $this->emit('message', [$jsonData, $sessionId, ['request' => $request]]);

            return HttpMessage::createEmptyResponse(202);
        } catch (\JsonException $e) {
            $this->logger->error('Error parsing message', ['sessionId' => $sessionId, 'exception' => $e]);
            return HttpMessage::createJsonResponse(
                ['error' => 'JSON parse error: ' . $e->getMessage()],
                400,
            );
        } catch (\Exception $e) {
            $this->logger->error('Error processing message', ['sessionId' => $sessionId, 'exception' => $e]);
            return HttpMessage::createJsonResponse(
                ['error' => 'Internal server error: ' . $e->getMessage()],
                500,
            );
        }
    }

    /**
     * Handle HTTP request and return response message
     */
    private function handleRequest(HttpMessage $request, Response $swooleResponse): ?HttpMessage
    {
        // Extract session ID from request headers
        $sessionId = $request->getHeader('Mcp-Session-Id');
        $session = null;

        // Find or create session
        if ($sessionId !== null) {
            $session = $this->getSession($sessionId);

            if ($session === null || $session->isExpired($this->sessionTimeout)) {
                if ($request->getMethod() !== 'POST' || $this->isInitializeRequest($request)) {
                    $session = $this->createSession();
                } else {
                    return HttpMessage::createJsonResponse(
                        ['error' => 'Session not found or expired'],
                        404,
                    );
                }
            }
        } else {
            if ($request->getMethod() !== 'POST' || $this->isInitializeRequest($request)) {
                $session = $this->createSession();
            } else {
                return HttpMessage::createJsonResponse(
                    ['error' => 'Session ID required'],
                    400,
                );
            }
        }

        $this->currentSessionId = $session->getId();

        // Process request based on HTTP method
        $response = match (\strtoupper((string) $request->getMethod())) {
            'POST' => $this->handlePostRequest($request, $session),
            'GET' => $this->handleGetRequest($request, $session),
            'DELETE' => $this->handleDeleteRequest($request, $session),
            default => HttpMessage::createJsonResponse(
                ['error' => 'Method not allowed'],
                405,
            )->setHeader('Allow', 'GET, POST, DELETE'),
        };

        // Add session ID header to response
        if ($session !== null) {
            $response->setHeader('Mcp-Session-Id', $session->getId());
        }

        return $response;
    }

    /**
     * Handle POST request
     */
    private function handlePostRequest(HttpMessage $request, HttpSession $session): HttpMessage
    {
        $contentType = $request->getHeader('Content-Type');
        if ($contentType === null || \stripos($contentType, 'application/json') === false) {
            return HttpMessage::createJsonResponse(
                ['error' => 'Unsupported Media Type'],
                415,
            );
        }

        $body = $request->getBody();
        if ($body === null || $body === '') {
            return HttpMessage::createJsonResponse(
                ['error' => 'Empty request body'],
                400,
            );
        }

        try {
            $jsonData = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $containsRequests = $this->processJsonRpcData($jsonData, $session);

            $session->updateActivity();
            $this->sessionStore->save($session);

            if (!$containsRequests) {
                return HttpMessage::createEmptyResponse(202);
            }

            return $this->createJsonResponse($session);
        } catch (\JsonException $e) {
            return HttpMessage::createJsonResponse(
                ['error' => 'JSON parse error: ' . $e->getMessage()],
                400,
            );
        } catch (\Exception $e) {
            return HttpMessage::createJsonResponse(
                ['error' => 'Internal server error: ' . $e->getMessage()],
                500,
            );
        }
    }

    /**
     * Handle GET request
     */
    private function handleGetRequest(HttpMessage $request, HttpSession $session): HttpMessage
    {
        return HttpMessage::createJsonResponse(
            ['error' => 'Method not allowed'],
            405,
        );
    }

    /**
     * Handle DELETE request
     */
    private function handleDeleteRequest(HttpMessage $request, HttpSession $session): HttpMessage
    {
        $sessionId = $session->getId();
        $session->expire();
        unset($this->sessions[$sessionId]);

        // Close SSE connections for this session
        if ($this->config->sseEnabled) {
            $this->sseManager->closeSessionConnections($sessionId);
            $this->emit('client_disconnected', [$sessionId, 'Session deleted']);
        }

        $this->messageQueue->cleanupExpiredSessions([$sessionId]);

        return HttpMessage::createEmptyResponse(204);
    }

    /**
     * Create HttpMessage from Swoole Request
     */
    private function createHttpMessageFromSwoole(Request $request): HttpMessage
    {
        $message = new HttpMessage();

        // I don't know why Swoole adds a trailing slash to the URI like this "request_uri" => "/%22//message"
        // Let's filter it out
        $uri = $this->filterRequestUri($request->server['request_uri'] ?? '/');

        $message->setMethod($request->server['request_method'] ?? 'GET');
        $message->setUri($uri);

        // Set headers
        if ($request->header) {
            foreach ($request->header as $key => $value) {
                $message->setHeader($key, $value);
            }
        }

        // Set query parameters
        if ($request->get) {
            $message->setQueryParams($request->get);
        }

        // Set body
        if ($request->getContent()) {
            $message->setBody($request->getContent());
        }

        return $message;
    }

    private function filterRequestUri(string $requestUri): string
    {
        // Remove URL encoding
        $uri = \urldecode($requestUri);

        // Remove extra quotes and special characters
        $uri = \trim($uri, '"\'');

        // Normalize multiple slashes to single slash
        $uri = \preg_replace('#/+#', '/', $uri);

        // Remove any remaining problematic characters
        $uri = \preg_replace('/[^\w\-.\/?&=]/', '', $uri);

        // Ensure URI starts with /
        if (!\str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }

        return $uri;
    }

    /**
     * Send Swoole response
     */
    private function sendSwooleResponse(Response $response, HttpMessage $message): void
    {
        $response->setStatusCode($message->getStatusCode());

        foreach ($message->getHeaders() as $name => $value) {
            $response->setHeader($name, $value);
        }

        $body = $message->getBody();
        if ($body !== null) {
            $response->end($body);
        } else {
            $response->end();
        }
    }

    /**
     * Send error response
     */
    private function sendErrorResponse(Response $response, string $message, int $code = 500): void
    {
        $response->setStatusCode($code);
        $response->setHeader('Content-Type', 'application/json');
        $response->end(\json_encode([
            'error' => $message,
            'code' => $code,
        ]));
    }

    /**
     * Generate session ID
     * @throws RandomException
     */
    private function generateSessionId(): string
    {
        return \bin2hex(\random_bytes(16));
    }

    /**
     * Get session by ID
     */
    private function getSession(string $sessionId): ?HttpSession
    {
        if (isset($this->sessions[$sessionId])) {
            $session = $this->sessions[$sessionId];
            if ($session->isExpired($this->sessionTimeout)) {
                $session->expire();
                $this->sessionStore->delete($sessionId);
                unset($this->sessions[$sessionId]);
                return null;
            }
            return $session;
        }

        $session = $this->sessionStore->load($sessionId);
        if ($session && !$session->isExpired($this->sessionTimeout)) {
            $this->sessions[$sessionId] = $session;
            return $session;
        }

        if ($session) {
            $session->expire();
            $this->sessionStore->delete($sessionId);
        }
        return null;
    }

    /**
     * Create new session
     */
    private function createSession(?string $sessionId = null): HttpSession
    {
        $session = new HttpSession($sessionId);
        $session->activate();
        $this->sessions[$session->getId()] = $session;
        $this->sessionStore->save($session);
        return $session;
    }

    /**
     * Create JSON response from pending messages
     */
    private function createJsonResponse(HttpSession $session): HttpMessage
    {
        $pendingMessages = $this->messageQueue->flushOutgoing($session->getId(), true);

        if (empty($pendingMessages)) {
            return HttpMessage::createEmptyResponse(202);
        }

        if (\count($pendingMessages) === 1) {
            $message = $pendingMessages[0];
            return HttpMessage::createJsonResponse($message->message, 200);
        }

        $batchData = [];
        foreach ($pendingMessages as $message) {
            $batchData[] = $message->message;
        }

        return HttpMessage::createJsonResponse($batchData, 200);
    }

    /**
     * Process JSON-RPC data and queue messages
     */
    private function processJsonRpcData(mixed $data, HttpSession $session): bool
    {
        if (!\is_array($data)) {
            return false;
        }

        $containsRequests = false;

        // Handle batch requests
        if (isset($data[0]) && \is_array($data[0])) {
            foreach ($data as $item) {
                if ($this->processJsonRpcItem($item, $session)) {
                    $containsRequests = true;
                }
            }
        } else {
            $containsRequests = $this->processJsonRpcItem($data, $session);
        }

        return $containsRequests;
    }

    /**
     * Process individual JSON-RPC item
     * @throws McpError
     */
    private function processJsonRpcItem(mixed $item, HttpSession $session): bool
    {
        $this->messageQueue->queueIncoming(
            $this->messageParser->parse($item),
        );

        return false; // Notifications don't expect responses

    }

    /**
     * Check if request is initialize request
     */
    private function isInitializeRequest(HttpMessage $request): bool
    {
        if ($request->getMethod() !== 'POST') {
            return false;
        }

        $body = $request->getBody();
        if ($body === null || $body === '') {
            return false;
        }

        try {
            $data = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return isset($data['method']) && $data['method'] === 'initialize';
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Cleanup expired sessions
     */
    private function cleanupExpiredSessions(): void
    {
        $now = \time();

        if ($now - $this->lastSessionCleanup < $this->sessionCleanupInterval) {
            return;
        }

        $this->lastSessionCleanup = $now;
        $sessionTimeout = $this->sessionTimeout;
        $expiredSessionIds = [];

        foreach ($this->sessions as $sessionId => $session) {
            if ($session->isExpired($sessionTimeout)) {
                $expiredSessionIds[] = $sessionId;
                unset($this->sessions[$sessionId]);
            }
        }

        if (!empty($expiredSessionIds)) {
            $this->messageQueue->cleanupExpiredSessions($expiredSessionIds);

            // Close SSE connections for expired sessions
            if ($this->config->sseEnabled) {
                foreach ($expiredSessionIds as $sessionId) {
                    $this->sseManager->closeSessionConnections($sessionId);
                    $this->emit('client_disconnected', [$sessionId, 'Session expired']);
                }
            }
        }
    }
}
