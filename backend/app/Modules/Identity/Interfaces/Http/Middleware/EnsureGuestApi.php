<?php

namespace App\Modules\Identity\Interfaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureGuestApi
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check()) {
            return response()->json([
                'message' => 'An authenticated user cannot perform this operation.',
            ], Response::HTTP_CONFLICT);
        }

        return $next($request);
    }
}
