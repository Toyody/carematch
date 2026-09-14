<?php

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\Data\RegisteredUser;

interface UserRegistrationStore
{
    public function create(string $name, string $email, string $password): RegisteredUser;
}
