<?php
namespace App\Core;

use App\Models\ApiToken;

/**
 * API authentication class
 * Handles API key authentication
 */

final class ApiAuth
{
    /**
     * Extracts the API key from headers.
     * Supports:
     *  - Authorization: Bearer <token>
     *  - X-Api-Key: <token>
     */
    public static function apiKeyFrom(Request $req): string
    {
        /** get the Authorization header */
        $auth = (string)($req->header('Authorization') ?? '');

        /** Check if authorization header is set and check if it starts with "Bearer " */
        if ($auth !== '' && stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7)); //strip "Bearer "
        }

        /** fallback to X-Api-Key */
        $fallback = (string)($req->header('X-Api-Key') ?? '');
        return trim($fallback);
    }

    /**
     * Hard gate: enforce API key presence/validity.
     * If invalid → respond 401 and terminate via Response::send().
     * Toggleable via .env: REQUIRE_API_KEY=0 (useful in local dev).
     */
    public static function requireApiKey(Request $req): void
    {
        // If Env is absent, default to enforcing else read flag
        $enforce = !class_exists(Env::class) || Env::bool('REQUIRE_API_KEY', true);
        if (!$enforce) return;

        /** Get the API key from the request and check if it's valid if not, send 401 response*/
        $token = self::apiKeyFrom($req);
        if ($token === '' || !ApiToken::verify($token)) {
            (new Response(401, 'Unauthorized', false, ['error' => 'invalid_or_missing_api_key']))->send();
        }
    }

    /** Soft check: returns true/false without sending a response. */
    /**
     * Check if the API key is valid.
     * @param Request $req
     * @return bool
     */
    public static function checkApiKey(Request $req): bool
    {
        /** get the API key from the request and check if it's valid' */
        $token = self::apiKeyFrom($req);
        return $token !== '' && ApiToken::verify($token);
    }
}
