<?php

namespace App\Modules\Identity\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Identity\Interfaces\Http\Resources\UserResource;
use Illuminate\Http\Request;
use LogicException;

final class MeController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('The authenticated Identity user could not be resolved.');
        }

        return new UserResource($user);
    }
}
