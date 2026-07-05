<?php

if (!function_exists('isAuthenticated')) {
    function isAuthenticated(): bool
    {
        return !empty($_SESSION['user_id']);
    }
}

if (!function_exists('requireRole')) {
    function requireRole(string|array $roles): void
    {
        if (!isAuthenticated()) {
            redirect_to('/login');
        }

        $allowed = is_array($roles) ? $roles : [$roles];
        if (!in_array($_SESSION['role_code'] ?? '', $allowed, true)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            $pageTitle = 'Доступ запрещён';
            ob_start();
            require base_path('app/View/pages/error_403.php');
            $content = ob_get_clean();
            require base_path('app/View/layouts/main.php');
            exit;
        }
    }
}

if (!function_exists('getSessionCompanyId')) {
    function getSessionCompanyId(): ?int
    {
        return isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : null;
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('requestJsonBody')) {
    function requestJsonBody(): array
    {
        $rawBody = file_get_contents('php://input');

        if (!is_string($rawBody) || trim($rawBody) === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('contractorFormDefaultContacts')) {
    function contractorFormDefaultContacts(): array
    {
        return contactFieldsDefaultRows();
    }
}

if (!function_exists('clientFormDefaultContacts')) {
    function clientFormDefaultContacts(): array
    {
        return contactFieldsDefaultRows();
    }
}
