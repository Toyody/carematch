<?php

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Application\Contracts\PasswordResetStore;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use LogicException;

final class EloquentPasswordResetStore implements PasswordResetStore
{
    public function reset(int $userId, string $password): void
    {
        $sessionTable = config('session.table');

        if (! is_string($sessionTable) || $sessionTable === '') {
            throw new LogicException('The database session table is not configured.');
        }

        $user = DB::transaction(static function () use ($password, $sessionTable, $userId): User {
            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($userId);

            $user->forceFill([
                'password' => $password,
            ]);
            $user->setRememberToken(Str::random(60));
            $user->save();

            DB::table($sessionTable)
                ->where('user_id', $userId)
                ->delete();

            return $user;
        });

        Event::dispatch(new PasswordReset($user));
    }
}
