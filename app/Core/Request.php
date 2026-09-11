<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Request Handler
 * Encapsulates incoming request data from Web and Flutter Mobile API clients
 */
class Request
{
    private string $method;
    private string $uri;
    private array $queryParams;
    private array $body;
    private array $headers;

    public function __construct(?array $query = null, ?array $body = null, ?array $server = null, ?array $headers = null)
    {
        $serverData = $server ?? $_SERVER;
        $this->method = strtoupper($serverData['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $this->parseUri($serverData);
        $this->queryParams = $query ?? $_GET;
        $this->headers = $headers ?? $this->parseHeaders($serverData);
        $this->body = $body ?? $this->parseBody();
    }

    /**
     * Parse and normalize the request URI
     * Handles Apache subdirectory deployments like /Secure360 or /Secure360/public
     */
    private function parseUri(array $server = []): string
    {
        $serverData = !empty($server) ? $server : $_SERVER;
        $rawUri = $serverData['REQUEST_URI'] ?? '/';
        $path = parse_url($rawUri, PHP_URL_PATH) ?? '/';

        // Normalize Windows backslashes
        $scriptName = str_replace('\\', '/', $serverData['SCRIPT_NAME'] ?? '');
        $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        if ($baseDir !== '' && $baseDir !== '/') {
            if (str_starts_with($path, $baseDir)) {
                $path = substr($path, strlen($baseDir));
            } else {
                $parentBase = rtrim(str_replace('\\', '/', dirname($baseDir)), '/');
                if ($parentBase !== '' && $parentBase !== '/' && str_starts_with($path, $parentBase)) {
                    $path = substr($path, strlen($parentBase));
                }
            }
        }

        // Direct subfolder check fallback (e.g. /Secure360)
        if (str_starts_with($path, '/Secure360')) {
            $path = substr($path, 10);
        }

        // Remove redundant /public segment if requested directly
        if (str_starts_with($path, '/public')) {
            $path = substr($path, 7);
        }

        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }

    /**
     * Parse request headers
     */
    private function parseHeaders(array $server = []): array
    {
        $serverData = !empty($server) ? $server : $_SERVER;
        $headers = [];

        if (function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            if (is_array($allHeaders)) {
                foreach ($allHeaders as $key => $value) {
                    $headers[strtolower((string)$key)] = $value;
                }
            }
        }

        // Fallback for Apache Authorization header if not picked up by getallheaders
        if (!isset($headers['authorization'])) {
            if (isset($serverData['HTTP_AUTHORIZATION'])) {
                $headers['authorization'] = $serverData['HTTP_AUTHORIZATION'];
            } elseif (isset($serverData['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['authorization'] = $serverData['REDIRECT_HTTP_AUTHORIZATION'];
            }
        }

        return $headers;
    }

    /**
     * Parse input body (handling $_POST as well as JSON payloads from Flutter)
     */
    private function parseBody(): array
    {
        if ($this->method === 'POST' || $this->method === 'PUT' || $this->method === 'PATCH') {
            $contentType = $this->getHeader('content-type') ?? '';

            if (str_contains($contentType, 'application/json')) {
                $rawInput = file_get_contents('php://input');
                $decoded = json_decode((string)$rawInput, true);
                return is_array($decoded) ? $decoded : [];
            }

            if (!empty($_POST)) {
                return $_POST;
            }

            // Fallback: check if raw input contains JSON
            $rawInput = file_get_contents('php://input');
            if (!empty($rawInput)) {
                $decoded = json_decode((string)$rawInput, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            return [];
        }

        return [];
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getQuery(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function getBody(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    /**
     * Retrieve an input parameter from request body (POST/JSON) or query string (GET)
     * Body parameters take precedence over query parameters
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return array_merge($this->queryParams, $this->body);
        }

        if (array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }

        if (array_key_exists($key, $this->queryParams)) {
            return $this->queryParams[$key];
        }

        return $default;
    }

    /**
     * Alias for getQuery() - retrieve GET query parameter
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $this->getQuery($key, $default);
    }

    /**
     * Check if a parameter exists in body or query parameters
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->queryParams);
    }

    /**
     * Retrieve all input data (body + query)
     */
    public function all(): array
    {
        return $this->input();
    }

    /**
     * Retrieve a subset of input parameters
     */
    public function only(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->input($key);
        }
        return $results;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    /**
     * Retrieve Bearer token from Authorization header
     */
    public function getBearerToken(): ?string
    {
        $header = $this->getHeader('authorization');
        if (!empty($header) && preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function isJson(): bool
    {
        $contentType = $this->getHeader('content-type') ?? '';
        return str_contains($contentType, 'application/json');
    }
}
