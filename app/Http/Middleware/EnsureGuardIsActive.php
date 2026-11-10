<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuardIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $guardStatus = $user?->role === 'guard'
            ? $user->guardProfile()->value('status')
            : null;

        if ($guardStatus !== 'inactive') {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'This guard account is inactive. Contact the supervisor.',
            ], 403);
        }

        return redirect()
            ->route('login')
            ->withErrors([
                'email' => 'This guard account is inactive. Contact the supervisor.',
            ]);
    }
}
