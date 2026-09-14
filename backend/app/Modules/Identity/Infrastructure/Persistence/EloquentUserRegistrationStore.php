<?php

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Application\Contracts\UserRegistrationStore;
use App\Modules\Identity\Application\Data\RegisteredUser;
use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

final class EloquentUserRegistrationStore implements UserRegistrationStore
{
    private const EMAIL_UNIQUE_CONSTRAINT = 'users_email_unique';

    public function create(string $name, string $email, string $password): RegisteredUser
    {
        try {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            if ($exception->index === self::EMAIL_UNIQUE_CONSTRAINT) {
                throw new EmailAlreadyRegistered($exception);
            }

            throw $exception;
        }

        $createdAt = $user->getAttribute('created_at');

        if (! $createdAt instanceof DateTimeInterface) {
            throw new LogicException('The registered user has no creation timestamp.');
        }

        return new RegisteredUser(
            id: (int) $user->getKey(),
            name: (string) $user->getAttribute('name'),
            email: (string) $user->getAttribute('email'),
            createdAt: DateTimeImmutable::createFromInterface($createdAt),
        );
    }
}
