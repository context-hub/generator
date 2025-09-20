<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Server;

use Mcp\Shared\ErrorData as TypesErrorData;
use Mcp\Shared\McpError;
use Mcp\Types\JSONRPCError;
use Mcp\Types\JsonRpcErrorObject;
use Mcp\Types\JsonRpcMessage;
use Mcp\Types\JSONRPCNotification;
use Mcp\Types\JSONRPCRequest;
use Mcp\Types\JSONRPCResponse;
use Mcp\Types\NotificationParams;
use Mcp\Types\RequestId;
use Mcp\Types\RequestParams;
use Mcp\Types\Result;

final readonly class MessageParser
{
    public function parse(array $data): JsonRpcMessage
    {
        // Must have "jsonrpc": "2.0"
        if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            throw new McpError(
                new TypesErrorData(
                    code: -32600,
                    message: 'Invalid Request: jsonrpc version must be "2.0"',
                ),
            );
        }

        // Check which fields are present
        $hasMethod = \array_key_exists('method', $data);
        $hasId = \array_key_exists('id', $data);
        $hasResult = \array_key_exists('result', $data);
        $hasError = \array_key_exists('error', $data);

        // Initialize a RequestId if present
        $id = null;
        if ($hasId) {
            $id = new RequestId($data['id']);
        }

        try {
            if ($hasError) {
                // JSONRPCError
                return new JsonRpcMessage($this->buildErrorMessage($data, $id));
            } elseif ($hasMethod && $hasId && !$hasResult) {
                // JSONRPCRequest
                return new JsonRpcMessage($this->buildRequestMessage($data, $id));
            } elseif ($hasMethod && !$hasId && !$hasResult && !$hasError) {
                // JSONRPCNotification
                return new JsonRpcMessage($this->buildNotificationMessage($data));
            } elseif ($hasId && $hasResult && !$hasMethod && !$hasError) {
                // JSONRPCResponse
                return new JsonRpcMessage($this->buildResponseMessage($data, $id));
            }
            // Could not classify
            throw new McpError(
                new TypesErrorData(
                    code: -32600,
                    message: 'Invalid Request: could not determine message type',
                ),
            );
        } catch (McpError $e) {
            // Bubble up as-is
            throw $e;
        } catch (\Exception $e) {
            // Other exceptions become parse errors
            throw new McpError(
                new TypesErrorData(
                    code: -32700,
                    message: 'Parse error: ' . $e->getMessage(),
                ),
            );
        }
    }


    /**
     * Build a JSONRPCError object from decoded data.
     */
    private function buildErrorMessage(array $data, ?RequestId $id): JSONRPCError
    {
        $errorData = $data['error'];
        if (!isset($errorData['code']) || !isset($errorData['message'])) {
            throw new McpError(
                new TypesErrorData(
                    code: -32600,
                    message: 'Invalid Request: error object must contain code and message',
                ),
            );
        }
        $errorObj = new JsonRpcErrorObject(
            code: $errorData['code'],
            message: $errorData['message'],
            data: $errorData['data'] ?? null,
        );
        $msg = new JSONRPCError(
            jsonrpc: '2.0',
            id: $id ?? new RequestId(''), // per JSON-RPC, error typically has an ID
            error: $errorObj,
        );
        $msg->validate();
        return $msg;
    }

    /**
     * Build a JSONRPCRequest object from decoded data.
     */
    private function buildRequestMessage(array $data, ?RequestId $id): JSONRPCRequest
    {
        $method = $data['method'];
        $params = isset($data['params']) && \is_array($data['params'])
            ? $this->parseRequestParams($data['params'])
            : null;

        $req = new JSONRPCRequest(
            jsonrpc: '2.0',
            id: $id,
            method: $method,
            params: $params,
        );
        $req->validate();
        return $req;
    }

    /**
     * Build a JSONRPCNotification object from decoded data.
     */
    private function buildNotificationMessage(array $data): JSONRPCNotification
    {
        $method = $data['method'];
        $params = isset($data['params']) && \is_array($data['params'])
            ? $this->parseNotificationParams($data['params'])
            : null;

        $not = new JSONRPCNotification(
            jsonrpc: '2.0',
            method: $method,
            params: $params,
        );
        $not->validate();
        return $not;
    }

    /**
     * Build a JSONRPCResponse object from decoded data.
     */
    private function buildResponseMessage(array $data, ?RequestId $id): JSONRPCResponse
    {
        // E.g. you do a "generic" mapping to a simple Result object
        $resultArr = $data['result'];
        $resultObj = new Result();
        if (\is_array($resultArr)) {
            foreach ($resultArr as $k => $v) {
                if ($k !== '_meta') {
                    $resultObj->$k = $v;
                }
            }
        }
        $resp = new JSONRPCResponse(
            jsonrpc: '2.0',
            id: $id,
            result: $resultObj,
        );
        $resp->validate();
        return $resp;
    }

    /**
     * Parses request parameters from an associative array.
     *
     * @param array $params The parameters array from the JSON-RPC request.
     *
     * @return RequestParams The parsed RequestParams object.
     */
    private function parseRequestParams(array $params): RequestParams
    {
        $meta = isset($params['_meta']) ? $this->metaFromArray($params['_meta']) : null;

        // Correctly passing $meta as the first argument
        $requestParams = new RequestParams($meta);

        // Assign other parameters dynamically
        foreach ($params as $key => $value) {
            if ($key !== '_meta') {
                $requestParams->$key = $value;
            }
        }

        return $requestParams;
    }

    /**
     * Parses notification parameters from an associative array.
     *
     * @param array $params The parameters array from the JSON-RPC notification.
     *
     * @return NotificationParams The parsed NotificationParams object.
     */
    private function parseNotificationParams(array $params): NotificationParams
    {
        $meta = isset($params['_meta']) ? $this->metaFromArray($params['_meta']) : null;

        // Correctly passing $meta as the first argument
        $notificationParams = new NotificationParams($meta);

        // Assign other parameters dynamically
        foreach ($params as $key => $value) {
            if ($key !== '_meta') {
                $notificationParams->$key = $value;
            }
        }

        return $notificationParams;
    }

    /**
     * Helper method to create a Meta object from an associative array.
     *
     * @param array $metaArr The meta information array.
     *
     * @return \Mcp\Types\Meta The constructed Meta object.
     */
    private function metaFromArray(array $metaArr): \Mcp\Types\Meta
    {
        $meta = new \Mcp\Types\Meta();
        foreach ($metaArr as $key => $value) {
            $meta->$key = $value;
        }
        return $meta;
    }
}
