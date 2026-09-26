<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardTutorialController extends Controller
{
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->role === 'guard', 403);

        $user->forceFill([
            'guard_tutorial_completed_at' => now(),
        ])->save();

        return response()->json([
            'completed' => true,
        ]);
    }
}
