<?php
namespace App\Core;

use App\Models\ApiToken;

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
        $auth = (string)($req->header('Authorization') ?? '');
        if ($auth !== '' && stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        $fallback = (string)($req->header('X-Api-Key') ?? '');
        return trim($fallback);
    }

    /**
     * Hard gate: if the API key is missing/invalid, sends 401 and stops.
     * Set REQUIRE_API_KEY=0 in .env to disable in local dev if you want.
     */
    public static function requireApiKey(Request $req): void
    {
        //toggle via .env
        $enforce = !class_exists(Env::class) || Env::bool('REQUIRE_API_KEY', true);
        if (!$enforce) return;

        $token = self::apiKeyFrom($req);
        if ($token === '' || !ApiToken::verify($token)) {
            (new Response(401, 'Unauthorized', false, ['error' => 'invalid_or_missing_api_key']))->send();
        }
    }

    /** Soft check: returns true/false without sending a response. */
    public static function checkApiKey(Request $req): bool
    {
        $token = self::apiKeyFrom($req);
        return $token !== '' && ApiToken::verify($token);
    }
}
