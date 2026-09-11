<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Response Handler
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function sendHeaders(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }
    }

    /**
     * Send a JSON response (standard for Mobile APIs and AJAX)
     */
    public function json(array $data, ?int $statusCode = null): void
    {
        if ($statusCode !== null) {
            $this->setStatusCode($statusCode);
        }
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->sendHeaders();
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Redirect to another URL
     * Automatically resolves relative application paths (e.g. '/login', '/superadmin/dashboard')
     * into absolute URLs using the project's base URL.
     */
    public function redirect(string $url, int $statusCode = 302): void
    {
        if (!preg_match('#^https?://#i', $url)) {
            $url = function_exists('url') ? url($url) : $url;
        }

        $this->setStatusCode($statusCode);
        $this->setHeader('Location', $url);
        $this->sendHeaders();
        exit;
    }

    /**
     * Send raw HTML output
     */
    public function html(string $content, ?int $statusCode = null): void
    {
        if ($statusCode !== null) {
            $this->setStatusCode($statusCode);
        }
        $this->setHeader('Content-Type', 'text/html; charset=utf-8');
        $this->sendHeaders();
        echo $content;
        exit;
    }
}
