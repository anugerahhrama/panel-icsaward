<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the read-only Landing API with the static bearer token a superadmin generates in Settings → Landing API.
 */
class EnsureLandingApiToken
{
    /**
     * The setting holding the token, encrypted with `Setting::putEncrypted()`.
     */
    public const string SETTING_KEY = 'landing_api_token';

    /**
     * Reject the request unless it carries the current token; without a token the API is disabled.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = Setting::getEncrypted(self::SETTING_KEY);
        $bearerToken = $request->bearerToken();

        if ($token === null || $bearerToken === null || ! hash_equals($token, $bearerToken)) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
