<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateRfidDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = trim((string) config('services.rfid.device_token'));

        if ($expectedToken === '') {
            return $this->deny('RFID device token is not configured.', 503);
        }

        $providedToken = $request->bearerToken() ?: (string) $request->header('X-Device-Token', '');

        if ($providedToken === '' || ! hash_equals($expectedToken, trim($providedToken))) {
            return $this->deny('RFID device token is missing or invalid.', 401);
        }

        return $next($request);
    }

    private function deny(string $message, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], $status);
    }
}
