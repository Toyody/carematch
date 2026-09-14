<?php

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Contracts\UserRegistrationStore;
use App\Modules\Identity\Application\Data\RegisteredUser;

final readonly class RegisterUser
{
    public function __construct(
        private UserRegistrationStore $users,
    ) {}

    public function handle(string $name, string $email, string $password): RegisteredUser
    {
        return $this->users->create($name, $email, $password);
    }
}
