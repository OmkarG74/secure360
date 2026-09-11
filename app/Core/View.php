<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * View Rendering Engine for Core PHP
 * Supports Layouts, Subviews, Component Injection, and Safe Output Escaping
 */
class View
{
    private static string $viewsPath = '';

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public static function getViewsPath(): string
    {
        if (empty(self::$viewsPath)) {
            self::$viewsPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;
        }
        return self::$viewsPath;
    }

    /**
     * Render a view within a layout
     *
     * @param string $viewPath (e.g. 'admin/dashboard/index')
     * @param array $data
     * @param string|null $layout (e.g. 'layouts/admin' or null for standalone)
     * @return string
     */
    public static function render(string $viewPath, array $data = [], ?string $layout = null): string
    {
        $base = self::getViewsPath();
        $fullViewPath = $base . str_replace(['.', '/'], DIRECTORY_SEPARATOR, $viewPath) . '.php';

        if (!file_exists($fullViewPath)) {
            throw new RuntimeException("View file not found: {$fullViewPath}");
        }

        // Extract variables into view scope
        extract($data, EXTR_SKIP);

        ob_start();
        require $fullViewPath;
        $content = ob_get_clean();

        // If a layout is specified, inject $content into the layout
        if ($layout !== null) {
            $fullLayoutPath = $base . str_replace(['.', '/'], DIRECTORY_SEPARATOR, $layout) . '.php';
            if (!file_exists($fullLayoutPath)) {
                throw new RuntimeException("Layout file not found: {$fullLayoutPath}");
            }

            ob_start();
            require $fullLayoutPath;
            return ob_get_clean();
        }

        return $content;
    }

    /**
     * Include a reusable component
     *
     * @param string $component (e.g. 'components/sidebar')
     * @param array $params
     * @return void
     */
    public static function component(string $component, array $params = []): void
    {
        $base = self::getViewsPath();
        $fullComponentPath = $base . str_replace(['.', '/'], DIRECTORY_SEPARATOR, $component) . '.php';

        if (file_exists($fullComponentPath)) {
            extract($params, EXTR_SKIP);
            require $fullComponentPath;
        }
    }

    /**
     * HTML entity safe escaping helper
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
