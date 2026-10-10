<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e): Response
    {
        if ($e instanceof TokenMismatchException) {
            $message = 'Your session expired. Please log in again.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 419);
            }

            return redirect()
                ->guest(route('login'))
                ->with('status', $message);
        }

        return parent::render($request, $e);
    }
}
