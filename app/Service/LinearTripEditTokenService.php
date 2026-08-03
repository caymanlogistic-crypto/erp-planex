<?php

namespace App\Service;

final class LinearTripEditTokenService
{
    private const SESSION_KEY = 'linear_trip_edit_tokens';
    private const TTL_SECONDS = 1800;
    private const MAX_TOKENS_PER_ROUTE = 8;

    public static function issue(int $routeId): string
    {
        self::prune();
        $token = bin2hex(random_bytes(24));
        $_SESSION[self::SESSION_KEY][$routeId][$token] = time() + self::TTL_SECONDS;

        $routeTokens = (array) ($_SESSION[self::SESSION_KEY][$routeId] ?? []);
        if (count($routeTokens) > self::MAX_TOKENS_PER_ROUTE) {
            asort($routeTokens, SORT_NUMERIC);
            while (count($routeTokens) > self::MAX_TOKENS_PER_ROUTE) {
                $oldest = array_key_first($routeTokens);
                if ($oldest === null) break;
                unset($routeTokens[$oldest]);
            }
            $_SESSION[self::SESSION_KEY][$routeId] = $routeTokens;
        }

        return $token;
    }

    public static function consume(int $routeId, string $token): bool
    {
        self::prune();
        if ($routeId <= 0 || $token === '') return false;

        $expiresAt = $_SESSION[self::SESSION_KEY][$routeId][$token] ?? null;
        if (!is_int($expiresAt) || $expiresAt < time()) return false;

        unset($_SESSION[self::SESSION_KEY][$routeId][$token]);
        if (empty($_SESSION[self::SESSION_KEY][$routeId])) {
            unset($_SESSION[self::SESSION_KEY][$routeId]);
        }
        return true;
    }

    private static function prune(): void
    {
        $now = time();
        $all = (array) ($_SESSION[self::SESSION_KEY] ?? []);
        foreach ($all as $routeId => $tokens) {
            foreach ((array) $tokens as $token => $expiresAt) {
                if (!is_int($expiresAt) || $expiresAt < $now) unset($all[$routeId][$token]);
            }
            if (empty($all[$routeId])) unset($all[$routeId]);
        }
        $_SESSION[self::SESSION_KEY] = $all;
    }
}
