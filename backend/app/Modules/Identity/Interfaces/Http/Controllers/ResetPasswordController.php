<?php

namespace App\Modules\Identity\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Actions\CompletePasswordReset;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Identity\Interfaces\Http\Requests\ResetPasswordRequest;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use LogicException;

final class ResetPasswordController extends Controller
{
    public function __invoke(
        ResetPasswordRequest $request,
        CompletePasswordReset $completePasswordReset,
    ): Response {
        $status = Password::reset(
            [
                'token' => (string) $request->validated('token'),
                'email' => (string) $request->validated('email'),
                'password' => (string) $request->validated('password'),
                'password_confirmation' => (string) $request->validated('password_confirmation'),
            ],
            static function (CanResetPassword $user, string $password) use ($completePasswordReset): void {
                if (! $user instanceof User) {
                    throw new LogicException('The password reset Identity user could not be resolved.');
                }

                $completePasswordReset->handle((int) $user->getKey(), $password);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['The password reset token is invalid or has expired.'],
            ]);
        }

        return response()->noContent();
    }
}
