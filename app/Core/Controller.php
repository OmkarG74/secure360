<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base Application Controller
 */
abstract class Controller
{
    protected Request $request;
    protected Response $response;

    public function __construct(?Request $request = null, ?Response $response = null)
    {
        $this->request = $request ?? new Request();
        $this->response = $response ?? new Response();
    }

    /**
     * Render a view with an optional layout
     */
    protected function render(string $view, array $data = [], ?string $layout = null): void
    {
        $content = View::render($view, $data, $layout);
        $this->response->html($content);
    }

    /**
     * Return a JSON response
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        $this->response->json($data, $statusCode);
    }

    /**
     * Redirect to specified URL
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        $this->response->redirect($url, $statusCode);
    }

    /**
     * Flash notification message to session
     */
    protected function setFlash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}
