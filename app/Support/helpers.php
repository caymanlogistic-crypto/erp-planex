<?php

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return STORAGE_PATH . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}

if (!function_exists('company_storage_path')) {
    function company_storage_path(?string $configuredPath, int $companyId): string
    {
        $configuredPath = rtrim(str_replace('\\', '/', trim((string) $configuredPath)), '/');

        if ($configuredPath === '') {
            return storage_path('companies/' . $companyId);
        }

        if (str_starts_with($configuredPath, 'storage/')) {
            return storage_path(substr($configuredPath, strlen('storage/')));
        }

        return base_path($configuredPath);
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('app_base_path')) {
    function app_base_path(): string
    {
        static $basePath = null;

        if ($basePath !== null) {
            return $basePath;
        }

        $basePath = trim(getenv('APP_BASE_PATH') ?: '');

        if ($basePath === '') {
            return $basePath = '';
        }

        $basePath = '/' . trim($basePath, '/');

        return $basePath;
    }
}

if (!function_exists('path_starts_with_base_path')) {
    function path_starts_with_base_path(string $path, string $basePath): bool
    {
        return $path === $basePath || str_starts_with($path, $basePath . '/');
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $base = app_base_path();

        if ($path === '' || $path === '/') {
            return $base !== '' ? $base : '/';
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to(string $path, int $status = 302): never
    {
        header('Location: ' . app_url($path), true, $status);
        exit;
    }
}

if (!function_exists('current_app_path')) {
    function current_app_path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = app_base_path();

        if ($base !== '' && path_starts_with_base_path($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = $uri !== '' ? $uri : '/';
        $uri = rtrim($uri, '/') ?: '/';

        return $uri;
    }
}

if (!function_exists('rewrite_html_base_path_urls')) {
    function rewrite_html_base_path_urls(string $html): string
    {
        $base = app_base_path();

        if ($base === '') {
            return $html;
        }

        $quotedBase = preg_quote(ltrim($base, '/'), '~');

        return preg_replace(
            '~\b(href|action|src)=(["\'])/(?!/)(?!' . $quotedBase . '(?:/|["\']|\?))~i',
            '$1=$2' . $base . '/',
            $html
        ) ?? $html;
    }
}

if (!function_exists('start_base_path_output_rewrite')) {
    function start_base_path_output_rewrite(): void
    {
        if (PHP_SAPI === 'cli' || app_base_path() === '') {
            return;
        }

        ob_start(static function (string $buffer): string {
            $contentType = '';

            foreach (headers_list() as $header) {
                if (stripos($header, 'Content-Type:') === 0) {
                    $contentType = strtolower($header);
                    break;
                }
            }

            if ($contentType !== '' && !str_contains($contentType, 'text/html')) {
                return $buffer;
            }

            return rewrite_html_base_path_urls($buffer);
        });
    }
}

if (!function_exists('start_base_path_header_rewrite')) {
    function start_base_path_header_rewrite(): void
    {
        if (PHP_SAPI === 'cli' || app_base_path() === '') {
            return;
        }

        header_register_callback(static function (): void {
            $base = app_base_path();

            foreach (headers_list() as $header) {
                if (stripos($header, 'Location:') !== 0) {
                    continue;
                }

                $location = trim(substr($header, strlen('Location:')));

                if ($location === '' || $location[0] !== '/' || str_starts_with($location, '//')) {
                    continue;
                }

                if (path_starts_with_base_path($location, $base)) {
                    continue;
                }

                header_remove('Location');
                header('Location: ' . app_url($location));
                break;
            }
        });
    }
}
