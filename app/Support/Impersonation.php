<?php

namespace App\Support;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * "View portal": an admin sees a counsellor's portal with a short-lived,
 * read-only Sanctum token named "impersonation:{adminId}".
 */
class Impersonation
{
    public const TOKEN_PREFIX = 'impersonation:';
    public const MINUTES = 30;

    public static function tokenName(int $adminId): string
    {
        return self::TOKEN_PREFIX . $adminId;
    }

    /** The impersonation token on this request, if any. */
    public static function token(?Request $request = null): ?PersonalAccessToken
    {
        $request ??= request();
        $user = $request->user() ?? $request->user('sanctum');
        $token = $user?->currentAccessToken();

        return $token instanceof PersonalAccessToken && str_starts_with((string) $token->name, self::TOKEN_PREFIX)
            ? $token
            : null;
    }

    public static function active(?Request $request = null): bool
    {
        return self::token($request) !== null;
    }

    public static function adminId(PersonalAccessToken $token): ?int
    {
        $id = substr((string) $token->name, strlen(self::TOKEN_PREFIX));

        return ctype_digit($id) ? (int) $id : null;
    }
}
