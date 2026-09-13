<?php

namespace App\Modules\Identity\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Interfaces\Http\Requests\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

final class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink([
            'email' => (string) $request->validated('email'),
        ]);

        return response()->json([
            'message' => 'If an account exists for that email, a password reset link will be sent.',
        ], Response::HTTP_ACCEPTED);
    }
}
