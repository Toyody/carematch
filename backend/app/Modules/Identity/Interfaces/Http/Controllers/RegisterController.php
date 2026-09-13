<?php

namespace App\Modules\Identity\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Actions\RegisterUser;
use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Identity\Interfaces\Http\Requests\RegisterRequest;
use App\Modules\Identity\Interfaces\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        try {
            $user = $registerUser->handle(
                name: (string) $request->validated('name'),
                email: (string) $request->validated('email'),
                password: (string) $request->validated('password'),
            );
        } catch (EmailAlreadyRegistered) {
            throw ValidationException::withMessages([
                'email' => [__('validation.unique', ['attribute' => 'email'])],
            ]);
        }

        $authenticatedUser = Auth::guard('web')->loginUsingId($user->id);

        if (! $authenticatedUser instanceof User) {
            throw new LogicException('The registered user could not be authenticated.');
        }

        $request->session()->regenerate();

        return (new UserResource($authenticatedUser))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
