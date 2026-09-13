<?php

namespace App\Modules\Identity\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Identity\Interfaces\Http\RateLimiting\LoginRateLimiter;
use App\Modules\Identity\Interfaces\Http\Requests\LoginRequest;
use App\Modules\Identity\Interfaces\Http\Resources\UserResource;
use App\Modules\Identity\Interfaces\Validation\Rules\BcryptCompatiblePassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, LoginRateLimiter $rateLimiter): JsonResponse
    {
        $email = (string) $request->validated('email');

        if ($rateLimiter->tooManyAttempts($request, $email)) {
            return response()->json([
                'message' => 'Too many attempts. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) $rateLimiter->availableIn($request, $email),
            ]);
        }

        $password = (string) $request->validated('password');

        if (! BcryptCompatiblePassword::accepts($password)) {
            return $this->authenticationFailed($request, $rateLimiter, $email);
        }

        $guard = Auth::guard('web');

        $authenticated = $guard->attempt([
            'email' => $email,
            'password' => $password,
        ]);

        if (! $authenticated) {
            return $this->authenticationFailed($request, $rateLimiter, $email);
        }

        $rateLimiter->clear($request, $email);
        $request->session()->regenerate();

        $user = $guard->user();

        if (! $user instanceof User) {
            throw new LogicException('The authenticated Identity user could not be resolved.');
        }

        return (new UserResource($user))->response();
    }

    private function authenticationFailed(
        LoginRequest $request,
        LoginRateLimiter $rateLimiter,
        string $email,
    ): JsonResponse {
        $rateLimiter->hit($request, $email);

        return response()->json([
            'message' => 'The provided credentials are incorrect.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
