<?php

namespace App\Service;

use Throwable;

final class MutationErrorService
{
    /**
     * Persist technical details in the protected storage log and return a public correlation ID.
     * Secret-like context keys are removed before serialization.
     *
     * @param array<string,mixed> $context
     */
    public static function report(Throwable $error, string $operation, array $context = []): string
    {
        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (Throwable) {
            $suffix = substr(hash('sha256', uniqid('', true)), 0, 8);
        }

        $errorId = 'ERP-' . gmdate('Ymd-His') . '-' . $suffix;
        $safeContext = self::sanitizeContext($context);
        $payload = [
            'timestamp' => gmdate(DATE_ATOM),
            'error_id' => $errorId,
            'operation' => $operation,
            'context' => $safeContext,
            'exception' => get_class($error),
            'code' => (string) $error->getCode(),
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => array_slice($error->getTrace(), 0, 10),
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false) {
            $logDirectory = storage_path('logs');
            if (!is_dir($logDirectory)) {
                @mkdir($logDirectory, 0750, true);
            }
            @file_put_contents(
                $logDirectory . DIRECTORY_SEPARATOR . 'mutation_errors.log',
                $encoded . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }

        error_log(sprintf(
            '[ERP] mutation failed error_id=%s operation=%s exception=%s',
            $errorId,
            $operation,
            get_class($error)
        ));

        return $errorId;
    }

    public static function userMessage(string $action, string $errorId): string
    {
        return sprintf(
            'Не удалось %s из-за внутренней ошибки. Данные не были применены. Код ошибки: %s.',
            $action,
            $errorId
        );
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private static function sanitizeContext(array $context): array
    {
        $result = [];
        foreach ($context as $key => $value) {
            $key = (string) $key;
            if (preg_match('/password|passwd|secret|token|cookie|session|authorization|api[_-]?key/i', $key)) {
                $result[$key] = '[REDACTED]';
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                $result[$key] = self::sanitizeContext($value);
            }
        }
        return $result;
    }
}
