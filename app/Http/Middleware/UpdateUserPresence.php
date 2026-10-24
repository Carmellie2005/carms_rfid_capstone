<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = Auth::user();

        if ($user?->role === 'guard') {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $response;
    }
}
