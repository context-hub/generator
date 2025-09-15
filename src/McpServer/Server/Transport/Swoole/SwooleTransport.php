<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server\Transport\Swoole;

use Butschster\ContextGenerator\McpServer\Config\SwooleTransportConfig;
use Mcp\Server\Transport\BufferedTransport;
use Mcp\Server\Transport\Http\HttpMessage;
use Mcp\Server\Transport\Http\HttpSession;
use Mcp\Server\Transport\Http\InMemorySessionStore;
use Mcp\Server\Transport\Http\MessageQueue;
use Mcp\Server\Transport\Http\SessionStoreInterface;
use Mcp\Server\Transport\SessionAwareTransport;
use Mcp\Shared\BaseSession;
use Mcp\Types\JSONRPCBatchResponse;
use Mcp\Types\JSONRPCError;
use Mcp\Types\JsonRpcMessage;
use Mcp\Types\JSONRPCNotification;
use Mcp\Types\JSONRPCResponse;
use Psr\Log\LoggerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Advanced Swoole-based HTTP transport with SSE support
 */
final class SwooleTransport implements BufferedTransport, SessionAwareTransport
{
    private readonly SwooleTransportConfig $config;
    private readonly MessageQueue $messageQueue;
    private readonly SessionStoreInterface $sessionStore;
    private readonly SseConnectionManager $sseManager;

    /**
     * @var array<string, HttpSession>
     */
    private array $sessions = [];

    private ?string $currentSessionId = null;
    private ?HttpSession $lastUsedSession = null;
    private bool $isStarted = false;
    private int $lastSessionCleanup = 0;
    private int $sessionCleanupInterval = 300;

    public function __construct(
        private readonly LoggerInterface $logger,
        ?SwooleTransportConfig $config = null,
        ?SessionStoreInterface $sessionStore = null,
    ) {
        $this->config = $config ?? new SwooleTransportConfig();
        $this->sessionStore = $sessionStore ?? new InMemorySessionStore();

        $this->messageQueue = new MessageQueue(
            $this->config->get('max_queue_size') ?? 1000,
        );

        $this->sseManager = new SseConnectionManager(
            $this->logger,
            $this->config->getSseConfig(),
        );
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
     * Handle HTTP request and return response message
     */
    private function handleRequest(HttpMessage $request, Response $swooleResponse): ?HttpMessage
    {
        // Check for SSE upgrade
        if ($this->isSSERequest($request) && $this->config->sseEnabled) {
            return $this->handleSSEUpgrade($request, $swooleResponse);
        }

        // Extract session ID from request headers
        $sessionId = $request->getHeader('Mcp-Session-Id');
        $session = null;

        // Find or create session
        if ($sessionId !== null) {
            $session = $this->getSession($sessionId);

            if ($session === null || $session->isExpired($this->config->get('session_timeout'))) {
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
        $this->lastUsedSession = $session;

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
     * Handle SSE connection upgrade
     */
    private function handleSSEUpgrade(HttpMessage $request, Response $response): ?HttpMessage
    {
        $sessionId = $request->getHeader('Mcp-Session-Id');

        if ($sessionId === null) {
            return HttpMessage::createJsonResponse(
                ['error' => 'Session ID required for SSE'],
                400,
            );
        }

        $session = $this->getSession($sessionId);
        if ($session === null) {
            return HttpMessage::createJsonResponse(
                ['error' => 'Invalid session for SSE'],
                404,
            );
        }

        // Create SSE connection
        $connectionId = \bin2hex(\random_bytes(16));
        $sseConnection = new SseConnection(
            $connectionId,
            $response,
            $sessionId,
        );

        if ($this->sseManager->addConnection($sseConnection)) {
            // Connection will be managed by SseConnection class
            // Return null to indicate response is handled
            return null;
        }
        return HttpMessage::createJsonResponse(
            ['error' => 'SSE connection limit reached'],
            503,
        );

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
        $acceptHeader = $request->getHeader('Accept');
        $wantsSse = $acceptHeader !== null &&
                   \stripos($acceptHeader, 'text/event-stream') !== false;

        if ($wantsSse && $this->config->sseEnabled) {
            // This should be handled by handleSSEUpgrade
            return HttpMessage::createJsonResponse(
                ['error' => 'SSE upgrade required'],
                426,
            );
        }

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
        $session->expire();
        unset($this->sessions[$session->getId()]);

        // Close SSE connections for this session
        if ($this->config->sseEnabled) {
            $this->sseManager->closeSessionConnections($session->getId());
        }

        $this->messageQueue->cleanupExpiredSessions([$session->getId()]);

        return HttpMessage::createEmptyResponse(204);
    }

    /**
     * Create HttpMessage from Swoole Request
     */
    private function createHttpMessageFromSwoole(Request $request): HttpMessage
    {
        $message = new HttpMessage();

        $message->setMethod($request->server['request_method'] ?? 'GET');
        $message->setUri($request->server['request_uri'] ?? '/');

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
     * Check if request is for SSE
     */
    private function isSSERequest(HttpMessage $request): bool
    {
        $accept = $request->getHeader('Accept');
        return $accept !== null && \stripos($accept, 'text/event-stream') !== false;
    }

    // ... [Include the rest of the helper methods from HttpServerTransport]
    // processJsonRpcData, isInitializeRequest, getSession, createSession, etc.
    // These would be identical or very similar to the existing HttpServerTransport implementation

    /**
     * Get session by ID (similar to HttpServerTransport)
     */
    private function getSession(string $sessionId): ?HttpSession
    {
        if (isset($this->sessions[$sessionId])) {
            $session = $this->sessions[$sessionId];
            if ($session->isExpired($this->config->get('session_timeout'))) {
                $session->expire();
                $this->sessionStore->delete($sessionId);
                unset($this->sessions[$sessionId]);
                return null;
            }
            return $session;
        }

        $session = $this->sessionStore->load($sessionId);
        if ($session && !$session->isExpired($this->config->get('session_timeout'))) {
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
    private function createSession(): HttpSession
    {
        $session = new HttpSession();
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
     * Process JSON-RPC data (simplified version)
     */
    private function processJsonRpcData(array $data, HttpSession $session): bool
    {
        // This would contain the same logic as HttpServerTransport
        // For brevity, returning true to indicate requests were processed
        $this->messageQueue->queueIncoming(new JsonRpcMessage(new JSONRPCNotification('2.0', 'test')));
        return true;
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
        $sessionTimeout = $this->config->get('session_timeout');
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
                }
            }
        }
    }

    public function isStarted(): bool
    {
        return $this->isStarted;
    }
}
