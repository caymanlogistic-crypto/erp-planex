<?php

if (!function_exists('cryptoEncrypt')) {
    function cryptoEncrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        $key = getenv('APP_ENCRYPTION_KEY');
        if ($key === false || $key === '') {
            throw new \RuntimeException('APP_ENCRYPTION_KEY is not configured. Cannot encrypt data.');
        }
        $key = hash('sha256', $key, true);
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }
        return 'v2:' . base64_encode($iv . $tag . $encrypted);
    }
}

if (!function_exists('cryptoDecrypt')) {
    function cryptoDecrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }
        $key = getenv('APP_ENCRYPTION_KEY');
        if ($key === false || $key === '') {
            throw new \RuntimeException('APP_ENCRYPTION_KEY is not configured. Cannot decrypt data.');
        }
        $key = hash('sha256', $key, true);
        if (str_starts_with($payload, 'v2:')) {
            $data = base64_decode(substr($payload, 3), true);
            if ($data === false || strlen($data) < 29) {
                throw new \RuntimeException('Encrypted payload is invalid.');
            }

            $iv = substr($data, 0, 12);
            $tag = substr($data, 12, 16);
            $ciphertext = substr($data, 28);
            $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($decrypted === false) {
                throw new \RuntimeException('Encrypted payload authentication failed.');
            }

            return $decrypted;
        }

        // Backward-compatible reader for values encrypted before the GCM migration.
        $data = base64_decode($payload, true);
        if ($data === false || strlen($data) < 17) {
            throw new \RuntimeException('Encrypted payload is invalid.');
        }
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new \RuntimeException('Encrypted payload decryption failed.');
        }

        return $decrypted;
    }
}
