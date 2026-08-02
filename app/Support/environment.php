<?php

if (!function_exists('loadEnvFileNonOverwriting')) {
    /**
     * Loads NAME=VALUE pairs without replacing variables already present in the process.
     * Returns names that were loaded; values are intentionally never returned.
     */
    function loadEnvFileNonOverwriting(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $loaded = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $lineNumber => $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            if (preg_match('/^[A-Z_][A-Z0-9_]*$/iD', $name) !== 1) {
                continue;
            }
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            if (getenv($name) !== false) {
                continue;
            }
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $loaded[] = $name;
        }

        return $loaded;
    }
}
